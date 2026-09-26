<?php

namespace App\Http\Controllers;

use App\Models\Assistant;
use App\Models\Holiday;
use App\Models\Setting;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\SurveyResponseAnswer;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Schema\Blueprint;
use Carbon\Carbon;

class AssistantController extends Controller
{
    private function getTimezone($assistantId = null): string
    {
        if ($assistantId) {
            $tz = Setting::where('assistant_id', $assistantId)->where('key', 'timezone')->value('value');
            if (!empty($tz)) return $tz;
        }
        return 'America/Sao_Paulo';
    }

    private function configureTimezone($assistantId = null)
    {
        date_default_timezone_set($this->getTimezone($assistantId));
    }

    private function ensureWebhookLogTableExists()
    {
        if (!Schema::hasTable('webhook_logs')) {
            Schema::create('webhook_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('assistant_id');
                $table->string('sender')->nullable();
                $table->text('user_message')->nullable();
                $table->text('ai_reply')->nullable();
                $table->longText('wa_send_result')->nullable();
                $table->longText('raw_snippet')->nullable();
                $table->string('timestamp')->nullable();
                $table->timestamps();
            });
        }
    }

    private function ensureAssistantColumnsExist()
    {
        if (Schema::hasTable('assistants')) {
            if (!Schema::hasColumn('assistants', 'context_limit')) {
                Schema::table('assistants', function (Blueprint $table) {
                    $table->integer('context_limit')->default(12)->after('model');
                    $table->longText('lead_fields')->nullable()->after('context_limit');
                });
            }
        }
    }

    private function ensureChatMessagesTableExists()
    {
        if (!Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('assistant_id');
                $table->string('phone_number')->index();
                $table->string('protocol')->nullable();
                $table->string('role');
                $table->text('content');
                $table->timestamps();
            });
        } else {
            if (!Schema::hasColumn('chat_messages', 'protocol')) {
                Schema::table('chat_messages', function (Blueprint $table) {
                    $table->string('protocol')->nullable()->after('phone_number');
                });
            }
        }
    }

    private function ensureDepartmentTablesExist()
    {
        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('department_members')) {
            Schema::create('department_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('department_id');
                $table->string('name');
                $table->string('email');
                $table->timestamps();
            });
        }
    }

    private function ensureAppointmentsTableExists()
    {
        if (!Schema::hasTable('appointments')) {
            // Espelha o schema atual da tabela (pós-migration 2026_09_11_210000, que trocou
            // human_agent_id por user_id). Esse método só roda numa instalação nova sem migrations
            // aplicadas; se ficasse com human_agent_id aqui, todo o resto do código (que só usa
            // user_id) quebraria de cara.
            Schema::create('appointments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('google_event_id')->nullable();
                $table->dateTime('start_time');
                $table->dateTime('end_time');
                $table->string('client_name');
                $table->string('client_phone');
                $table->string('client_email');
                $table->string('status')->default('scheduled');
                $table->timestamps();
            });
        } else {
            if (!Schema::hasColumn('appointments', 'google_event_id')) {
                Schema::table('appointments', function (Blueprint $table) {
                    $table->string('google_event_id')->nullable();
                });
            }
        }
    }

    /**
     * @param bool $lock Quando true, deve ser chamado de dentro de um DB::transaction(): trava a
     *   linha de cada agente candidato (lockForUpdate) antes de checar conflito, fechando a janela
     *   de corrida entre "checar disponibilidade" e "gravar o agendamento" (duas conversas
     *   simultâneas não conseguem mais reservar o mesmo agente/horário).
     * @param int|null $excludeAppointmentId Ignora esse agendamento na checagem de conflito
     *   (usado no reagendamento, pra não conflitar com o próprio compromisso sendo movido).
     */
    private function allocateAgentRoundRobin(int $assistantId, int $departmentId, string $startDateTime, string $endDateTime, bool $lock = false, ?int $excludeAppointmentId = null)
    {
        $agents = DB::table('department_user')
            ->join('users', 'department_user.user_id', '=', 'users.id')
            ->where('department_user.department_id', $departmentId)
            ->where('users.is_active', 1)
            ->select('users.*')
            ->orderBy('users.id') // ordem estável entre chamadas concorrentes, evita deadlock nas travas
            ->get();

        if ($agents->isEmpty()) {
            return null;
        }

        $availableAgents = [];

        foreach ($agents as $agent) {
            if ($lock) {
                DB::table('users')->where('id', $agent->id)->lockForUpdate()->first();
            }

            $conflictQuery = DB::table('appointments')
                ->where('user_id', $agent->id)
                ->where('status', '!=', 'cancelled')
                ->where('start_time', '<', $endDateTime)
                ->where('end_time', '>', $startDateTime);

            if ($excludeAppointmentId) {
                $conflictQuery->where('id', '!=', $excludeAppointmentId);
            }

            if (!$conflictQuery->exists()) {
                $appointmentCount = DB::table('appointments')
                    ->where('user_id', $agent->id)
                    ->where('status', '!=', 'cancelled')
                    ->count();

                $availableAgents[] = [
                    'agent' => $agent,
                    'count' => $appointmentCount
                ];
            }
        }

        if (empty($availableAgents)) {
            return null;
        }

        usort($availableAgents, function ($a, $b) {
            return $a['count'] <=> $b['count'];
        });

        return $availableAgents[0]['agent'];
    }

    /**
     * Busca o agendamento ativo de um cliente por telefone+e-mail, com margem de 15 minutos na
     * data/hora informada (usado tanto no cancelamento quanto no reagendamento, pra não depender
     * de a IA reproduzir a data original com precisão de segundo). Se mais de um agendamento bater
     * com os critérios, não escolhe um "no achismo" - devolve a lista pra pedir confirmação.
     */
    private function findClientAppointment(string $phone, string $email, ?string $dateStr = null): array
    {
        $query = DB::table('appointments')
            ->where('client_phone', $phone)
            ->whereRaw('LOWER(TRIM(client_email)) = ?', [strtolower(trim($email))])
            ->where('status', 'scheduled')
            ->orderBy('start_time');

        if (!empty($dateStr)) {
            try {
                $parsed = Carbon::parse($dateStr);
                $query->whereBetween('start_time', [
                    (clone $parsed)->subMinutes(15)->toDateTimeString(),
                    (clone $parsed)->addMinutes(15)->toDateTimeString(),
                ]);
            } catch (\Throwable $e) {
                Log::warning("Falha ao parsear data no lookup de agendamento: " . $dateStr);
            }
        }

        $matches = $query->get();

        if ($matches->isEmpty()) {
            return ['status' => 'not_found'];
        }

        if ($matches->count() > 1) {
            return ['status' => 'ambiguous', 'matches' => $matches];
        }

        return ['status' => 'found', 'appointment' => $matches->first()];
    }

    private function getMeetingDurationMinutes(int $assistantId): int
    {
        $minutes = (int) (Setting::where('assistant_id', $assistantId)->where('key', 'meeting_duration_minutes')->value('value') ?? 60);
        return $minutes > 0 ? $minutes : 60;
    }

    /**
     * Confere se um horário pedido pro cliente cai dentro do expediente configurado (dias úteis +
     * janela de horário). A checagem de disponibilidade (allocateAgentRoundRobin) só olha se já tem
     * OUTRO compromisso marcado ali - ela não sabe nada sobre dia da semana ou horário comercial, e
     * por isso um sábado de madrugada sem nada marcado passava como "livre". Retorna null se estiver
     * tudo certo, ou a mensagem pra IA mandar ao cliente se estiver fora do expediente.
     */
    private function validateBusinessHours(Carbon $startTime, int $assistantId): ?string
    {
        $blockWeekends = (Setting::where('assistant_id', $assistantId)->where('key', 'business_block_weekends')->value('value') ?? '1') === '1';
        if ($blockWeekends && $startTime->isWeekend()) {
            return "\n\n⚠️ Poxa, não realizamos reuniões aos finais de semana - nosso atendimento é de segunda a sexta. Você teria disponibilidade em outro dia?";
        }

        $holiday = Holiday::where('assistant_id', $assistantId)
            ->where(function ($q) use ($startTime) {
                $q->where(function ($q2) use ($startTime) {
                    $q2->where('is_recurring', true)
                       ->whereMonth('date', $startTime->month)
                       ->whereDay('date', $startTime->day);
                })->orWhere(function ($q2) use ($startTime) {
                    $q2->where('is_recurring', false)
                       ->whereDate('date', $startTime->toDateString());
                });
            })
            ->first();

        if ($holiday) {
            return "\n\n⚠️ Poxa, o dia " . $startTime->format('d/m/Y') . " é feriado (*{$holiday->name}*) e não temos atendimento. Você teria disponibilidade em outro dia?";
        }

        $hoursStart = trim(Setting::where('assistant_id', $assistantId)->where('key', 'business_hours_start')->value('value') ?? '09:00');
        $hoursEnd = trim(Setting::where('assistant_id', $assistantId)->where('key', 'business_hours_end')->value('value') ?? '17:00');
        $timeStr = $startTime->format('H:i');

        if ($timeStr < $hoursStart || $timeStr >= $hoursEnd) {
            return "\n\n⚠️ Esse horário está fora do nosso período de atendimento ({$hoursStart} às {$hoursEnd}). Você teria disponibilidade em outro horário dentro desse período?";
        }

        return null;
    }

    private function processAppointmentTag(Assistant $assistant, string $aiReply, string $displayName, string $cleanSender): string
    {
        // 1. CHECAGEM PRÉVIA DA AGENDA
        if (preg_match('/\[VERIFICAR_AGENDA:(.*?)\]/s', $aiReply, $matches)) {
            $tagContent = $matches[1];
            preg_match('/data_hora=["\']([^"\']+)["\']/i', $tagContent, $mDate);
            preg_match('/departamento=["\']([^"\']+)["\']/i', $tagContent, $mDept);

            $checkDateStr = trim($mDate[1] ?? '');
            $deptName = trim($mDept[1] ?? '');

            try {
                $startTime = Carbon::parse($checkDateStr);
                $endTime = (clone $startTime)->addMinutes($this->getMeetingDurationMinutes($assistant->id));

                if ($businessHoursError = $this->validateBusinessHours($startTime, $assistant->id)) {
                    return trim(preg_replace('/\[VERIFICAR_AGENDA:.*?\]/s', $businessHoursError, $aiReply));
                }

                $dept = null;
                if (!empty($deptName)) {
                    $cleanTerm = strtolower($deptName);
                    $dept = DB::table('departments')
                        ->where('assistant_id', $assistant->id)
                        ->where(function($q) use ($cleanTerm) {
                            $q->whereRaw('LOWER(TRIM(name)) = ?', [$cleanTerm])
                              ->orWhereRaw('LOWER(name) LIKE ?', ['%' . $cleanTerm . '%']);
                        })
                        ->first();
                }

                if (!$dept) {
                    $defaultDeptId = Setting::where('assistant_id', $assistant->id)->where('key', 'default_department_id')->value('value');
                    if ($defaultDeptId) {
                        $dept = DB::table('departments')->where('assistant_id', $assistant->id)->where('id', $defaultDeptId)->first();
                    }
                }

                if (!$dept) {
                    $dept = DB::table('departments')->where('assistant_id', $assistant->id)->first();
                }

                if (!$dept) {
                    $msg = "\n\n⚠️ *Aviso do Sistema:* Nenhum setor de atendimento está cadastrado para este assistente.";
                    return trim(preg_replace('/\[VERIFICAR_AGENDA:.*?\]/s', $msg, $aiReply));
                }

                $allocatedAgent = $this->allocateAgentRoundRobin(
                    $assistant->id,
                    $dept->id,
                    $startTime->toDateTimeString(),
                    $endTime->toDateTimeString()
                );

                if (!$allocatedAgent) {
                    $msg = "\n\n⚠️ Poxa, acabei de verificar e infelizmente a equipe do setor *" . $dept->name . "* já possui compromissos agendados para " . $startTime->format('d/m/Y \à\s H:i') . ".\n\nVocê teria disponibilidade em **outra data ou horário**?";
                    return trim(preg_replace('/\[VERIFICAR_AGENDA:.*?\]/s', $msg, $aiReply));
                }

                $msg = "\n\n✅ Verifiquei aqui e o horário de *" . $startTime->format('d/m/Y \à\s H:i') . "* está **LIVRE** no setor *" . $dept->name . "*!\n\nQual o seu e-mail principal para enviarmos o convite da reunião?";
                return trim(preg_replace('/\[VERIFICAR_AGENDA:.*?\]/s', $msg, $aiReply));

            } catch (\Throwable $e) {
                Log::error("Erro na verificação de agenda: " . $e->getMessage());
                $msg = "\n\n⚠️ *Erro do Sistema:* Não foi possível consultar o calendário para essa data. Por favor, confirme o dia e o horário desejados.";
                return trim(preg_replace('/\[VERIFICAR_AGENDA:.*?\]/s', $msg, $aiReply));
            }
        }

        // 2. CANCELAR REUNIÃO
        if (preg_match('/\[(?:CANCELAR_REUNIAO|Cancelar reunião|CANCELAR_AGENDAMENTO|CANCELAR)\s*:(.*?)\]/is', $aiReply, $matches)) {
            $tagContent = $matches[1];
            preg_match('/email_cliente=["\']([^"\']+)["\']/i', $tagContent, $mEmail);
            preg_match('/data_hora=["\']([^"\']+)["\']/i', $tagContent, $mDate);

            $emailInput = trim($mEmail[1] ?? '');
            $origDateStr = trim($mDate[1] ?? '');

            $lookup = $this->findClientAppointment($cleanSender, $emailInput, $origDateStr ?: null);

            if ($lookup['status'] === 'not_found') {
                $msg = "\n\n⚠️ Não encontramos nenhuma reunião ativa associada ao seu e-mail *{$emailInput}* para essa data e horário.";
                return trim(preg_replace('/\[(?:CANCELAR_REUNIAO|Cancelar reunião|CANCELAR_AGENDAMENTO|CANCELAR):.*?\]/is', $msg, $aiReply));
            }

            if ($lookup['status'] === 'ambiguous') {
                $list = $lookup['matches']->map(fn($a) => '• ' . Carbon::parse($a->start_time)->format('d/m/Y \à\s H:i'))->implode("\n");
                $msg = "\n\n⚠️ Encontramos mais de uma reunião ativa com esse e-mail. Qual delas você deseja cancelar?\n\n{$list}";
                return trim(preg_replace('/\[(?:CANCELAR_REUNIAO|Cancelar reunião|CANCELAR_AGENDAMENTO|CANCELAR):.*?\]/is', $msg, $aiReply));
            }

            $appointment = $lookup['appointment'];

            if (!empty($appointment->google_event_id)) {
                $googleService = new GoogleCalendarService();
                $cancelResult = $googleService->cancelMeeting($assistant->id, $appointment->google_event_id);
                
                if (!$cancelResult) {
                    $msg = "\n\n⚠️ Erro técnico ao comunicar com o Google Calendar para o cancelamento. Tente novamente em instantes.";
                    return trim(preg_replace('/\[(?:CANCELAR_REUNIAO|Cancelar reunião|CANCELAR_AGENDAMENTO|CANCELAR):.*?\]/is', $msg, $aiReply));
                }
            }

            DB::table('appointments')->where('id', $appointment->id)->update([
                'status' => 'cancelled',
                'updated_at' => now()
            ]);

            $msg = "\n\n❌ *REUNIÃO CANCELADA COM SUCESSO!*\n\nO agendamento do dia " . Carbon::parse($appointment->start_time)->format('d/m/Y \à\s H:i') . " foi cancelado na agenda e os participantes foram notificados.\n\nPosso te ajudar em mais alguma coisa, ou podemos encerrar por aqui? [ENCERRAMENTO]";

            return trim(preg_replace('/\[(?:CANCELAR_REUNIAO|Cancelar reunião|CANCELAR_AGENDAMENTO|CANCELAR):.*?\](.*)$/is', $msg, $aiReply));
        }

        // 3. REAGENDAR REUNIAO
        if (preg_match('/\[REAGENDAR_REUNIAO:(.*?)\]/s', $aiReply, $matches)) {
            $tagContent = $matches[1];
            preg_match('/email_cliente=["\']([^"\']+)["\']/i', $tagContent, $mEmail);
            preg_match('/data_hora_original=["\']([^"\']+)["\']/i', $tagContent, $mOrigDate);
            preg_match('/nova_data_hora=["\']([^"\']+)["\']/i', $tagContent, $mDate);
            preg_match('/departamento=["\']([^"\']+)["\']/i', $tagContent, $mDept);

            $emailInput = trim($mEmail[1] ?? '');
            $origDateStr = trim($mOrigDate[1] ?? '');
            $newDateStr = trim($mDate[1] ?? '');
            $deptName = trim($mDept[1] ?? '');

            $lookup = $this->findClientAppointment($cleanSender, $emailInput, $origDateStr ?: null);

            if ($lookup['status'] === 'not_found') {
                $msg = "\n\n⚠️ Não encontramos nenhuma reunião ativa para a data/e-mail fornecidos (*{$emailInput}*).";
                return trim(preg_replace('/\[REAGENDAR_REUNIAO:.*?\]/s', $msg, $aiReply));
            }

            if ($lookup['status'] === 'ambiguous') {
                $list = $lookup['matches']->map(fn($a) => '• ' . Carbon::parse($a->start_time)->format('d/m/Y \à\s H:i'))->implode("\n");
                $msg = "\n\n⚠️ Encontramos mais de uma reunião ativa com esse e-mail. Qual delas você deseja reagendar?\n\n{$list}";
                return trim(preg_replace('/\[REAGENDAR_REUNIAO:.*?\]/s', $msg, $aiReply));
            }

            $existingAppointment = $lookup['appointment'];

            try {
                $newStartTime = Carbon::parse($newDateStr);
                $newEndTime = (clone $newStartTime)->addMinutes($this->getMeetingDurationMinutes($assistant->id));

                if ($businessHoursError = $this->validateBusinessHours($newStartTime, $assistant->id)) {
                    return trim(preg_replace('/\[REAGENDAR_REUNIAO:.*?\]/s', $businessHoursError, $aiReply));
                }

                $dept = null;
                if (!empty($deptName)) {
                    $cleanTerm = strtolower($deptName);
                    $dept = DB::table('departments')
                        ->where('assistant_id', $assistant->id)
                        ->where(function($q) use ($cleanTerm) {
                            $q->whereRaw('LOWER(TRIM(name)) = ?', [$cleanTerm])
                              ->orWhereRaw('LOWER(name) LIKE ?', ['%' . $cleanTerm . '%']);
                        })
                        ->first();
                }

                if (!$dept) {
                    $dept = DB::table('departments')->where('assistant_id', $assistant->id)->first();
                }

                // Trava o agente candidato e já grava o novo horário atomicamente, fechando a
                // janela de corrida entre "checar disponibilidade" e "gravar o reagendamento".
                $reservation = DB::transaction(function () use ($assistant, $dept, $newStartTime, $newEndTime, $existingAppointment) {
                    $agent = $this->allocateAgentRoundRobin(
                        $assistant->id,
                        $dept->id,
                        $newStartTime->toDateTimeString(),
                        $newEndTime->toDateTimeString(),
                        lock: true,
                        excludeAppointmentId: $existingAppointment->id
                    );

                    if (!$agent) {
                        return null;
                    }

                    DB::table('appointments')->where('id', $existingAppointment->id)->update([
                        'user_id' => $agent->id,
                        'start_time' => $newStartTime->toDateTimeString(),
                        'end_time' => $newEndTime->toDateTimeString(),
                        'updated_at' => now(),
                    ]);

                    return $agent;
                }, 3);

                if (!$reservation) {
                    $msg = "\n\n⚠️ Nossa equipe do setor *" . $dept->name . "* já está ocupada para " . $newStartTime->format('d/m/Y \à\s H:i') . ". Sua reunião original permanece mantida.";
                    return trim(preg_replace('/\[REAGENDAR_REUNIAO:.*?\]/s', $msg, $aiReply));
                }

                $allocatedAgent = $reservation;

                $googleService = new GoogleCalendarService();
                $meetingResult = null;
                $originalEventGone = false;

                if (!empty($existingAppointment->google_event_id)) {
                    $updateOutcome = $googleService->updateMeeting(
                        $assistant->id,
                        $existingAppointment->google_event_id,
                        $newStartTime->toDateTimeString(),
                        $newEndTime->toDateTimeString(),
                        $allocatedAgent->email
                    );

                    // false = o evento não existe mais no Google (ex: apagado manualmente na conta
                    // do atendente) - seguro criar um novo. null = falha real de comunicação; nesse
                    // caso NÃO criamos um evento novo, senão duplicaríamos a reunião no calendário.
                    if ($updateOutcome === false) {
                        $originalEventGone = true;
                    } else {
                        $meetingResult = $updateOutcome;
                    }
                }

                if (!$meetingResult && (empty($existingAppointment->google_event_id) || $originalEventGone)) {
                    $eventDescription = "📋 Agendamento Reagendado via WhatsApp - InHouse Contact Center\n\n" .
                                       "👤 Cliente: " . $displayName . "\n" .
                                       "📱 Telefone: " . $cleanSender . "\n" .
                                       "📧 E-mail: " . $emailInput . "\n" .
                                       "🏢 Setor: " . $dept->name . "\n" .
                                       "🎧 Atendente Responsável: " . $allocatedAgent->name . "\n" .
                                       "📅 Nova Data e Hora: " . $newStartTime->format('d/m/Y \à\s H:i');

                    $meetingResult = $googleService->createMeeting(
                        $assistant->id,
                        "Reunião - InHouse x " . $displayName,
                        $eventDescription,
                        $newStartTime->toDateTimeString(),
                        $newEndTime->toDateTimeString(),
                        $allocatedAgent->email,
                        $emailInput
                    );
                }

                if (!$meetingResult) {
                    // Desfaz a reserva de horário feita acima, mantendo a reunião original intacta
                    // em vez de deixar o horário interno mudado sem o convite real no Google.
                    DB::table('appointments')->where('id', $existingAppointment->id)->update([
                        'user_id' => $existingAppointment->user_id,
                        'start_time' => $existingAppointment->start_time,
                        'end_time' => $existingAppointment->end_time,
                        'updated_at' => now(),
                    ]);
                    $msg = "\n\n⚠️ Erro técnico ao comunicar com o Google Calendar para o reagendamento. Tente em instantes.";
                    return trim(preg_replace('/\[REAGENDAR_REUNIAO:.*?\]/s', $msg, $aiReply));
                }

                DB::table('appointments')->where('id', $existingAppointment->id)->update([
                    'google_event_id' => $meetingResult['event_id'] ?? $existingAppointment->google_event_id,
                ]);

                $msg = "\n\n🔄 *REUNIÃO REAGENDADA COM SUCESSO!*\n\n";
                $msg .= "👤 *Atendente:* " . $allocatedAgent->name . "\n";
                $msg .= "📅 *Nova Data/Hora:* " . $newStartTime->format('d/m/Y \à\s H:i') . "\n";
                if ($meetingResult['meet_link'] ?? false) $msg .= "🎥 *Link do Google Meet:* " . $meetingResult['meet_link'] . "\n\n";

                $msg .= "Posso te ajudar em mais alguma coisa, ou podemos encerrar por aqui? [ENCERRAMENTO]";

                return trim(preg_replace('/\[REAGENDAR_REUNIAO:.*?\](.*)$/s', $msg, $aiReply));

            } catch (\Throwable $e) {
                Log::error("Erro ao reagendar reunião: " . $e->getMessage());
                $msg = "\n\n⚠️ Falha ao alterar o agendamento. Tente em instantes.";
                return trim(preg_replace('/\[REAGENDAR_REUNIAO:.*?\]/s', $msg, $aiReply));
            }
        }

        // 4. AGENDAR NOVA REUNIÃO DEFINITIVA
        if (preg_match('/\[AGENDAR_REUNIAO:(.*?)\]/s', $aiReply, $matches)) {
            $tagContent = $matches[1];
            
            $deptName = null;
            $startDateTimeStr = null;
            $clientEmail = null;
            $additionalEmails = [];

            if (preg_match('/departamento=["\']([^"\']+)["\']/i', $tagContent, $m)) $deptName = trim($m[1]);
            if (preg_match('/data_hora_inicio=["\']([^"\']+)["\']/i', $tagContent, $m)) $startDateTimeStr = trim($m[1]);
            if (preg_match('/email_cliente=["\']([^"\']+)["\']/i', $tagContent, $m)) $clientEmail = trim($m[1]);
            if (preg_match('/emails_adicionais=["\']([^"\']+)["\']/i', $tagContent, $m)) {
                $rawAdd = trim($m[1]);
                if (!empty($rawAdd)) $additionalEmails = array_map('trim', explode(',', $rawAdd));
            }

            if (!$deptName || !$startDateTimeStr || !$clientEmail) {
                return trim(preg_replace('/\[AGENDAR_REUNIAO:.*?\]/s', '', $aiReply));
            }

            try {
                $startTime = Carbon::parse($startDateTimeStr);
                $endTime = (clone $startTime)->addMinutes($this->getMeetingDurationMinutes($assistant->id));

                if ($businessHoursError = $this->validateBusinessHours($startTime, $assistant->id)) {
                    return trim(preg_replace('/\[AGENDAR_REUNIAO:.*?\]/s', $businessHoursError, $aiReply));
                }

                $dept = null;
                if (!empty($deptName)) {
                    $cleanTerm = strtolower($deptName);
                    $dept = DB::table('departments')
                        ->where('assistant_id', $assistant->id)
                        ->where(function($q) use ($cleanTerm) {
                            $q->whereRaw('LOWER(TRIM(name)) = ?', [$cleanTerm])
                              ->orWhereRaw('LOWER(name) LIKE ?', ['%' . $cleanTerm . '%']);
                        })
                        ->first();
                }

                if (!$dept) {
                    $dept = DB::table('departments')->where('assistant_id', $assistant->id)->first();
                }

                // Trava o agente candidato e já reserva o horário atomicamente (grava a linha em
                // appointments antes de falar com o Google), fechando a janela de corrida entre
                // "checar disponibilidade" e "confirmar o agendamento".
                $reservation = DB::transaction(function () use ($assistant, $dept, $startTime, $endTime, $displayName, $cleanSender, $clientEmail) {
                    $agent = $this->allocateAgentRoundRobin(
                        $assistant->id,
                        $dept->id,
                        $startTime->toDateTimeString(),
                        $endTime->toDateTimeString(),
                        lock: true
                    );

                    if (!$agent) {
                        return null;
                    }

                    $appointmentId = DB::table('appointments')->insertGetId([
                        'user_id' => $agent->id,
                        'google_event_id' => null,
                        'start_time' => $startTime->toDateTimeString(),
                        'end_time' => $endTime->toDateTimeString(),
                        'client_name' => $displayName,
                        'client_phone' => $cleanSender,
                        'client_email' => $clientEmail,
                        'status' => 'scheduled',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return ['agent' => $agent, 'appointment_id' => $appointmentId];
                }, 3);

                if (!$reservation) {
                    $msg = "\n\n⚠️ Ocorreu uma mudança de disponibilidade e esse horário não está mais livre no setor *" . $dept->name . "*. Qual outra data podemos agendar?";
                    return trim(preg_replace('/\[AGENDAR_REUNIAO:.*?\]/s', $msg, $aiReply));
                }

                $allocatedAgent = $reservation['agent'];
                $appointmentId = $reservation['appointment_id'];

                $eventDescription = "📋 Agendamento via WhatsApp - InHouse Contact Center\n\n" .
                                   "👤 Cliente: " . $displayName . "\n" .
                                   "📱 Telefone: " . $cleanSender . "\n" .
                                   "📧 E-mail: " . $clientEmail . "\n" .
                                   "🏢 Setor: " . $dept->name . "\n" .
                                   "🎧 Atendente Responsável: " . $allocatedAgent->name . "\n" .
                                   "📅 Data e Hora: " . $startTime->format('d/m/Y \à\s H:i');

                $googleService = new GoogleCalendarService();
                $meetingResult = $googleService->createMeeting(
                    $assistant->id,
                    "Reunião - InHouse x " . $displayName,
                    $eventDescription,
                    $startTime->toDateTimeString(),
                    $endTime->toDateTimeString(),
                    $allocatedAgent->email,
                    $clientEmail,
                    $additionalEmails
                );

                if (!$meetingResult) {
                    // Desfaz a reserva: sem convite real no Google, não faz sentido manter o
                    // horário travado internamente pro cliente.
                    DB::table('appointments')->where('id', $appointmentId)->delete();
                    $msg = "\n\n⚠️ Erro técnico ao criar a reunião no Google Calendar. Tente em instantes.";
                    return trim(preg_replace('/\[AGENDAR_REUNIAO:.*?\]/s', $msg, $aiReply));
                }

                DB::table('appointments')->where('id', $appointmentId)->update([
                    'google_event_id' => $meetingResult['event_id'] ?? null,
                ]);

                $allEmails = array_unique(array_filter(array_merge([$clientEmail], $additionalEmails)));

                $msg = "\n\n✅ **REUNIÃO CONFIRMADA COM SUCESSO!**\n\n";
                $msg .= "👤 *Atendente:* " . $allocatedAgent->name . "\n";
                $msg .= "🏢 *Setor:* " . $dept->name . "\n";
                $msg .= "📅 *Data/Hora:* " . $startTime->format('d/m/Y \à\s H:i') . "\n";
                $msg .= "✉️ *Convites enviados para:* " . implode(', ', $allEmails) . "\n";

                if ($meetingResult['meet_link'] ?? false) {
                    $msg .= "🎥 *Link do Google Meet:* " . $meetingResult['meet_link'] . "\n";
                }

                $msg .= "\nPosso te ajudar em mais alguma coisa, ou podemos encerrar por aqui? [ENCERRAMENTO]";

                return trim(preg_replace('/\[AGENDAR_REUNIAO:.*?\](.*)$/s', $msg, $aiReply));

            } catch (\Throwable $e) {
                Log::error("Erro no agendamento final: " . $e->getMessage());
                $msg = "\n\n⚠️ Erro técnico ao criar a reunião no Google Calendar. Tente em instantes.";
                return trim(preg_replace('/\[AGENDAR_REUNIAO:.*?\]/s', $msg, $aiReply));
            }
        }

        // Trava de segurança: remove qualquer tag residual de agendamento em colchetes
        $aiReply = preg_replace('/\[(?:VERIFICAR_AGENDA|AGENDAR_REUNIAO|CANCELAR_REUNIAO|REAGENDAR_REUNIAO|Cancelar reunião|CANCELAR|REAGENDAR).*?\]/is', '', $aiReply);

        return trim($aiReply);
    }

    public function index(Request $request)
    {
        if ($request->has('view_file')) {
            return $this->servePublicFile($request->input('view_file'));
        }

        $assistantIdForTz = $request->input('configure') ?? $request->input('conversations_id') ?? $request->input('chat_id');
        $this->configureTimezone($assistantIdForTz);

        $this->ensureWebhookLogTableExists();
        $this->ensureAssistantColumnsExist();
        $this->ensureChatMessagesTableExists();
        $this->ensureDepartmentTablesExist();

        $currentView = $request->input('view', 'robots');

        if ($request->isMethod('post') && $request->input('action') === 'store_agent') return $this->storeAgent($request);
        if ($request->isMethod('post') && $request->input('action') === 'store_department') return $this->storeDepartment($request);
        if ($request->isMethod('post') && $request->input('action') === 'test_ai') return $this->testAi($request);
        
        if ($request->isMethod('post') && $request->input('action') === 'status_whatsapp') return $this->checkWhatsappStatus($request, false);
        if ($request->isMethod('post') && $request->input('action') === 'test_whatsapp') return $this->checkWhatsappStatus($request, true);
        if ($request->isMethod('post') && $request->input('action') === 'disconnect_whatsapp') return $this->disconnectWhatsapp($request);
        
        if ($request->isMethod('post') && $request->input('action') === 'map_site') return $this->mapSite($request);
        if ($request->isMethod('post') && $request->input('action') === 'scrape_single_url') return $this->scrapeSingleUrl($request);

        if ($request->isMethod('post')) return $this->store($request);
        if ($request->isMethod('put')) return $this->update($request);
        if ($request->isMethod('patch')) return $this->toggleActive($request);
        if ($request->isMethod('delete')) return $this->destroyOrRemoveFile($request);

        $assistants = Assistant::orderBy('name', 'asc')->get();
        $departments = DB::table('departments')->get();
        $agents = DB::table('department_members')->get();

        $configuring = null;
        $lastWebhook = null;
        $conversationsAssistant = null;
        $conversationThreads = [];
        $activeThreadMessages = [];
        $activePhone = $request->input('phone');
        $assistantTz = 'America/Sao_Paulo';

        if ($request->has('conversations_id')) {
            $conversationsAssistant = Assistant::find($request->conversations_id);
            if ($conversationsAssistant) {
                $assistantTz = $this->getTimezone($conversationsAssistant->id);
                $conversationThreads = DB::table('chat_messages')
                    ->where('assistant_id', $conversationsAssistant->id)
                    ->select('phone_number', DB::raw('MAX(created_at) as last_activity'), DB::raw('COUNT(id) as total_messages'))
                    ->groupBy('phone_number')
                    ->orderBy('last_activity', 'desc')
                    ->get();

                if (!$activePhone && count($conversationThreads) > 0) {
                    $activePhone = $conversationThreads[0]->phone_number;
                }

                if ($activePhone) {
                    $activeThreadMessages = DB::table('chat_messages')
                        ->where('assistant_id', $conversationsAssistant->id)
                        ->where('phone_number', $activePhone)
                        ->orderBy('id', 'asc')
                        ->get();
                }
            }
        }

        if ($request->has('configure')) {
            $configuring = Assistant::find($request->configure);
            if ($configuring) {
                $assistantTz = $this->getTimezone($configuring->id);
                if (is_string($configuring->lead_fields)) {
                    $configuring->lead_fields = json_decode($configuring->lead_fields, true);
                }
                if (!is_array($configuring->lead_fields)) {
                    $configuring->lead_fields = [];
                }

                $log = DB::table('webhook_logs')->where('assistant_id', $configuring->id)->latest('id')->first();
                if ($log) {
                    $lastWebhook = (array) $log;
                    if (isset($lastWebhook['wa_send_result']) && is_string($lastWebhook['wa_send_result'])) {
                        $lastWebhook['wa_send_result'] = json_decode($lastWebhook['wa_send_result'], true);
                    }
                }
            }
        }

        return view('assistants.index', compact(
            'assistants', 'configuring', 'lastWebhook',
            'conversationsAssistant', 'conversationThreads', 'activeThreadMessages', 'activePhone', 'currentView',
            'departments', 'agents', 'assistantTz'
        ));
    }

    public function servePublicFileRoute(string $path)
    {
        return $this->servePublicFile($path);
    }

    public function rename(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
        ]);

        $assistant = Assistant::findOrFail($id);
        $assistant->update([
            'name' => $request->name,
            'company_name' => trim($request->input('company_name') ?? '') ?: null,
        ]);

        return response()->json(['success' => true, 'name' => $assistant->name, 'company_name' => $assistant->company_name]);
    }

    private function servePublicFile($relativePath)
    {
        $cleanPath = ltrim(str_replace(['..', '\\'], ['', '/'], (string)$relativePath), '/');

        $candidates = [
            storage_path('app/public/' . $cleanPath),
            storage_path('app/' . $cleanPath),
            public_path($cleanPath),
        ];

        $targetFile = null;
        foreach ($candidates as $candidate) {
            if (file_exists($candidate) && is_file($candidate)) {
                $targetFile = $candidate;
                break;
            }
        }

        if (!$targetFile) {
            return response()->json([
                'error' => 'Arquivo não encontrado no servidor',
                'caminho_buscado' => $cleanPath
            ], 404);
        }

        $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $mime = 'application/octet-stream';

        if ($ext === 'mp4') $mime = 'video/mp4';
        elseif ($ext === 'pdf') $mime = 'application/pdf';
        elseif (in_array($ext, ['jpg', 'jpeg'])) $mime = 'image/jpeg';
        elseif ($ext === 'png') $mime = 'image/png';
        elseif ($ext === 'webp') $mime = 'image/webp';
        elseif ($ext === 'mp3') $mime = 'audio/mpeg';
        elseif ($ext === 'ogg') $mime = 'audio/ogg';
        elseif ($ext === 'txt') $mime = 'text/plain';

        return response()->file($targetFile, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($targetFile) . '"'
        ]);
    }

    private function checkWhatsappStatus(Request $request, $isTest = false)
    {
        $assistantId = $request->input('assistant_id');
        $assistant = $assistantId ? Assistant::find($assistantId) : null;

        $baseUrl = rtrim($request->input('url') ?? ($assistant->whatsapp_url ?? ''), '/');
        $token = trim($request->input('token') ?? ($assistant->whatsapp_token ?? ''));
        $instance = trim($request->input('instance') ?? ($assistant->whatsapp_instance ?? ''));
        $provider = $request->input('provider') ?? ($assistant->whatsapp_provider ?? '');

        if (!$baseUrl || !$token) {
            return response()->json(['connected' => false, 'success' => false, 'message' => 'Credenciais incompletas.']);
        }

        $isUazapi = str_contains($baseUrl, 'uazapi.com') || $provider === 'uazapi';

        try {
            // A UazAPI só reconhece o header "token" (confirmado testando manualmente com
            // curl). Mandar os headers extras abaixo (chutes genéricos pra outros provedores)
            // junto pode fazer a API tratar a chamada como não autenticada e responder algo
            // sem QR Code, mesmo com o token certo.
            $headers = $isUazapi ? [
                'token' => $token,
                'Content-Type' => 'application/json'
            ] : [
                'token' => $token,
                'Client-Token' => $token,
                'client-token' => $token,
                'apikey' => $token,
                'Content-Type' => 'application/json'
            ];

            $params = array_filter([
                'token' => $token,
                'instance' => $instance
            ]);

            // A UazAPI só tem um endpoint real de status (identifica a instância pelo header
            // "token", sem precisar de sufixo na URL). Os outros caminhos abaixo são chutes
            // genéricos (estilo Evolution API) mantidos só para os demais provedores.
            $statusPaths = $isUazapi ? ['/instance/status'] : [
                '/instance/connectionState/' . $instance,
                '/instance/connectionState',
                '/instance/status/' . $instance,
                '/instance/status'
            ];

            $connected = false;
            $statusParams = $isUazapi ? [] : $params;

            foreach ($statusPaths as $path) {
                $url = $baseUrl . $path;
                $res = Http::withHeaders($headers)->get($url, $statusParams);

                if (!$res->successful()) {
                    $res = $isUazapi
                        ? Http::withHeaders($headers)->send('POST', $url)
                        : Http::withHeaders($headers)->post($url, $statusParams);
                }

                if ($res->successful()) {
                    $json = $res->json();
                    if (is_array($json)) {
                        if (
                            (!empty($json['connected']) && $json['connected'] === true) ||
                            (!empty($json['instance']['connected']) && $json['instance']['connected'] === true) ||
                            (!empty($json['status']['connected']) && $json['status']['connected'] === true)
                        ) {
                            $connected = true;
                            break;
                        }

                        $state = $json['instance']['state']
                              ?? $json['instance']['status']
                              ?? $json['state']
                              ?? $json['status']
                              ?? $json['connectionStatus']
                              ?? null;

                        if (is_array($state)) {
                            $state = $state['state'] ?? $state['status'] ?? null;
                        }

                        if (is_string($state)) {
                            $stateClean = strtolower(trim($state));
                            if (in_array($stateClean, ['open', 'connected', 'conectado', 'connecting_online', 'pair', 'paired', 'working', 'online'])) {
                                $connected = true;
                                break;
                            }
                        }
                    }
                }
            }

            if ($connected) {
                return response()->json(['connected' => true, 'success' => true, 'message' => 'WhatsApp conectado!']);
            }

            // O front-end já tem um QR Code na tela e está só perguntando se já conectou.
            // Não chamamos /instance/connect de novo aqui: isso geraria/invalidaria um QR
            // novo a cada 3s (era essa a causa do QR nunca ficar tempo suficiente na tela
            // para ser escaneado, mesmo a API respondendo certo em cada chamada isolada).
            if ($isTest && $request->boolean('has_qr')) {
                return response()->json([
                    'connected' => false,
                    'success' => true,
                    'message' => 'Aguardando leitura do QR Code...'
                ]);
            }

            if ($isTest) {
                // Idem: a UazAPI só tem um endpoint real de conectar/gerar QR Code.
                $qrPaths = $isUazapi ? ['/instance/connect'] : [
                    '/instance/connect/' . $instance,
                    '/instance/connect',
                    '/instance/qr/' . $instance,
                    '/instance/qr'
                ];

                // A UazAPI identifica a instância só pelo header "token"; mandar corpo/params
                // extras nessa chamada específica pode fazer a API responder diferente do
                // esperado (confirmado testando manualmente sem corpo nenhum).
                $qrParams = $isUazapi ? [] : $params;

                foreach ($qrPaths as $path) {
                    $url = $baseUrl . $path;
                    // Http::post($url, []) manda o corpo literal "[]" (Laravel serializa o
                    // array vazio como JSON), diferente de não mandar corpo nenhum. O teste
                    // manual que funcionou com a UazAPI não mandava corpo algum, então para
                    // ela replicamos isso com send() puro em vez de post() com array vazio.
                    $res = $isUazapi
                        ? Http::withHeaders($headers)->send('POST', $url)
                        : Http::withHeaders($headers)->post($url, $qrParams);
                    if (!$res->successful()) {
                        $res = Http::withHeaders($headers)->get($url, $qrParams);
                    }

                    if ($res->successful()) {
                        $json = $res->json();
                        if (is_array($json)) {
                            $qr = $json['qrcode']
                               ?? $json['base64']
                               ?? $json['qr']
                               ?? $json['code']
                               ?? ($json['data']['qrcode'] ?? null)
                               ?? ($json['data']['base64'] ?? null)
                               ?? ($json['instance']['qrcode'] ?? null);

                            if (is_array($qr)) {
                                $qr = $qr['base64'] ?? $qr['qrcode'] ?? $qr['code'] ?? null;
                            }

                            if (is_string($qr) && !empty($qr)) {
                                if (!str_starts_with($qr, 'data:image')) {
                                    $qr = 'data:image/png;base64,' . $qr;
                                }
                                return response()->json([
                                    'connected' => false,
                                    'success' => true,
                                    'qr' => $qr,
                                    'message' => 'Escaneie o QR Code no seu celular.'
                                ]);
                            }
                        }
                    }
                }

                // Não achamos QR Code em nenhum formato conhecido: antes isso assumia
                // "conectado" por padrão (bug real). Agora é honesto: reporta que ainda
                // está tentando, sem inventar um estado que não foi confirmado pela API.
                return response()->json([
                    'connected' => false,
                    'success' => true,
                    'message' => 'Aguardando o QR Code da instância. Tente novamente em alguns segundos.'
                ]);
            }

            return response()->json(['connected' => false, 'success' => true, 'message' => 'WhatsApp desconectado.']);

        } catch (\Throwable $e) {
            Log::error("Erro checando status WhatsApp: " . $e->getMessage());
            return response()->json(['connected' => false, 'success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    private function disconnectWhatsapp(Request $request)
    {
        $assistantId = $request->input('assistant_id');
        $assistant = $assistantId ? Assistant::find($assistantId) : null;

        $baseUrl = rtrim($request->input('url') ?? ($assistant->whatsapp_url ?? ''), '/');
        $token = trim($request->input('token') ?? ($assistant->whatsapp_token ?? ''));
        $instance = trim($request->input('instance') ?? ($assistant->whatsapp_instance ?? ''));
        $provider = $request->input('provider') ?? ($assistant->whatsapp_provider ?? '');

        if ($baseUrl && $token) {
            try {
                $headers = [
                    'token' => $token,
                    'Client-Token' => $token,
                    'client-token' => $token,
                    'apikey' => $token,
                    'Content-Type' => 'application/json'
                ];

                $payload = array_filter([
                    'token' => $token,
                    'instance' => $instance
                ]);

                if (str_contains($baseUrl, 'uazapi.com') || $provider === 'uazapi') {
                    $response = Http::withHeaders($headers)->post($baseUrl . '/instance/disconnect', $payload);

                    if (!$response->successful()) {
                        $response = Http::withHeaders($headers)->post($baseUrl . '/instance/logout', $payload);
                    }
                } else if ($provider === 'evolution') {
                    Http::withHeaders($headers)->delete($baseUrl . '/instance/logout/' . $instance);
                }
            } catch (\Throwable $e) {
                Log::error("Erro ao desconectar WhatsApp: " . $e->getMessage());
            }
        }

        return response()->json(['success' => true, 'connected' => false, 'message' => 'Sessão encerrada com sucesso.']);
    }

    private function mapSite(Request $request)
    {
        $url = trim($request->input('website_url'));
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }

        $domain = parse_url($url, PHP_URL_HOST);
        if (!$domain) return response()->json(['success' => false, 'message' => 'URL inválida.']);
        
        $domainStr = str_replace('www.', '', $domain);
        $baseUrl = 'https://' . $domainStr;

        $discoveredLinks = [$url];

        // 1. BUSCA INTELIGENTE VIA SITEMAPS DO WORDPRESS (Captura 100% dos posts e artigos)
        $sitemapUrls = [
            $baseUrl . '/wp-sitemap.xml',
            $baseUrl . '/sitemap.xml',
            $baseUrl . '/sitemap_index.xml'
        ];

        foreach ($sitemapUrls as $sitemapUrl) {
            try {
                $smRes = Http::timeout(8)->get($sitemapUrl);
                if ($smRes->successful()) {
                    $xmlContent = $smRes->body();
                    preg_match_all('/<loc>(https?:\/\/[^<]+)<\/loc>/i', $xmlContent, $locMatches);
                    
                    if (!empty($locMatches[1])) {
                        foreach ($locMatches[1] as $loc) {
                            if (str_contains($loc, '.xml')) {
                                // Se for um índice de sitemaps, lê os sub-sitemaps (ex: posts, páginas)
                                try {
                                    $subRes = Http::timeout(6)->get($loc);
                                    if ($subRes->successful()) {
                                        preg_match_all('/<loc>(https?:\/\/[^<]+)<\/loc>/i', $subRes->body(), $subLocs);
                                        if (!empty($subLocs[1])) {
                                            foreach ($subLocs[1] as $subLoc) {
                                                $discoveredLinks[] = $subLoc;
                                            }
                                        }
                                    }
                                } catch (\Throwable $eSub) {}
                            } else {
                                $discoveredLinks[] = $loc;
                            }
                        }
                    }
                }
            } catch (\Throwable $eSm) {}
        }

        // 2. FALLBACK: NAVEGAÇÃO DE SEGUNDO NÍVEL (Se o sitemap não retornar links suficientes)
        if (count($discoveredLinks) <= 5) {
            $content = $this->fetchContentFromUrl($url);
            if ($content) {
                $discoveredLinks = array_merge($discoveredLinks, $this->extractLinksFromText($content));
                
                // Abre as 5 primeiras subpáginas (como /blog) para extrair os links internos delas
                $subPagesToScan = array_slice($discoveredLinks, 1, 5);
                foreach ($subPagesToScan as $subUrl) {
                    $subContent = $this->fetchContentFromUrl($subUrl);
                    if ($subContent) {
                        $discoveredLinks = array_merge($discoveredLinks, $this->extractLinksFromText($subContent));
                    }
                }
            }
        }

        // 3. FILTRAGEM E TRATAMENTO DOS LINKS ENCONTRADOS
        $cleanLinks = [];
        foreach ($discoveredLinks as $link) {
            $link = explode('?', $link)[0];
            $link = explode('#', $link)[0];
            $link = rtrim($link, '/');
            
            $linkDomain = parse_url($link, PHP_URL_HOST);
            if ($linkDomain) {
                $linkDomainStr = str_replace('www.', '', $linkDomain);
                if (str_ends_with($linkDomainStr, $domainStr)) {
                    if (!preg_match('/\.(jpg|jpeg|png|gif|pdf|zip|rar|mp4|mp3|css|js|svg|webp|doc|docx|xml)$/i', $link)) {
                        if (!in_array($link, $cleanLinks)) {
                            $cleanLinks[] = $link;
                        }
                    }
                }
            }
        }

        if (empty($cleanLinks)) {
            return response()->json(['success' => false, 'message' => 'Falha ao acessar o site inicial. Ele pode estar bloqueando a extração.']);
        }

        $cleanLinks = array_slice($cleanLinks, 0, 150);
        return response()->json(['success' => true, 'urls' => array_values($cleanLinks)]);
    }

    private function extractLinksFromText(string $content): array
    {
        $links = [];
        preg_match_all('/\[[^\]]*\]\((https?:\/\/[^\)]+)\)/i', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $link) {
                $links[] = $link;
            }
        }
        preg_match_all('/https?:\/\/[^\s<>"\'\)\\]]+/i', $content, $rawMatches);
        if (!empty($rawMatches[0])) {
            foreach ($rawMatches[0] as $link) {
                $links[] = $link;
            }
        }
        return $links;
    }

    private function scrapeSingleUrl(Request $request)
    {
        $assistant = Assistant::findOrFail($request->assistant_id);
        $url = trim($request->input('website_url'));

        $content = $this->fetchContentFromUrl($url);
        
        if ($content) {
            $files = is_array($assistant->knowledge_files) ? $assistant->knowledge_files : [];
            
            $exists = false;
            foreach ($files as &$f) {
                if (($f['name'] ?? '') === '🌐 ' . $url) {
                    $f['content'] = $content;
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $files[] = [
                    'name' => '🌐 ' . $url,
                    'path' => null,
                    'content' => $content
                ];
            }
            
            $assistant->forceFill(['knowledge_files' => array_values($files)])->save();
            return response()->json(['success' => true, 'url' => $url]);
        }

        return response()->json(['success' => false, 'message' => 'Sem conteúdo na página.']);
    }

    private function storeDepartment(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        DB::table('departments')->insert([
            'name' => trim($request->name),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return redirect('/?view=equipe')->with('success', 'Departamento criado com sucesso!');
    }

    private function storeAgent(Request $request)
    {
        $departmentId = (int) $request->input('department_id');
        $email = strtolower(trim((string)$request->input('email')));
        $name = trim((string)$request->input('name'));

        if (!$departmentId || !$email || !$name) {
            return redirect('/?view=equipe')->with('error', 'Preencha todos os campos do agente.');
        }

        $duplicate = DB::table('department_members')
            ->where('department_id', $departmentId)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();

        if ($duplicate) {
            return redirect('/?view=equipe')->with('error', "Operação Bloqueada: O e-mail '{$email}' já está cadastrado para o agente '{$duplicate->name}' neste departamento.");
        }

        DB::table('department_members')->insert([
            'department_id' => $departmentId,
            'name' => $name,
            'email' => $email,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect('/?view=equipe')->with('success', "Agente '{$name}' adicionado com sucesso!");
    }

    private function store(Request $request)
    {
        $this->configureTimezone();
        $request->validate(['name' => 'required|string|max:255']);
        $assistant = new Assistant();
        $assistant->forceFill([
            'name' => $request->name,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'context_limit' => 12,
            'system_prompt' => 'Você é um assistente virtual prestativo.',
            'is_active' => true,
        ])->save();
        
        return redirect('/?configure=' . $assistant->id)->with('success', 'Assistente criado com sucesso!');
    }

    private function update(Request $request)
    {
        $this->configureTimezone($request->assistant_id);
        $assistant = Assistant::findOrFail($request->assistant_id);

        $data = $request->only([
            'system_prompt', 'provider', 'model', 'context_limit',
            'whatsapp_provider', 'whatsapp_url', 'whatsapp_instance', 'whatsapp_token', 'whatsapp_verify_token'
        ]);

        if ($request->has('lead_fields')) {
            $fields = $request->input('lead_fields');
            $cleanFields = [];
            if (is_array($fields)) {
                foreach ($fields as $field) {
                    if (!empty($field['name']) && !empty($field['label'])) {
                        $cleanFields[] = $field;
                    }
                }
            }
            $data['lead_fields'] = json_encode($cleanFields, JSON_UNESCAPED_UNICODE);
        } else {
            if ($request->has('system_prompt')) {
                $data['lead_fields'] = json_encode([]);
            }
        }

        foreach (['openai_api_key', 'gemini_api_key', 'anthropic_api_key', 'grok_api_key'] as $keyName) {
            if ($request->filled($keyName)) {
                $data[$keyName] = trim($request->input($keyName));
            }
        }

        $existingFiles = $assistant->knowledge_files;
        if (!is_array($existingFiles)) {
            $existingFiles = [];
        }
        $hasKnowledgeChanges = false;

        if ($request->hasFile('documents')) {
            $uploadedFiles = $request->file('documents');
            if (!is_array($uploadedFiles)) {
                $uploadedFiles = [$uploadedFiles];
            }

            foreach ($uploadedFiles as $file) {
                if ($file && $file->isValid()) {
                    try {
                        $fileName = $file->getClientOriginalName();
                        $path = $file->store('knowledge_base');
                        $fullPath = Storage::path($path);
                        
                        $extractedText = $this->extractTextFromFile($fullPath, $fileName);

                        $existingFiles[] = [
                            'name' => $fileName,
                            'path' => $path,
                            'content' => $extractedText
                        ];
                        $hasKnowledgeChanges = true;
                    } catch (\Throwable $e) {
                        Log::error('Erro no anexo ' . $file->getClientOriginalName() . ': ' . $e->getMessage());
                    }
                }
            }
        }

        if ($hasKnowledgeChanges) {
            $data['knowledge_files'] = array_values($existingFiles);
        }

        $assistant->forceFill($data)->save();

        return redirect('/?configure=' . $assistant->id)->with('success', 'Configurações atualizadas!');
    }

    private function fetchContentFromUrl(string $url): ?string
    {
        try {
            $response = Http::timeout(25)->get('https://r.jina.ai/' . $url);
            if ($response->successful()) {
                return $this->sanitizeText($response->body());
            }
        } catch (\Throwable $e) {
            Log::error("Erro ao importar URL via Jina Reader ({$url}): " . $e->getMessage());
        }
        return null;
    }

    private function toggleActive(Request $request)
    {
        $assistant = Assistant::findOrFail($request->assistant_id);
        $assistant->is_active = !$assistant->is_active;
        $assistant->save();
        return redirect()->back()->with('success', 'Status alterado!');
    }

    private function destroyOrRemoveFile(Request $request)
    {
        $assistant = Assistant::findOrFail($request->assistant_id);

        if ($request->has('file_indexes')) {
            $files = $assistant->knowledge_files;
            if (!is_array($files)) $files = [];
            
            $indices = $request->input('file_indexes', []);
            $indices = array_map('intval', $indices);
            rsort($indices);

            foreach ($indices as $index) {
                if (isset($files[$index])) {
                    if (!empty($files[$index]['path'])) {
                        Storage::delete($files[$index]['path']);
                    }
                    array_splice($files, $index, 1);
                }
            }

            $assistant->forceFill(['knowledge_files' => array_values($files)])->save();
            return redirect('/?configure=' . $assistant->id)->with('success', 'Fontes de conhecimento removidas com sucesso.');
        }

        if ($request->has('file_index')) {
            $files = $assistant->knowledge_files;
            if (!is_array($files)) $files = [];
            $index = (int)$request->file_index;

            if (isset($files[$index])) {
                if (!empty($files[$index]['path'])) {
                    Storage::delete($files[$index]['path']);
                }
                array_splice($files, $index, 1);
                $assistant->forceFill(['knowledge_files' => array_values($files)])->save();
            }
            return redirect('/?configure=' . $assistant->id)->with('success', 'Arquivo/URL removido.');
        }

        $assistant->delete();
        return redirect('/')->with('success', 'Assistente excluído!');
    }

    private function buildSystemPromptWithKnowledge(Assistant $assistant): string
    {
        $tz = $this->getTimezone($assistant->id);
        $now = Carbon::now($tz);

        $diasSemana = [
            'Sunday' => 'Domingo',
            'Monday' => 'Segunda-feira',
            'Tuesday' => 'Terça-feira',
            'Wednesday' => 'Quarta-feira',
            'Thursday' => 'Quinta-feira',
            'Friday' => 'Sexta-feira',
            'Saturday' => 'Sábado'
        ];
        $diaPt = $diasSemana[$now->format('l')] ?? $now->format('l');

        $prompt = "===============================================\n";
        $prompt .= "CONTEXTO TEMPORAL DO SISTEMA (OBRIGATÓRIO):\n";
        $prompt .= "• Data e Hora Atual: " . $now->format('d/m/Y \à\s H:i:s') . " ({$diaPt})\n";
        $prompt .= "• Data Formato ISO: " . $now->format('Y-m-d') . "\n";
        $prompt .= "• Ano Corrente: " . $now->year . "\n";
        $prompt .= "===============================================\n\n";

        // 0. MÓDULO DE PESQUISAS DE OPINIÃO (SE HOUVER PESQUISA ATIVA). A IA NÃO decide mais nada
        // sobre a pesquisa - só marca a própria mensagem de encerramento (que ela já manda de forma
        // 100% confiável) com uma tag simples. Todo o resto (perguntar, esperar resposta, conduzir
        // a pesquisa, decidir a hora certa de mandar a mensagem de encerramento de verdade) é feito
        // em código, interceptando essa tag - pedir pra IA "não mandar a mensagem agora" ou "perguntar
        // antes" falhou repetidamente, então a IA não recebe mais esse tipo de decisão condicional.
        $activeSurveys = Survey::where('assistant_id', $assistant->id)->where('is_active', true)->get();
        if ($activeSurveys->isNotEmpty()) {
            $prompt .= "===============================================\n";
            $prompt .= "MÓDULO DE PESQUISAS DE OPINIÃO:\n";
            $prompt .= "SEMPRE que você enviar a mensagem de encerramento (qualquer uma das duas mensagens fixas da seção \"MENSAGENS DE ENCERRAMENTO\"), inclua TAMBÉM, ao final dela, na mesma mensagem, a tag [ENCERRAMENTO]. Faça isso em TODA mensagem de encerramento, sem exceção e sem pensar em mais nada sobre pesquisa - o sistema cuida de todo o resto automaticamente ao ver essa tag.\n";
            $prompt .= "===============================================\n\n";
        }

        // 1. PROMPT PRINCIPAL
        $prompt .= $assistant->system_prompt ?? '';

        // 2. COLETA DE DADOS (LEAD FIELDS)
        $leadFields = is_array($assistant->lead_fields) 
            ? $assistant->lead_fields 
            : json_decode($assistant->lead_fields ?? '[]', true);

        if (!empty($leadFields) && is_array($leadFields)) {
            $prompt .= "\n\n===============================================\n";
            $prompt .= "DIRETRIZES DE TRIAGEM E COLETA DE DADOS:\n";
            $prompt .= "Obtenha as seguintes informações do usuário ao longo do atendimento:\n";
            foreach ($leadFields as $field) {
                $label = $field['label'] ?? '';
                $name = $field['name'] ?? '';
                if ($label) $prompt .= "• {$label} (Identificador: {$name})\n";
            }
            $prompt .= "===============================================\n";
        }

        // 3. MÓDULO DE AGENDAMENTO (SE ATIVADO)
        $schedulingEnabled = Setting::where('assistant_id', $assistant->id)->where('key', 'scheduling_enabled')->value('value') ?? '1';

        if ($schedulingEnabled === '1') {
            // Só oferece pra IA os departamentos que têm pelo menos um atendente ativo vinculado;
            // um departamento vazio nunca conseguiria ser realmente agendado (allocateAgentRoundRobin
            // sempre retornaria nulo), então não faz sentido a IA oferecê-lo como opção ao cliente.
            $depts = DB::table('departments')
                ->where('departments.assistant_id', $assistant->id)
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('department_user')
                      ->join('users', 'department_user.user_id', '=', 'users.id')
                      ->whereColumn('department_user.department_id', 'departments.id')
                      ->where('users.is_active', 1);
                })
                ->get();
            if ($depts->isNotEmpty()) {
                $deptNames = $depts->pluck('name')->toArray();
                $defaultDeptId = Setting::where('assistant_id', $assistant->id)->where('key', 'default_department_id')->value('value');
                
                $defaultDept = $defaultDeptId ? $depts->firstWhere('id', (int)$defaultDeptId) : $depts->first();

                $businessHoursStart = trim(Setting::where('assistant_id', $assistant->id)->where('key', 'business_hours_start')->value('value') ?? '09:00');
                $businessHoursEnd = trim(Setting::where('assistant_id', $assistant->id)->where('key', 'business_hours_end')->value('value') ?? '17:00');
                $blockWeekends = (Setting::where('assistant_id', $assistant->id)->where('key', 'business_block_weekends')->value('value') ?? '1') === '1';

                $prompt .= "\n\n===============================================\n";
                $prompt .= "MÓDULO DE AGENDAMENTO E VERIFICAÇÃO DE AGENDA:\n";
                $prompt .= "Departamentos disponíveis no sistema:\n";
                foreach ($depts as $d) {
                    $prompt .= "• Setor: {$d->name}\n";
                }
                $prompt .= "\nHORÁRIO DE ATENDIMENTO: reuniões só podem ser marcadas das {$businessHoursStart} às {$businessHoursEnd}" . ($blockWeekends ? ", de segunda a sexta (não atendemos aos sábados e domingos)" : "") . ". Se o cliente pedir um horário fora dessa janela, avise educadamente ANTES de tentar verificar a agenda, e peça outro dia/horário dentro do expediente.\n";

                $holidays = Holiday::where('assistant_id', $assistant->id)->orderBy('date')->get();
                if ($holidays->isNotEmpty()) {
                    $prompt .= "\nFERIADOS (não atendemos nesses dias):\n";
                    foreach ($holidays as $h) {
                        $prompt .= "• " . ($h->is_recurring ? $h->date->format('d/m') . " (todo ano)" : $h->date->format('d/m/Y')) . " - {$h->name}\n";
                    }
                    $prompt .= "Se o cliente pedir um desses dias, avise educadamente ANTES de tentar verificar a agenda, e peça outro dia.\n";
                }

                if ($defaultDept) {
                    $otherDepts = array_values(array_filter($deptNames, fn($n) => $n !== $defaultDept->name));
                    
                    $prompt .= "\nDIRETRIZ DE APRESENTAÇÃO DE SETOR:\n";
                    if (!empty($otherDepts)) {
                        $prompt .= "Ao confirmar o agendamento, diga algo como: \"Entendo que este agendamento seja para o nosso departamento {$defaultDept->name}, mas caso deseje entrar em contato com outros departamentos, eles são: " . implode(', ', $otherDepts) . ".\"\n";
                    } else {
                        $prompt .= "Ao confirmar o agendamento, diga algo como: \"Entendo que este agendamento seja para o nosso departamento {$defaultDept->name}.\"\n";
                    }
                    $prompt .= "É PROIBIDO usar a palavra 'padrão' ao falar com o cliente.\n";
                }

                // INSERE O SEU TEXTO CUSTOMIZADO DAS CONFIGURAÇÕES
                $schedulingCustomPrompt = Setting::where('assistant_id', $assistant->id)->where('key', 'scheduling_custom_prompt')->value('value');
                if (!empty(trim($schedulingCustomPrompt ?? ''))) {
                    $prompt .= "\nINSTRUÇÕES DE FLUXO DE AGENDAMENTO DA EMPRESA:\n" . trim($schedulingCustomPrompt) . "\n";
                }

                // TAGS TÉCNICAS INVISÍVEIS PARA A API
                $prompt .= "\n*** COMANDOS DO SISTEMA (TAGS) - USO OBRIGATÓRIO NOS BASTIDORES ***\n";
                $prompt .= "Para o sistema real verificar a agenda, emita as tags invisíveis no final da mensagem:\n\n";
                
                $prompt .= "1. CHECAGEM DE AGENDA (Imediata após o cliente informar DATA e HORA):\n";
                $prompt .= "Emita no final da mensagem: [VERIFICAR_AGENDA: departamento=\"NOME_DO_SETOR\", data_hora=\"YYYY-MM-DD HH:MM:SS\"]\n\n";

                $prompt .= "2. FINALIZAÇÃO DO AGENDAMENTO (Emitida APENAS após o cliente confirmar que NÃO há mais e-mails/convidados):\n";
                $prompt .= "Emita no final da mensagem: [AGENDAR_REUNIAO: departamento=\"NOME_DO_SETOR\", data_hora_inicio=\"YYYY-MM-DD HH:MM:SS\", email_cliente=\"email@cliente.com\", emails_adicionais=\"email1@...,email2@...\"]\n\n";

                $prompt .= "3. CANCELAMENTO:\n";
                $prompt .= "Emita EXATAMENTE nesse formato (não use variações como 'Cancelar reunião' ou 'CANCELAR'): [CANCELAR_REUNIAO: email_cliente=\"email@cliente.com\", data_hora=\"YYYY-MM-DD HH:MM:SS\"]\n\n";

                $prompt .= "4. REAGENDAMENTO:\n";
                $prompt .= "Emita: [REAGENDAR_REUNIAO: departamento=\"NOME_DO_SETOR\", data_hora_original=\"YYYY-MM-DD HH:MM:SS\", nova_data_hora=\"YYYY-MM-DD HH:MM:SS\", email_cliente=\"email@cliente.com\"]\n";
                $prompt .= "===============================================\n";
            }
        }

        // 4. BASE DE CONHECIMENTO (COM TRAVA BLINDADA DE TOKENS)
        $files = $assistant->knowledge_files;
        if (is_array($files) && !empty($files)) {
            // Documentos enviados manualmente (Word/PDF/etc.) vêm sempre ANTES das páginas de site
            // raspadas automaticamente: com dezenas/centenas de páginas raspadas, elas sozinhas já
            // estouravam a trava de caracteres e os documentos oficiais (endereço, quem somos etc.)
            // nunca chegavam a entrar no prompt.
            usort($files, fn($a, $b) => str_starts_with($a['name'] ?? '', '🌐') <=> str_starts_with($b['name'] ?? '', '🌐'));

            $prompt .= "\n\n### BASE DE CONHECIMENTO OFICIAL DA EMPRESA ###\n";

            $accumulatedChars = 0;
            $maxAllowedKbChars = 120000; // Trava máxima (~30k tokens) para evitar estouro da OpenAI

            foreach ($files as $file) {
                if ($accumulatedChars >= $maxAllowedKbChars) {
                    break;
                }

                $name = $file['name'] ?? 'Arquivo Desconhecido';
                $content = $file['content'] ?? '';
                if (empty($content) && !empty($file['path']) && Storage::exists($file['path'])) {
                    $content = $this->extractTextFromFile(Storage::path($file['path']), $name);
                }

                if (!empty($content)) {
                    $cleanUrl = str_replace('🌐 ', '', $name);
                    $isWebPage = str_starts_with($name, '🌐');
                    // Documentos enviados manualmente têm mais espaço (6.000 caracteres) por serem
                    // poucos e terem sido escolhidos a dedo; páginas de site raspadas automaticamente
                    // continuam limitadas a 1.200 pra não estourar a trava com dezenas delas.
                    $trimmedContent = mb_substr($content, 0, $isWebPage ? 1200 : 6000);
                    $contentLength = mb_strlen($trimmedContent);

                    if ($accumulatedChars + $contentLength > $maxAllowedKbChars) {
                        $trimmedContent = mb_substr($trimmedContent, 0, $maxAllowedKbChars - $accumulatedChars);
                    }

                    if ($isWebPage) {
                        $prompt .= "\n[TIPO: PAGINA_WEB]\n[URL: {$cleanUrl}]\n[CONTEÚDO]:\n" . $trimmedContent . "\n[FIM DE PAGINA_WEB]\n";
                    } else {
                        $prompt .= "\n[TIPO: DOCUMENTO_ARQUIVO]\n[NOME_ARQUIVO: {$name}]\n[CONTEÚDO]:\n" . $trimmedContent . "\n[FIM DE DOCUMENTO_ARQUIVO]\n";
                    }

                    $accumulatedChars += mb_strlen($trimmedContent);
                }
            }
        }

        return $prompt;
    }

    private function extractTextFromFile(string $filePath, string $fileName): string
    {
        if (!file_exists($filePath)) return '';

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $text = '';

        try {
            if (in_array($ext, ['txt', 'md', 'csv', 'json', 'html', 'xml', 'log'])) {
                $text = @file_get_contents($filePath) ?: '';
            } elseif ($ext === 'docx' && class_exists('\ZipArchive')) {
                $zip = new \ZipArchive();
                if ($zip->open($filePath) === true) {
                    if (($index = $zip->locateName('word/document.xml')) !== false) {
                        $data = $zip->getFromIndex($index);
                        $text = trim(strip_tags(str_replace(['</w:p>', '</w:tr>'], "\n", $data)));
                    }
                    $zip->close();
                }
            } elseif ($ext === 'pdf') {
                if (class_exists('\Smalot\PdfParser\Parser')) {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($filePath);
                    $text = $pdf->getText();
                } else {
                    Log::error("A biblioteca smalot/pdfparser não está instalada.");
                    $text = "ERRO INTERNO: Falha na extração. Leitor de PDF não instalado.";
                }
            }
        } catch (\Throwable $e) {
            Log::error("Erro na leitura de {$fileName}: " . $e->getMessage());
            $text = "Erro ao extrair conteúdo deste documento.";
        }

        return $this->sanitizeText($text);
    }

    private function sanitizeText($text): string
    {
        if (!is_string($text) || empty($text)) return '';
        $clean = @mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean);
        return trim(mb_substr($clean, 0, 8000));
    }

    public function showChatWidget(Request $request, $id)
    {
        $this->configureTimezone($id);
        $assistant = Assistant::findOrFail($id);
        return view('assistants.chat', compact('assistant'));
    }

    public function chatWidgetSend(Request $request, $id)
    {
        $request->merge(['assistant_id' => $id]);
        return $this->chat($request);
    }

    private function chat(Request $request)
    {
        $assistantId = $request->input('assistant_id');
        $this->configureTimezone($assistantId);
        try {
            $assistant = Assistant::find($assistantId);
            if (!$assistant) {
                return response()->json(['reply' => '⚠️ Assistente não encontrado.']);
            }

            $userMessage = (string)$request->input('message');
            $history = $request->input('history', []);
            if (!is_array($history)) $history = [];
            $history = array_slice($history, -12);

            $systemPrompt = $this->buildSystemPromptWithKnowledge($assistant);
            $response = $this->callAiApi($assistant, $systemPrompt, $userMessage, $history);

            return response()->json(['reply' => $response]);
        } catch (\Throwable $e) {
            return response()->json(['reply' => '⚠️ Erro no Chat: ' . $e->getMessage()], 200);
        }
    }

    /**
     * Detecta (por heurística simples de palavras/caracteres comuns, sem depender de nenhuma API
     * externa) se o texto está em português, inglês ou espanhol, e devolve a voz + languageCode do
     * Google TTS correspondente - a IA pode responder em qualquer idioma (regra de acompanhamento
     * dinâmico), então a voz do áudio precisa acompanhar, senão o TTS lê palavras estrangeiras com
     * fonética de português (soletrando acentos, travessões, etc.).
     */
    private function pickTtsVoiceForText(string $text): array
    {
        $lower = mb_strtolower($text);

        $ptScore = 0;
        $enScore = 0;
        $esScore = 0;

        if (preg_match('/[¿¡]/u', $lower)) $esScore += 3;
        if (preg_match('/[ãõ]/u', $lower)) $ptScore += 3;

        foreach (['você', 'não', 'está', 'muito', 'obrigad', 'também', 'então', 'aqui', 'para você'] as $w) {
            if (str_contains($lower, $w)) $ptScore++;
        }
        foreach ([' the ', ' you ', 'please', 'thank', 'hello ', ' what ', ' with ', ' have ', ' this ', ' that '] as $w) {
            if (str_contains($lower, $w)) $enScore++;
        }
        foreach (['usted', 'gracias', 'por favor', 'hola', 'está', 'más', 'qué', 'cómo', 'aquí', 'entonces'] as $w) {
            if (str_contains($lower, $w)) $esScore++;
        }

        if ($esScore > $ptScore && $esScore > $enScore) {
            return ['es-US-Wavenet-A', 'es-US'];
        }
        if ($enScore > $ptScore && $enScore > $esScore) {
            return ['en-US-Wavenet-F', 'en-US'];
        }
        return ['pt-BR-Chirp3-HD-Erinome', 'pt-BR'];
    }

    private function formatTextForWhatsapp(string $text): string
    {
        if (empty($text)) return '';

        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($matches) {
            $label = trim($matches[1]);
            $url = trim($matches[2]);
            return "{$label}:\n👉 {$url}";
        }, $text);

        $text = preg_replace('/^#{1,6}\s*(.+)$/m', '*$1*', $text);
        $text = preg_replace('/^\s*[\*\-]\s+/m', '• ', $text);
        $text = preg_replace('/^\*(\d+\.)\s*\*/m', '$1 *', $text);
        $text = preg_replace('/^\*(\d+\.)\s*/m', '$1 ', $text);
        $text = preg_replace('/\*\*\*(.*?)\*\*\*/s', '*$1*', $text);
        $text = preg_replace('/\*\*(.*?)\*\*/s', '*$1*', $text);
        $text = str_replace('**', '*', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    private function detectExtensionFromBytes(string $bytes): ?string
    {
        if (strlen($bytes) < 12) return null;

        $header = substr($bytes, 0, 16);

        if (str_starts_with($header, '%PDF')) return 'pdf';
        if (str_starts_with($header, "\x89PNG")) return 'png';
        if (str_starts_with($header, "\xFF\xD8\xFF")) return 'jpg';
        if (str_starts_with($header, "GIF8")) return 'gif';
        if (str_starts_with($header, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') return 'webp';
        if (substr($bytes, 4, 4) === 'ftyp') return 'mp4';
        if (str_starts_with($header, 'OggS')) return 'ogg';
        if (str_starts_with($header, 'ID3') || str_starts_with($header, "\xFF\xFB") || str_starts_with($header, "\xFF\xF3")) return 'mp3';
        if (str_starts_with($header, 'RIFF') && substr($bytes, 8, 4) === 'WAVE') return 'wav';
        if (str_starts_with($header, "PK\x03\x04")) return 'docx';

        return null;
    }

    private function guessExtension(?string $mime): ?string
    {
        if (empty($mime)) return null;
        $mime = strtolower(trim(explode(';', $mime)[0]));
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/csv' => 'csv',
            'text/plain' => 'txt',
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/wav' => 'wav',
            'audio/mp4' => 'mp4',
            'video/mp4' => 'mp4',
            'video/avi' => 'avi',
            'video/quicktime' => 'mov',
        ];
        return $map[$mime] ?? null;
    }

    private function extractMediaBytesFromResponse($response): ?string
    {
        if (!$response || !$response->successful()) {
            return null;
        }

        $json = $response->json();

        if (is_array($json)) {
            $b64 = $json['base64'] 
                ?? $json['data']['base64'] 
                ?? $json['media'] 
                ?? $json['data'] 
                ?? $json['result']
                ?? null;

            if (is_string($b64) && strlen($b64) > 100 && !str_starts_with($b64, 'http')) {
                $cleanB64 = preg_replace('#^data:[^;]+;base64,#i', '', $b64);
                $decoded = base64_decode($cleanB64);
                if ($decoded && strlen($decoded) > 100) {
                    return $decoded;
                }
            }

            $returnedUrl = $json['fileURL']
                ?? $json['fileUrl']
                ?? $json['file_url']
                ?? $json['url'] 
                ?? $json['mediaUrl'] 
                ?? $json['media_url']
                ?? $json['downloadUrl']
                ?? $json['data']['fileURL']
                ?? $json['data']['fileUrl']
                ?? $json['data']['url'] 
                ?? null;

            if (is_string($returnedUrl) && str_starts_with($returnedUrl, 'http')) {
                try {
                    $dl = Http::timeout(25)->get($returnedUrl);
                    if ($dl->successful() && strlen($dl->body()) > 200) {
                        return $dl->body();
                    }
                } catch (\Throwable $eUrl) {
                    Log::error("Erro no download da fileURL de mídia: " . $eUrl->getMessage());
                }
            }
        }

        $body = $response->body();
        if (is_string($body) && strlen($body) > 100 && !str_starts_with(trim($body), '{') && !str_starts_with(trim($body), '<')) {
            return $body;
        }

        return null;
    }

    private function extractAudioBytesFromResponse($response): ?string
    {
        return $this->extractMediaBytesFromResponse($response);
    }

    private function sendToOmni(string $message, string $pushName, string $type, string $phone, $assistantId = null)
    {
        try {
            if (!$assistantId) {
                Log::info("Integração Omni ignorada: ID do assistente não informado.");
                return null;
            }

            $webhookBaseUrl = Setting::where('assistant_id', $assistantId)->where('key', 'omni_webhook_url')->value('value');

            if (empty(trim($webhookBaseUrl ?? ''))) {
                Log::info("Integração Omni ignorada para o assistente #{$assistantId}: URL do webhook não configurada.");
                return null;
            }

            $url = trim($webhookBaseUrl);
            if (!str_contains($url, 'webhook_multiagents.php')) {
                $url = rtrim($url, '/') . '/webhook_multiagents.php';
            }

            $remoteJidAlt = str_contains($phone, '@') ? $phone : ($phone . '@s.whatsapp.net');

            $payload = [
                'conversation' => $message,
                'pushName'     => $pushName ?: 'Cliente',
                'type'         => $type,
                'remoteJidAlt' => $remoteJidAlt,
            ];

            $response = Http::withoutVerifying()->timeout(15)->post($url, $payload);
            
            if ($response->successful()) {
                $json = $response->json();
                Log::info("Registro Omni API Direta ({$type}): ", is_array($json) ? $json : []);
                return $json;
            } else {
                Log::error("Erro na integração Omni para {$url}: Status HTTP " . $response->status());
                return null;
            }
        } catch (\Throwable $e) {
            Log::error("Erro ao registrar conversa no Omni API Direta ({$type}): " . $e->getMessage());
        }
        return null;
    }

    public function webhook(Request $request, $id)
    {
        $this->configureTimezone($id);
        $this->ensureWebhookLogTableExists();
        $this->ensureChatMessagesTableExists();
        $this->ensureAppointmentsTableExists();

        try {
            $assistant = Assistant::find($id);
            if (!$assistant || !$assistant->is_active) {
                return response()->json(['status' => 'ignored']);
            }

            $rawSender = $request->input('message.sender_pn')
                ?? $request->input('message.chatid')
                ?? $request->input('chat.phone')
                ?? $request->input('chat.wa_chatid')
                ?? $request->input('data.key.remoteJid') 
                ?? $request->input('key.remoteJid') 
                ?? $request->input('phone')
                ?? $request->input('from')
                ?? $request->input('sender') 
                ?? 'desconhecido';

            $sender = is_array($rawSender) ? ($rawSender['user'] ?? json_encode($rawSender)) : (string)$rawSender;
            if (str_contains($sender, '@')) {
                $sender = explode('@', $sender)[0];
            }

            $cleanSender = preg_replace('/[^0-9]/', '', $sender);

            // 🛑 CONTATO "LID": o WhatsApp vem migrando cada vez mais contatos (principalmente fora
            // do Brasil) pra um identificador interno opaco ("@lid") em vez do número de telefone
            // real - quando isso acontece, message.sender_pn some (fica null) e só sobra esse LID.
            // Enviar de volta só os dígitos como se fosse um número de verdade não funciona (não é
            // discável); é preciso mandar pro JID completo, com o sufixo @lid, pra UazAPI/Baileys
            // resolver corretamente. $sendTarget é o que deve ser usado em qualquer envio de
            // resposta pra esse contato; $cleanSender continua sendo só os dígitos, usado como
            // identificador estável no banco (chat_messages, webhook_logs, agendamentos etc.).
            $isLidSender = is_string($rawSender) && str_contains($rawSender, '@lid');
            $sendTarget = $isLidSender ? ($cleanSender . '@lid') : $cleanSender;

            if ($request->input('message.fromMe') === true || $request->input('data.key.fromMe') === true || $request->input('key.fromMe') === true) {
                return response()->json(['status' => 'ignored_from_me']);
            }

            // Protege contra reentrega do mesmo webhook (retry do provedor por timeout/instabilidade):
            // sem isso, a mesma mensagem podia gerar duas respostas de IA e ate duplicar agendamento.
            $messageId = $request->input('message.id')
                ?? $request->input('message.messageid')
                ?? $request->input('data.key.id')
                ?? $request->input('key.id')
                ?? null;

            if ($messageId) {
                $dedupKey = 'webhook_msg_' . $id . '_' . md5((string) $messageId);
                if (!Cache::add($dedupKey, true, now()->addHours(24))) {
                    Log::info("Webhook duplicado ignorado (mensagem já processada): {$messageId}");
                    return response()->json(['status' => 'duplicate_ignored']);
                }
            }

            $audioService = new \App\Services\AudioService();

            $msgType = strtolower(
                $request->input('message.mediaType')
                ?? $request->input('message.messageType')
                ?? $request->input('message.type')
                ?? $request->input('type')
                ?? ''
            );

            $mediaUrl = $request->input('message.content.URL')
                ?? $request->input('message.content.url')
                ?? $request->input('message.media_url')
                ?? $request->input('message.url')
                ?? null;

            $isAudioMessage = in_array($msgType, ['ptt', 'audio', 'audiomessage', 'voice']) 
                || (!empty($mediaUrl) && (str_contains($mediaUrl, '.og') || str_contains($mediaUrl, '.mp3') || str_contains($mediaUrl, 'audio')));

            $isMediaMessage = $isAudioMessage 
                || in_array($msgType, ['image', 'video', 'document', 'sticker', 'imagemessage', 'videomessage', 'documentmessage', 'documentwithcaptionmessage']) 
                || (!empty($mediaUrl) && str_contains($mediaUrl, 'http'));

            $rawMessage = $request->input('message.content')
                ?? $request->input('message.text')
                ?? $request->input('message.caption')
                ?? $request->input('data.message.conversation')
                ?? $request->input('data.message.extendedTextMessage.text')
                ?? $request->input('message.conversation')
                ?? $request->input('message.extendedTextMessage.text')
                ?? $request->input('text.message')
                ?? $request->input('text')
                ?? $request->input('body')
                ?? '';

            // Resposta de lista interativa (ListResponseMessage): a UazAPI manda o item escolhido
            // dentro de content.title (ex: "Agendar uma Reunião"), não em content.text/body.
            $userMessage = is_array($rawMessage)
                ? ($rawMessage['text'] ?? $rawMessage['body'] ?? $rawMessage['title'] ?? '')
                : (string)$rawMessage;

            $mediaErrorDetails = null;
            $mediaSaved = false;
            $mediaUrlPublic = null;
            $mediaExt = '';

            if ($isMediaMessage) {
                try {
                    $maxFileSizeMb = (int)(Setting::where('assistant_id', $assistant->id)->where('key', 'max_file_size_mb')->value('value') ?? 4);
                    $maxFileSizeBytes = $maxFileSizeMb * 1024 * 1024;
                    
                    $allowedExtRaw = Setting::where('assistant_id', $assistant->id)->where('key', 'allowed_extensions')->value('value');
                    $allowedExtensions = $allowedExtRaw ? json_decode($allowedExtRaw, true) : [];
                    if (!is_array($allowedExtensions)) $allowedExtensions = [];

                    $token = trim($assistant->whatsapp_token ?? '');
                    $baseUrl = rtrim($assistant->whatsapp_url ?? '', '/');
                    $msgPayload = $request->input('message') ?? [];
                    $msgId = $msgPayload['messageid'] ?? $msgPayload['id'] ?? null;

                    $mediaBytes = null;

                    $rawB64 = $msgPayload['base64'] 
                        ?? $msgPayload['content']['base64'] 
                        ?? $request->input('base64') 
                        ?? null;

                    if (is_string($rawB64) && strlen($rawB64) > 100) {
                        $cleanB64 = preg_replace('#^data:[^;]+;base64,#i', '', $rawB64);
                        $decodedB64 = base64_decode($cleanB64);
                        if ($decodedB64 && strlen($decodedB64) > 100) {
                            $mediaBytes = $decodedB64;
                        }
                    }

                    if (!$mediaBytes && $baseUrl && $token) {
                        $headers = [
                            'token' => $token,
                            'Client-Token' => $token,
                            'client-token' => $token,
                            'apikey' => $token,
                            'Content-Type' => 'application/json'
                        ];
                        $url = $baseUrl . '/message/download?token=' . urlencode($token);
                        $payloads = [
                            ['token' => $token, 'id' => $msgId, 'messageid' => $msgId, 'message' => $msgPayload],
                            ['token' => $token, 'id' => $msgId]
                        ];

                        foreach ($payloads as $payload) {
                            if (empty($payload['id'])) continue;
                            try {
                                $res = Http::withHeaders($headers)->timeout(25)->post($url, $payload);
                                if ($res->successful()) {
                                    $bytes = $this->extractMediaBytesFromResponse($res);
                                    if ($bytes) {
                                        $mediaBytes = $bytes;
                                        break;
                                    }
                                }
                            } catch (\Throwable $eDl) {}
                        }
                    }

                    if (!$mediaBytes && !empty($mediaUrl) && str_starts_with($mediaUrl, 'http')) {
                        try {
                            $dl = Http::timeout(25)->get($mediaUrl);
                            if ($dl->successful() && strlen($dl->body()) > 100) {
                                $mediaBytes = $dl->body();
                            }
                        } catch (\Throwable $eUrl) {
                            Log::error("Erro no download direto da mediaUrl: " . $eUrl->getMessage());
                        }
                    }

                    if ($mediaBytes && strlen($mediaBytes) > 100) {
                        $fileSize = strlen($mediaBytes);
                        
                        $mimetype = $msgPayload['mimetype'] 
                            ?? $msgPayload['documentMessage']['mimetype'] 
                            ?? $msgPayload['videoMessage']['mimetype'] 
                            ?? $msgPayload['imageMessage']['mimetype'] 
                            ?? $msgPayload['audioMessage']['mimetype'] 
                            ?? $request->input('message.mimetype') 
                            ?? $request->input('mimetype') 
                            ?? '';

                        $fileName = $msgPayload['fileName'] 
                            ?? $msgPayload['documentMessage']['fileName'] 
                            ?? $msgPayload['title'] 
                            ?? $request->input('message.fileName') 
                            ?? '';
                        
                        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                        if (!$ext || $ext === 'bin') {
                            $ext = $this->detectExtensionFromBytes($mediaBytes);
                        }

                        if (!$ext) {
                            $ext = $this->guessExtension($mimetype);
                        }

                        if (!$ext) {
                            $mediaTypeClean = strtolower($msgType);
                            if (str_contains($mediaTypeClean, 'video')) $ext = 'mp4';
                            elseif (str_contains($mediaTypeClean, 'image')) $ext = 'jpg';
                            elseif (str_contains($mediaTypeClean, 'document')) $ext = 'pdf';
                            elseif (str_contains($mediaTypeClean, 'audio') || str_contains($mediaTypeClean, 'voice') || str_contains($mediaTypeClean, 'ptt')) $ext = 'ogg';
                        }

                        if (!$ext) $ext = $isAudioMessage ? 'ogg' : 'bin';
                        $mediaExt = $ext;

                        if ($fileSize > $maxFileSizeBytes) {
                            $mediaErrorDetails = "O anexo excede o limite permitido de {$maxFileSizeMb}MB.";
                        } elseif (!empty($allowedExtensions) && !in_array($ext, $allowedExtensions)) {
                            $mediaErrorDetails = "Tipo de arquivo (.{$ext}) não permitido pelo administrador.";
                        } else {
                            $folderPath = 'uploads/assistants/' . $assistant->id;
                            $fullDir = storage_path('app/public/' . $folderPath);

                            if (!file_exists($fullDir)) {
                                @mkdir($fullDir, 0777, true);
                            }

                            $newFileName = time() . '_' . rand(1000, 9999) . '.' . $ext;
                            $relativePath = $folderPath . '/' . $newFileName;
                            $fullFilePath = $fullDir . '/' . $newFileName;

                            $writtenSuccess = Storage::disk('public')->put($relativePath, $mediaBytes);
                            if (!$writtenSuccess || !file_exists($fullFilePath)) {
                                @file_put_contents($fullFilePath, $mediaBytes);
                            }

                            if (file_exists($fullFilePath) && filesize($fullFilePath) > 100) {
                                $mediaUrlPublic = $request->getSchemeAndHttpHost() . '/files/' . $relativePath;
                                $savePath = $fullFilePath;
                                $mediaSaved = true;

                                if ($isAudioMessage) {
                                    $transcriptionKey = trim($assistant->openai_api_key ?? '');
                                    if ($transcriptionKey) {
                                        $transcribedText = $audioService->transcribeAudio($savePath, $transcriptionKey, 'openai');
                                        if (!empty($transcribedText)) {
                                            $userMessage = $transcribedText;
                                        } else {
                                            $userMessage = "[Áudio sem transcrição]";
                                        }
                                    } else {
                                        $userMessage = "[Áudio recebido]";
                                    }
                                }
                            } else {
                                $mediaErrorDetails = "Falha de gravação do arquivo no disco do servidor.";
                                Log::error("Erro ao gravar arquivo em: {$fullFilePath}");
                            }
                        }
                    } else {
                        $mediaErrorDetails = "Falha ao baixar anexo da API do WhatsApp.";
                    }
                } catch (\Throwable $e) {
                    $mediaErrorDetails = "Exceção ao processar anexo: " . $e->getMessage();
                    Log::error("Erro no anexo: " . $e->getMessage());
                }

                if ($mediaErrorDetails && !$mediaSaved) {
                    $nowFormatted = now()->setTimezone($this->getTimezone($assistant->id))->toDateTimeString();
                    $rejectMsg = "⚠️ " . $mediaErrorDetails;
                    
                    $waResult = $this->sendWhatsappMessage($assistant, $sendTarget, $rejectMsg);

                    DB::table('webhook_logs')->insert([
                        'assistant_id' => $assistant->id,
                        'sender' => substr($sender, 0, 255),
                        'user_message' => '[Anexo Rejeitado]',
                        'ai_reply' => 'Rejeitado: ' . $mediaErrorDetails,
                        'wa_send_result' => json_encode($waResult, JSON_INVALID_UTF8_IGNORE),
                        'raw_snippet' => json_encode($request->all(), JSON_INVALID_UTF8_IGNORE),
                        'timestamp' => $nowFormatted,
                        'created_at' => $nowFormatted,
                        'updated_at' => $nowFormatted,
                    ]);

                    return response()->json(['status' => 'media_rejected', 'reason' => $mediaErrorDetails]);
                }

                if ($mediaSaved) {
                    $caption = trim($userMessage);
                    if ($isAudioMessage) {
                        $userMessage = (!empty($caption) && !str_starts_with($caption, '[')) ? "Mensagem de Voz: \"" . $caption . "\"" : $caption;
                        $userMessage .= "\n🔊 Link do Áudio: " . $mediaUrlPublic;
                    } else {
                        $userMessage = "📎 Anexo Recebido (.{$mediaExt})\n🔗 Link: " . $mediaUrlPublic;
                        if (!empty($caption)) {
                            $userMessage .= "\n📝 Legenda do Usuário: \"" . $caption . "\"";
                        }
                    }
                }
            }

            $nowFormatted = now()->setTimezone($this->getTimezone($assistant->id))->toDateTimeString();

            if (empty(trim($userMessage))) {
                DB::table('webhook_logs')->insert([
                    'assistant_id' => $assistant->id,
                    'sender' => substr($sender, 0, 255),
                    'user_message' => $isMediaMessage ? '[Mídia sem conteúdo]' : '[Sem Texto]',
                    'ai_reply' => 'Ignorado',
                    'wa_send_result' => json_encode(['info' => 'Nenhuma resposta enviada']),
                    'raw_snippet' => json_encode($request->all(), JSON_INVALID_UTF8_IGNORE),
                    'timestamp' => $nowFormatted,
                    'created_at' => $nowFormatted,
                    'updated_at' => $nowFormatted,
                ]);
                return response()->json(['status' => 'no_message']);
            }

            $rawPushName = $request->input('message.senderName')
                ?? $request->input('senderName')
                ?? $request->input('pushName')
                ?? $request->input('data.pushName')
                ?? '';

            $clientName = trim((string)$rawPushName);
            if (empty($clientName) || preg_match('/^[0-9]+$/', $clientName)) {
                $displayName = 'Cliente';
            } else {
                $displayName = $clientName;
            }

            // 🛑 PESQUISA (AGUARDANDO CONFIRMAÇÃO OU EM ANDAMENTO): se esse número acabou de ser
            // convidado a responder uma pesquisa, ou já está respondendo uma, essa mensagem é a
            // resposta atual - conduz a sequência 100% em código, sem passar pela IA (a mesma razão
            // de "3 - voltar ao menu": é navegação/estado determinístico, não conversa livre).
            $pendingSurveyResponse = SurveyResponse::where('assistant_id', $assistant->id)
                ->where('phone_number', $cleanSender)
                ->whereIn('status', ['awaiting_confirmation', 'in_progress'])
                ->latest('id')
                ->first();

            if ($pendingSurveyResponse) {
                if ($pendingSurveyResponse->status === 'awaiting_confirmation') {
                    $this->handleSurveyConfirmation($assistant, $pendingSurveyResponse, $userMessage);
                } else {
                    $this->handleSurveyAnswer($assistant, $pendingSurveyResponse, $userMessage);
                }

                DB::table('chat_messages')->insert([
                    ['assistant_id' => $assistant->id, 'phone_number' => $cleanSender, 'protocol' => null, 'role' => 'user', 'content' => $userMessage, 'created_at' => $nowFormatted, 'updated_at' => $nowFormatted],
                    ['assistant_id' => $assistant->id, 'phone_number' => $cleanSender, 'protocol' => null, 'role' => 'assistant', 'content' => '[Resposta de pesquisa registrada]', 'created_at' => $nowFormatted, 'updated_at' => $nowFormatted],
                ]);

                DB::table('webhook_logs')->insert([
                    'assistant_id' => $assistant->id,
                    'sender' => substr($sender, 0, 255),
                    'user_message' => $userMessage,
                    'ai_reply' => '[PESQUISA] resposta registrada',
                    'wa_send_result' => json_encode(['success' => true], JSON_INVALID_UTF8_IGNORE),
                    'raw_snippet' => json_encode($request->all(), JSON_INVALID_UTF8_IGNORE),
                    'timestamp' => $nowFormatted,
                    'created_at' => $nowFormatted,
                    'updated_at' => $nowFormatted,
                ]);

                return response()->json(['status' => 'success']);
            }

            $omniInputRes = $this->sendToOmni($userMessage, $displayName !== 'Cliente' ? $displayName : $cleanSender, 'input', $sendTarget, $assistant->id);

            $protocolo = null;
            $isNewTicket = false;
            $omniUserName = null;

            if (!empty($omniInputRes) && is_array($omniInputRes)) {
                $protocolo    = $omniInputRes['protocolo'] ?? $omniInputRes['ticket_number'] ?? $omniInputRes['number'] ?? null;
                $isNewTicket  = !empty($omniInputRes['is_new_ticket']);
                $omniUserName = $omniInputRes['user_name'] ?? null;
            } elseif (is_string($omniInputRes)) {
                $dataArr      = json_decode($omniInputRes, true);
                $protocolo    = $dataArr['protocolo'] ?? null;
                $isNewTicket  = !empty($dataArr['is_new_ticket']);
                $omniUserName = $dataArr['user_name'] ?? null;
            }

            // LIMPEZA DOS ZEROS À ESQUERDA DO PROTOCOLO
            $protocoloClean = null;
            if (!empty($protocolo)) {
                $protocoloClean = ltrim((string)$protocolo, '0');
                if ($protocoloClean === '') $protocoloClean = '0';
            }

            if (!empty($omniUserName) && !preg_match('/^[0-9]+$/', trim($omniUserName))) {
                $displayName = trim($omniUserName);
            }

            // ==============================================================
            // LÓGICA DE RESET DE SESSÃO (DINÂMICA COM E SEM OMNI)
            // ==============================================================
            $sessionTimeoutMinutes = (int) (Setting::where('assistant_id', $assistant->id)->where('key', 'session_timeout_minutes')->value('value') ?? 240);

            $lastMessageRow = DB::table('chat_messages')
                ->where('assistant_id', $assistant->id)
                ->where('phone_number', $cleanSender)
                ->orderBy('id', 'desc')
                ->first();

            $shouldResetSession = false;

            if ($lastMessageRow) {
                if ($isNewTicket) {
                    $shouldResetSession = true;
                }
                elseif (!empty($protocoloClean) && !empty($lastMessageRow->protocol) && $protocoloClean !== $lastMessageRow->protocol) {
                    $shouldResetSession = true;
                }
                elseif ($sessionTimeoutMinutes > 0 && Carbon::parse($lastMessageRow->created_at)->diffInMinutes(now()->setTimezone($this->getTimezone($assistant->id))) >= $sessionTimeoutMinutes) {
                    $shouldResetSession = true;
                }
            }

            if ($shouldResetSession) {
                DB::table('chat_messages')
                    ->where('assistant_id', $assistant->id)
                    ->where('phone_number', $cleanSender)
                    ->delete();
                $lastMessageRow = null;
            }

            $contextLimit = (int) ($assistant->context_limit ?? 12);

            $historyRecords = DB::table('chat_messages')
                ->where('assistant_id', $assistant->id)
                ->where('phone_number', $cleanSender)
                ->orderBy('id', 'desc')
                ->limit($contextLimit)
                ->get()
                ->reverse();

            $history = [];
            $assistantMsgCount = 0;

            foreach ($historyRecords as $msg) {
                if (isset($msg->role) && $msg->role === 'assistant') {
                    $assistantMsgCount++;
                }
                $history[] = [
                    'role' => $msg->role,
                    'content' => $msg->content
                ];
            }

            // DADOS DO ATENDIMENTO E PROTOCOLO APENAS SE FOR A PRIMEIRA MENSAGEM DO ASSISTENTE
            $isFirstMessage = ($assistantMsgCount === 0);

            $systemPrompt = $this->buildSystemPromptWithKnowledge($assistant);

            $systemPrompt .= "\n\n===============================================\n";
            $systemPrompt .= "DADOS DO ATENDIMENTO ATUAL:\n";

            if ($isFirstMessage) {
                $systemPrompt .= "• Nome do Cliente: " . $displayName . "\n";
                if (!empty($protocoloClean)) {
                    $systemPrompt .= "INSTRUÇÃO OBRIGATÓRIA DE SAUDAÇÃO: Esta é a PRIMEIRA MENSAGEM do atendimento. Você DEVE saudar o cliente pelo nome (" . $displayName . "). É ESTRITAMENTE PROIBIDO escrever, inventar ou citar qualquer número de protocolo na sua resposta, pois o sistema adicionará o protocolo automaticamente no início da mensagem.\n";
                } else {
                    $systemPrompt .= "INSTRUÇÃO OBRIGATÓRIA DE SAUDAÇÃO: Esta é a PRIMEIRA MENSAGEM do atendimento. Você DEVE obrigatoriamente saudar o cliente pelo nome (" . $displayName . ").\n";
                }
            } else {
                $systemPrompt .= "INSTRUÇÃO OBRIGATÓRIA: A conversa já está em andamento. NÃO repita a saudação de boas-vindas e É ESTRITAMENTE PROIBIDO enviar ou mencionar o número do protocolo.\n";
            }
            $systemPrompt .= "===============================================\n";

            // ATIVA O 'DIGITANDO...' ENQUANTO A IA PROCESSA A RESPOSTA
            $this->sendWhatsappPresence($assistant, $sendTarget, 'composing');

            $aiReply = $this->callAiApi($assistant, $systemPrompt, $userMessage, $history);

            if (!empty($displayName) && $displayName !== 'Cliente') {
                $aiReply = str_replace(['#NOME#', '[NOME]', '[Nome do Cliente]'], $displayName, $aiReply);
            } else {
                $aiReply = str_replace(['#NOME#', '[NOME]', '[Nome do Cliente]'], 'Cliente', $aiReply);
            }

            if ($isFirstMessage) {
                if (!empty($protocoloClean)) {
                    $aiReply = str_replace(['#PROTOCOLO#', '[PROTOCOLO]', '[Número do Protocolo]'], $protocoloClean, $aiReply);
                    $aiReply = preg_replace('/Seu protocolo (de atendimento )?[é|é]:?\s*\d*/i', '', $aiReply);

                    if (strpos($aiReply, $protocoloClean) === false) {
                        $aiReply = "🎫 *Protocolo:* " . $protocoloClean . "\n\n" . trim($aiReply);
                    }
                }
            } else {
                $aiReply = str_replace(['#PROTOCOLO#', '[PROTOCOLO]', '[Número do Protocolo]'], '', $aiReply);
                if (!empty($protocolo)) {
                    $aiReply = str_replace($protocolo, '', $aiReply);
                }
                if (!empty($protocoloClean)) {
                    $aiReply = str_replace($protocoloClean, '', $aiReply);
                }
                $aiReply = preg_replace('/🎫\s*\*?Protocolo:\*?\s*\d*\n*/i', '', $aiReply);
                $aiReply = preg_replace('/\*?Protocolo:\*?\s*\d*\n*/i', '', $aiReply);
                $aiReply = preg_replace('/Seu protocolo (de atendimento )?[é|é]:?\s*\d*/i', '', $aiReply);
            }

            $aiReply = str_replace('..', '.', $aiReply);

            // 🛑 DETECÇÃO BLINDADA: Identifica tags de agendamento (ignorando maiúsculas, espaços e quebras de linha)
            $hasSchedulingTag = preg_match('/\[(VERIFICAR_AGENDA|AGENDAR_REUNIAO|CANCELAR_REUNIAO|REAGENDAR_REUNIAO)\s*:/is', $aiReply);

            // PROCESSA TAGS DE AGENDAMENTO
            if (method_exists($this, 'processAppointmentTag')) {
                $aiReply = $this->processAppointmentTag($assistant, $aiReply, $displayName, $cleanSender);
            }

            // 🛑 FORÇA TEXTO: Se houver tag de agendamento OU se a resposta contiver termos de confirmação da reunião
            if ($hasSchedulingTag || preg_match('/(REUNIÃO CONFIRMADA|REUNIÃO CANCELADA|REUNIÃO REAGENDADA|está \*?LIVRE\*?|Google Meet|Atendente:)/i', $aiReply)) {
                $isAudioMessage = false;
            }

            // 🛑 MENU PRINCIPAL: a IA emite [MENU_PRINCIPAL] em vez de escrever a lista numerada -
            // o sistema envia o menu de verdade como lista interativa do WhatsApp (não faz sentido em áudio).
            $hasMainMenuTag = (bool) preg_match('/\[MENU_PRINCIPAL\]/i', $aiReply);
            if ($hasMainMenuTag) {
                $aiReply = trim(preg_replace('/\[MENU_PRINCIPAL\]/i', '', $aiReply));
                $isAudioMessage = false;
            }

            // 🛑 ENCERRAMENTO: a IA sempre marca sua mensagem de encerramento com [ENCERRAMENTO] (ação
            // única e já confiável, igual o [MENU_PRINCIPAL]). O sistema intercepta: se houver pesquisa
            // ativa ainda não oferecida a esse número, DESCARTA o texto de encerramento da IA (ele só
            // serviu de sinal) e manda no lugar a pergunta de oferta - só quando o cliente responder é
            // que a mensagem de encerramento de verdade é gerada (ver handleSurveyConfirmation /
            // sendAiGeneratedClosing). Pedir pra IA decidir "não mandar a mensagem agora" ou "perguntar
            // antes" falhou repetidamente, então ela não recebe mais esse tipo de decisão condicional.
            $offeredSurvey = null;
            if (preg_match('/\[ENCERRAMENTO\]/i', $aiReply)) {
                $aiReply = trim(preg_replace('/\[ENCERRAMENTO\]/i', '', $aiReply));

                $candidateSurvey = Survey::where('assistant_id', $assistant->id)->where('is_active', true)->first();
                if ($candidateSurvey) {
                    // Só bloqueia se já houver uma pesquisa PENDENTE (aguardando resposta ou em
                    // andamento) pra esse número - uma vez concluída ou recusada, o próximo
                    // encerramento (de um novo atendimento/chamado) oferece de novo normalmente,
                    // mesmo que seja no mesmo dia.
                    $hasPendingSurvey = SurveyResponse::where('assistant_id', $assistant->id)
                        ->where('phone_number', $cleanSender)
                        ->where('survey_id', $candidateSurvey->id)
                        ->whereIn('status', ['awaiting_confirmation', 'in_progress'])
                        ->exists();

                    if (!$hasPendingSurvey) {
                        $offeredSurvey = $candidateSurvey;
                        $aiReply = 'Antes de finalizarmos, você poderia nos ajudar respondendo uma breve pesquisa de satisfação, bem rapidinha, aqui mesmo pelo WhatsApp?';
                        $hasMainMenuTag = false;
                    }
                }
            }
            if ($offeredSurvey) {
                $isAudioMessage = false;
            }

            // ENVIO PARA O OMNI COM A RESPOSTA FINAL TRATADA E FORMATADA
            $this->sendToOmni($aiReply, $displayName !== 'Cliente' ? $displayName : $cleanSender, 'output', $sendTarget, $assistant->id);

            DB::table('chat_messages')->insert([
                [
                    'assistant_id' => $assistant->id,
                    'phone_number' => $cleanSender,
                    'protocol' => $protocoloClean,
                    'role' => 'user',
                    'content' => $userMessage,
                    'created_at' => $nowFormatted,
                    'updated_at' => $nowFormatted,
                ],
                [
                    'assistant_id' => $assistant->id,
                    'phone_number' => $cleanSender,
                    'protocol' => $protocoloClean,
                    'role' => 'assistant',
                    'content' => $aiReply,
                    'created_at' => $nowFormatted,
                    'updated_at' => $nowFormatted,
                ]
            ]);

            if ($isAudioMessage) {
                $separated = $audioService->separateLinksFromText($aiReply);

                // === INÍCIO DO FILTRO DE ÁUDIO (LIMPEZA PARA O TTS) ===
                $textForAudio = $separated['audio_text'];

                // 1. Remove emojis (Mantém o texto de voz limpo)
                $textForAudio = preg_replace('/[\x{1F300}-\x{1F64F}\x{1F680}-\x{1F6FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F900}-\x{1F9FF}\x{1FA00}-\x{1FAFF}\x{1F1E6}-\x{1F1FF}\x{2300}-\x{23FF}\x{2500}-\x{25FF}\x{2B00}-\x{2BFF}]/u', '', $textForAudio);

                // 1.1. Emojis numerados (1️⃣, 2️⃣...) são um dígito normal + marcadores invisíveis
                // (seletor de variação U+FE0F + "keycap combinante" U+20E3), fora das faixas acima.
                // Sem remover esses marcadores, o Google TTS lia "tecla um", "tecla dois" em vez de
                // só "um", "dois".
                $textForAudio = preg_replace('/[\x{FE0F}\x{20E3}]/u', '', $textForAudio);

                // 2. Converte as horas para leitura correta (ex: 17h -> 17 horas, 09h30 -> 9 horas e 30 minutos).
                // O (int) descarta o zero à esquerda: sem isso "09h" virava "09 horas" e o Google TTS
                // lia dígito por dígito ("zero nove horas") em vez de "nove horas".
                $textForAudio = preg_replace_callback('/\b(\d{1,2})h(\d{2})\b/i', function ($m) {
                    return ((int) $m[1]) . ' horas e ' . ((int) $m[2]) . ' minutos';
                }, $textForAudio);
                $textForAudio = preg_replace_callback('/\b(\d{1,2})h\b/i', function ($m) {
                    return ((int) $m[1]) . ' horas';
                }, $textForAudio);
                // Cobre horários escritos como "09:00" (formato usado nas confirmações de agendamento),
                // mesmo motivo do item acima.
                $textForAudio = preg_replace_callback('/\b0(\d)(:\d{2})\b/', function ($m) {
                    return $m[1] . $m[2];
                }, $textForAudio);
                
                // 3. Substitui traços isolados por vírgula para forçar pausa ao invés de falar "menos"
                $textForAudio = preg_replace('/\s+[-–—]\s+/', ', ', $textForAudio);
                
                // 4. Remove pontuações extras e ícones textuais que o TTS pode verbalizar
                $textForAudio = str_replace(['*', '#', '_', '✅', '⚠️', '🎥', '🏢', '👤', '📅', '✉️', '📋', '🎫'], '', $textForAudio);

                // 5. O menu de continuação (quando a IA decide incluí-lo, conforme o prompt) nunca
                // vai na fala - soa estranho ler "1, tenho mais dúvidas..." em voz alta. Tira esse
                // bloco do que vira áudio e guarda separado pra mandar como texto logo em seguida.
                $menuBlockPattern = '/\n*(Restou mais alguma dúvida ou posso te ajudar em algo mais\?[\s\S]*)$/u';
                $strippedMenuText = null;
                if (preg_match($menuBlockPattern, $textForAudio, $menuBlockMatch)) {
                    $strippedMenuText = trim($menuBlockMatch[1]);
                    $textForAudio = trim(preg_replace($menuBlockPattern, '', $textForAudio));
                }
                // === FIM DO FILTRO DE ÁUDIO ===

                $googleKey = env('GOOGLE_API_KEY_TTS')
                    ?? env('GOOGLE_APIKEY_TTS')
                    ?? (defined('GOOGLE_APIKEY_TTS') ? GOOGLE_APIKEY_TTS : null)
                    ?? env('GOOGLE_API_KEY');

                // A resposta pode vir em qualquer idioma (regra de acompanhamento dinâmico do prompt),
                // então a voz do TTS precisa acompanhar - senão o texto sai lido com sotaque/fonética
                // de português, soletrando acentos e travessões de outros idiomas.
                [$ttsVoice, $ttsLangCode] = $this->pickTtsVoiceForText($aiReply);

                $audioData = $audioService->textToSpeech($textForAudio, $googleKey, $ttsVoice, 'FEMALE', $ttsLangCode);

                if ($audioData) {
                    $waResult = $this->sendWhatsappAudioMessage($assistant, $sendTarget, $audioData);

                    if (!empty($separated['extracted_links'])) {
                        $this->sendWhatsappMessage($assistant, $sendTarget, $separated['extracted_links']);
                    }

                    if ($strippedMenuText !== null) {
                        $this->sendWhatsappMessage($assistant, $sendTarget, $strippedMenuText);
                    }
                } else {
                    $formattedReply = $this->formatTextForWhatsapp($aiReply);
                    $waResult = $this->sendWhatsappMessage($assistant, $sendTarget, $formattedReply);
                }
            } elseif ($hasMainMenuTag) {
                $waResult = $this->sendWhatsappInteractiveMenu($assistant, $sendTarget, $aiReply);
            } else {
                $formattedReply = $this->formatTextForWhatsapp($aiReply);
                $waResult = $this->sendWhatsappMessage($assistant, $sendTarget, $formattedReply);
            }

            // A pergunta de oferta já foi enviada acima; cria o registro "aguardando confirmação"
            // pra que a PRÓXIMA mensagem desse número seja interpretada (em código) como sim/não.
            if ($offeredSurvey) {
                SurveyResponse::create([
                    'survey_id' => $offeredSurvey->id,
                    'assistant_id' => $assistant->id,
                    'phone_number' => $cleanSender,
                    'client_name' => $displayName,
                    'status' => 'awaiting_confirmation',
                    'current_question_id' => null,
                ]);
            }

            DB::table('webhook_logs')->insert([
                'assistant_id' => $assistant->id,
                'sender' => substr($sender, 0, 255),
                'user_message' => $userMessage,
                'ai_reply' => $aiReply,
                'wa_send_result' => json_encode($waResult, JSON_INVALID_UTF8_IGNORE),
                'raw_snippet' => json_encode($request->all(), JSON_INVALID_UTF8_IGNORE),
                'timestamp' => $nowFormatted,
                'created_at' => $nowFormatted,
                'updated_at' => $nowFormatted,
            ]);

            return response()->json(['status' => 'success', 'reply' => $aiReply]);
        } catch (\Throwable $e) {
            Log::error('Erro no webhook do WhatsApp', [
                'assistant_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            try {
                $nowFormatted = now()->setTimezone($this->getTimezone($id))->toDateTimeString();
                DB::table('webhook_logs')->insert([
                    'assistant_id' => $id,
                    'sender' => 'Erro Interno',
                    'user_message' => 'Falha Critica',
                    'ai_reply' => 'Erro: ' . mb_substr($e->getMessage(), 0, 500, 'UTF-8'),
                    'wa_send_result' => json_encode(['error' => $e->getMessage()], JSON_INVALID_UTF8_IGNORE),
                    'raw_snippet' => json_encode($request->all(), JSON_INVALID_UTF8_IGNORE),
                    'timestamp' => $nowFormatted,
                    'created_at' => $nowFormatted,
                    'updated_at' => $nowFormatted,
                ]);
            } catch (\Throwable $e2) {
                Log::error('Falha ao gravar log de erro do webhook', ['message' => $e2->getMessage()]);
            }

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 200);
        }
    }

    private function callAiApi(Assistant $assistant, string $systemPrompt, string $userMessage, array $history = []): string
    {
        $provider = $assistant->provider ?? 'openai';

        if ($provider === 'openai') {
            $key = trim($assistant->openai_api_key ?? '');
            if (!$key) return 'Erro: Chave API da OpenAI não configurada.';

            $messages = [['role' => 'system', 'content' => $systemPrompt]];
            foreach ($history as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
                }
            }
            $messages[] = ['role' => 'user', 'content' => $userMessage];

            $res = Http::withToken($key)->timeout(90)->post('https://api.openai.com/v1/chat/completions', [
                'model' => $assistant->model ?? 'gpt-4o-mini',
                'messages' => $messages,
            ]);

            if ($res->failed()) return 'Erro na API OpenAI: ' . json_encode($res->json());
            return $res->json('choices.0.message.content') ?? 'Resposta vazia da OpenAI.';
        }

        if ($provider === 'gemini') {
            $key = trim($assistant->gemini_api_key ?? '');
            if (!$key) return 'Erro: Chave API do Gemini não configurada.';

            $res = Http::timeout(90)->post("https://generativelanguage.googleapis.com/v1beta/models/{$assistant->model}:generateContent?key={$key}", [
                'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => [['parts' => [['text' => $userMessage]]]]
            ]);

            if ($res->failed()) return 'Erro na API Gemini: ' . json_encode($res->json());
            return $res->json('candidates.0.content.parts.0.text') ?? 'Resposta vazia do Gemini.';
        }

        if ($provider === 'anthropic') {
            $key = trim($assistant->anthropic_api_key ?? '');
            if (!$key) return 'Erro: Chave API do Claude não configurada.';

            $res = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json'
            ])->timeout(90)->post('https://api.anthropic.com/v1/messages', [
                'model' => $assistant->model ?? 'claude-3-haiku-20240307',
                'system' => $systemPrompt,
                'max_tokens' => 1024,
                'messages' => [['role' => 'user', 'content' => $userMessage]]
            ]);

            if ($res->failed()) return 'Erro na API Anthropic: ' . json_encode($res->json());
            return $res->json('content.0.text') ?? 'Resposta vazia da Anthropic.';
        }

        if ($provider === 'grok') {
            $key = trim($assistant->grok_api_key ?? '');
            if (!$key) return 'Erro: Chave API do Grok não configurada.';

            $messages = [['role' => 'system', 'content' => $systemPrompt]];
            foreach ($history as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
                }
            }
            $messages[] = ['role' => 'user', 'content' => $userMessage];

            $res = Http::withToken($key)->timeout(90)->post('https://api.x.ai/v1/chat/completions', [
                'model' => $assistant->model ?? 'grok-2-mini',
                'messages' => $messages
            ]);

            if ($res->failed()) return 'Erro na API Grok: ' . json_encode($res->json());
            return $res->json('choices.0.message.content') ?? 'Resposta vazia do Grok.';
        }

        return 'Provedor de IA não configurado.';
    }

    private function testAi(Request $request)
    {
        $provider = $request->provider;
        $apiKey = trim($request->api_key ?? '');

        if (!$apiKey) return response()->json(['success' => false, 'message' => 'Informe uma chave API válida.']);

        try {
            if ($provider === 'openai') {
                $res = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [['role' => 'user', 'content' => 'Responda OK']]
                ]);
                return response()->json([
                    'success' => $res->successful(), 
                    'message' => $res->successful() ? 'Conexão OpenAI OK!' : ($res->json('error.message') ?? 'Chave de API rejeitada.')
                ]);
            }
            if ($provider === 'gemini') {
                $res = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                    'contents' => [['parts' => [['text' => 'Responda OK']]]]
                ]);
                return response()->json([
                    'success' => $res->successful(), 
                    'message' => $res->successful() ? 'Conexão Gemini OK!' : 'Falha ao autenticar chave Gemini.'
                ]);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
        return response()->json(['success' => false, 'message' => 'Provedor inválido.']);
    }

    /**
     * Números "normais" (só dígitos) continuam limpos de qualquer formatação; mas se vier um JID
     * completo (com "@", ex: "158243909329030@lid" de um contato LID, cujo número de telefone real
     * o WhatsApp não expõe) o sufixo é preservado - a UazAPI/Baileys precisa do JID inteiro pra
     * conseguir entregar a mensagem nesse caso, já que os dígitos sozinhos não são discáveis.
     */
    private function normalizeWaTarget(string $to): string
    {
        return str_contains($to, '@') ? $to : preg_replace('/[^0-9]/', '', $to);
    }

    private function sendWhatsappMessage(Assistant $assistant, string $to, string $message): array
    {
        if (empty($assistant->whatsapp_url) || empty($assistant->whatsapp_token)) {
            return ['success' => false, 'error' => 'WhatsApp não configurado.'];
        }

        try {
            $cleanTo = $this->normalizeWaTarget($to);
            $baseUrl = rtrim($assistant->whatsapp_url, '/');
            $token = trim($assistant->whatsapp_token);

            if (str_contains($baseUrl, 'uazapi.com') || $assistant->whatsapp_provider === 'uazapi') {
                $endpoint = $baseUrl . '/send/text';
                
                $payload = [
                    'token' => $token,
                    'number' => $cleanTo,
                    'text' => $message
                ];

                $response = Http::withHeaders([
                    'token' => $token,
                    'Client-Token' => $token,
                    'client-token' => $token,
                    'apikey' => $token,
                    'Content-Type' => 'application/json'
                ])->post($endpoint . '?token=' . urlencode($token), $payload);

            } else {
                $endpoint = $baseUrl . '/message/sendText/' . $assistant->whatsapp_instance;
                $payload = [
                    'number' => $cleanTo,
                    'phone' => $cleanTo,
                    'text' => $message
                ];

                $response = Http::withHeaders([
                    'token' => $token,
                    'apikey' => $token,
                    'Content-Type' => 'application/json'
                ])->post($endpoint, $payload);
            }

            return ['success' => $response->successful(), 'error' => $response->failed() ? $response->body() : null];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Itens do menu inicial no formato "texto|id|descrição" exigido pelo endpoint /send/menu (tipo
     * "list") da UazAPI. Mantidos em código (não no prompt) porque a tag [MENU_PRINCIPAL] sempre
     * dispara exatamente essas opções - a IA não escreve mais a lista numerada por conta própria.
     */
    private function getMainMenuChoices(): array
    {
        return [
            'Conhecer ou contratar as soluções da InHouse|vendas|Vendas',
            'Sou Cliente|suporte|Suporte / Pós-venda',
            'Trabalhe Conosco|vagas|Vagas e Recrutamento',
            'Fornecedores|fornecedores|Oferecer produtos/serviços',
            'Agendar uma Reunião|agendar',
            'Outros Assuntos|outros',
        ];
    }

    private function getMainMenuFallbackText(): string
    {
        return "1️⃣ Conhecer ou contratar as soluções da InHouse (Vendas)\n"
             . "2️⃣ Sou Cliente (Suporte/Pós-venda)\n"
             . "3️⃣ Trabalhe Conosco (Vagas e Recrutamento)\n"
             . "4️⃣ Fornecedores (Oferecer produtos/serviços)\n"
             . "5️⃣ Agendar uma Reunião\n"
             . "6️⃣ Outros Assuntos";
    }

    /**
     * Monta o texto de uma pergunta de pesquisa (com as opções numeradas, se for múltipla escolha).
     */
    private function buildSurveyQuestionMessage($question): string
    {
        $msg = $question->question_text;

        if ($question->type === 'multiple_choice') {
            $msg .= "\n\n";
            $i = 1;
            foreach ($question->options as $option) {
                $msg .= "{$i}️⃣ {$option->option_text}\n";
                $i++;
            }
            $msg = rtrim($msg);
        }

        return $msg;
    }

    /**
     * Registra a resposta da pergunta atual e avança pra próxima (ou encerra a pesquisa).
     * Pra perguntas de múltipla escolha, aceita tanto o número da opção quanto o texto exato -
     * mas nunca trava o fluxo se não bater com nada, só grava o que o cliente escreveu mesmo.
     */
    private function handleSurveyAnswer(Assistant $assistant, SurveyResponse $surveyResponse, string $userMessage): void
    {
        $pushName = $surveyResponse->client_name ?: $surveyResponse->phone_number;
        $this->sendToOmni($userMessage, $pushName, 'input', $surveyResponse->phone_number, $assistant->id);

        $question = $surveyResponse->currentQuestion;
        if (!$question) {
            $surveyResponse->update(['status' => 'completed', 'completed_at' => now()]);
            return;
        }

        $answerText = trim($userMessage);

        if ($question->type === 'multiple_choice') {
            $options = $question->options;
            if (preg_match('/^\s*(\d+)\s*$/', $answerText, $m)) {
                $option = $options->get(((int) $m[1]) - 1);
                if ($option) $answerText = $option->option_text;
            } else {
                foreach ($options as $option) {
                    if (mb_strtolower(trim($option->option_text)) === mb_strtolower($answerText)) {
                        $answerText = $option->option_text;
                        break;
                    }
                }
            }
        }

        SurveyResponseAnswer::create([
            'survey_response_id' => $surveyResponse->id,
            'survey_question_id' => $question->id,
            'answer_text' => $answerText,
        ]);

        $nextQuestion = $question->survey->questions()->where('sort_order', '>', $question->sort_order)->first();

        if ($nextQuestion) {
            $surveyResponse->update(['current_question_id' => $nextQuestion->id]);
            $nextMsg = $this->buildSurveyQuestionMessage($nextQuestion);
            $this->sendWhatsappMessage($assistant, $surveyResponse->phone_number, $nextMsg);
            $this->sendToOmni($nextMsg, $pushName, 'output', $surveyResponse->phone_number, $assistant->id);
            return;
        }

        $surveyResponse->update(['status' => 'completed', 'completed_at' => now(), 'current_question_id' => null]);
        $thanksMsg = 'Muito obrigado por responder nossa pesquisa! 🙏 Sua opinião é muito importante pra gente.';
        $this->sendWhatsappMessage($assistant, $surveyResponse->phone_number, $thanksMsg);
        $this->sendToOmni($thanksMsg, $pushName, 'output', $surveyResponse->phone_number, $assistant->id);

        $this->sendAiGeneratedClosing(
            $assistant,
            $surveyResponse->phone_number,
            $pushName,
            '[SISTEMA: o cliente acabou de concluir a pesquisa de opinião - ela já foi respondida agora mesmo aqui no chat. Finalize o atendimento agora, seguindo estritamente as suas instruções de encerramento. Não pergunte mais nada, não ofereça nenhuma pesquisa e NÃO inclua nenhuma tag de pesquisa entre colchetes na sua resposta - isso já foi feito.]'
        );
    }

    /**
     * Interpreta (100% em código, sem IA) a resposta do cliente à pergunta "quer responder a
     * pesquisa?" - se topar, inicia a pesquisa; senão (ou se não entender a resposta), encerra
     * o atendimento normalmente através da própria IA (mensagem exata que fecha o chamado no Omni).
     */
    private function handleSurveyConfirmation(Assistant $assistant, SurveyResponse $pending, string $userMessage): void
    {
        $pushName = $pending->client_name ?: $pending->phone_number;
        $this->sendToOmni($userMessage, $pushName, 'input', $pending->phone_number, $assistant->id);

        $accepted = (bool) preg_match('/\b(sim|s|quero|claro|pode|ok|okay|beleza|bora|vamos|topo|aceito|com certeza)\b/iu', trim($userMessage));
        $survey = $pending->survey;
        $firstQuestion = $survey ? $survey->questions()->first() : null;

        if ($accepted && $firstQuestion) {
            $pending->update(['status' => 'in_progress', 'current_question_id' => $firstQuestion->id]);
            $questionMsg = $this->buildSurveyQuestionMessage($firstQuestion);
            $this->sendWhatsappMessage($assistant, $pending->phone_number, $questionMsg);
            $this->sendToOmni($questionMsg, $pushName, 'output', $pending->phone_number, $assistant->id);
            return;
        }

        $pending->update(['status' => 'declined', 'completed_at' => now()]);
        $this->sendAiGeneratedClosing(
            $assistant,
            $pending->phone_number,
            $pushName,
            '[SISTEMA: o cliente respondeu que não quer (ou não respondeu claramente) à oferta da pesquisa de opinião. Finalize o atendimento agora, seguindo estritamente as suas instruções de encerramento. Não pergunte mais nada e não ofereça nenhuma pesquisa de novo.]'
        );
    }

    /**
     * Pede pra própria IA gerar a mensagem de encerramento (com o texto exato configurado no
     * prompt dela, que é o que o Omni reconhece pra fechar o chamado) e manda pro cliente já
     * limpa de qualquer tag/link de pesquisa - usado depois que a pesquisa foi concluída ou
     * recusada, pra não depender de um texto fixo de encerramento aqui no código (cada
     * assistente pode ter um texto diferente).
     */
    private function sendAiGeneratedClosing(Assistant $assistant, string $phone, ?string $pushName, string $instructionNote): void
    {
        try {
            $systemPrompt = $this->buildSystemPromptWithKnowledge($assistant);
            $closingReply = $this->callAiApi($assistant, $systemPrompt, $instructionNote);

            // O Omni recebe o texto cru (é ele que reconhece a mensagem de encerramento pra fechar o
            // chamado). O cliente no WhatsApp não precisa ver nenhuma tag nem o link antigo de pesquisa.
            $this->sendToOmni($closingReply, $pushName ?: $phone, 'output', $phone, $assistant->id);

            $cleaned = trim(preg_replace('/\[MENU_PRINCIPAL\]/i', '', $closingReply));
            $cleaned = trim(preg_replace('/\[ENCERRAMENTO\]/i', '', $cleaned));
            foreach (Survey::where('assistant_id', $assistant->id)->where('is_active', true)->get() as $s) {
                $cleaned = trim(preg_replace('/\[' . preg_quote($s->tag, '/') . '\]/i', '', $cleaned));
            }
            $cleaned = trim(preg_replace('/\n?[^\n]*\[[^\]]*pesquisa[^\]]*\]\([^\)]+\)[^\n]*/iu', '', $cleaned));

            $formatted = $this->formatTextForWhatsapp($cleaned);
            $this->sendWhatsappMessage($assistant, $phone, $formatted);
        } catch (\Throwable $e) {
            Log::error('Erro ao gerar mensagem de encerramento via IA: ' . $e->getMessage());
        }
    }

    /**
     * Envia o menu inicial como lista interativa do WhatsApp (endpoint /send/menu da UazAPI).
     * A própria documentação da UazAPI avisa que botões/listas "podem ser descontinuados a
     * qualquer momento sem aviso prévio" - por isso, qualquer falha aqui cai para texto simples
     * em vez de deixar o cliente sem menu nenhum.
     */
    private function sendWhatsappInteractiveMenu(Assistant $assistant, string $to, string $introText): array
    {
        $baseUrl = rtrim($assistant->whatsapp_url ?? '', '/');
        $isUazapi = str_contains($baseUrl, 'uazapi.com') || $assistant->whatsapp_provider === 'uazapi';
        $fallbackText = trim($introText) . "\n\n" . $this->getMainMenuFallbackText();

        if (empty($baseUrl) || empty($assistant->whatsapp_token) || !$isUazapi) {
            return $this->sendWhatsappMessage($assistant, $to, $fallbackText);
        }

        try {
            $cleanTo = $this->normalizeWaTarget($to);
            $token = trim($assistant->whatsapp_token);
            $endpoint = $baseUrl . '/send/menu';

            $payload = [
                'token' => $token,
                'number' => $cleanTo,
                'type' => 'list',
                'text' => $introText,
                'choices' => $this->getMainMenuChoices(),
                'listButton' => 'Ver opções',
            ];

            $response = Http::withHeaders([
                'token' => $token,
                'Content-Type' => 'application/json'
            ])->post($endpoint . '?token=' . urlencode($token), $payload);

            if ($response->failed()) {
                Log::warning("Falha ao enviar menu interativo, caindo para texto simples: " . $response->body());
                return $this->sendWhatsappMessage($assistant, $to, $fallbackText);
            }

            return ['success' => true, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning("Exceção ao enviar menu interativo, caindo para texto simples: " . $e->getMessage());
            return $this->sendWhatsappMessage($assistant, $to, $fallbackText);
        }
    }

    private function sendWhatsappAudioMessage(Assistant $assistant, string $to, $audioData): array
    {
        if (empty($assistant->whatsapp_url) || empty($assistant->whatsapp_token)) {
            return ['success' => false, 'error' => 'WhatsApp não configurado.'];
        }

        try {
            $cleanTo = $this->normalizeWaTarget($to);
            $baseUrl = rtrim($assistant->whatsapp_url, '/');
            $token = trim($assistant->whatsapp_token);

            $b64Raw = '';
            $possiblePath = '';

            if (is_array($audioData)) {
                $b64Raw = $audioData['base64'] ?? '';
                $possiblePath = $audioData['url'] ?? $audioData['path'] ?? '';
            } else if (is_string($audioData)) {
                $possiblePath = $audioData;
            }

            if (empty($b64Raw) && !empty($possiblePath)) {
                $relativePath = parse_url($possiblePath, PHP_URL_PATH) ?? $possiblePath;
                $cleanRelative = ltrim(str_replace('/storage/', '', $relativePath), '/');

                $localCandidates = [
                    $possiblePath,
                    storage_path('app/public/' . $cleanRelative),
                    storage_path('app/' . $cleanRelative),
                    public_path('storage/' . $cleanRelative),
                    public_path($cleanRelative)
                ];

                foreach ($localCandidates as $candidate) {
                    if (file_exists($candidate) && is_file($candidate)) {
                        $content = file_get_contents($candidate);
                        if ($content && strlen($content) > 100) {
                            $b64Raw = base64_encode($content);
                            break;
                        }
                    }
                }
            }

            if (str_contains($baseUrl, 'uazapi.com') || $assistant->whatsapp_provider === 'uazapi') {
                $endpoint = $baseUrl . '/send/media';
                
                $headers = [
                    'token' => $token,
                    'Client-Token' => $token,
                    'client-token' => $token,
                    'apikey' => $token,
                    'Content-Type' => 'application/json'
                ];

                $filePayload = !empty($b64Raw) ? 'data:audio/mp3;base64,' . $b64Raw : $possiblePath;

                $payload = [
                    'token' => $token,
                    'number' => $cleanTo,
                    'file' => $filePayload,
                    'type' => 'audio',
                    'mimetype' => 'audio/mp3',
                    'ptt' => true
                ];

                $response = Http::withHeaders($headers)->timeout(30)->post($endpoint . '?token=' . urlencode($token), $payload);
                
                if ($response->successful()) {
                    return ['success' => true, 'response' => $response->json()];
                }

                return ['success' => false, 'error' => $response->body()];

            } else {
                $endpoint = $baseUrl . '/message/sendWhatsAppAudio/' . $assistant->whatsapp_instance;
                $payload = [
                    'number' => $cleanTo,
                    'audio' => !empty($b64Raw) ? 'data:audio/mp3;base64,' . $b64Raw : $possiblePath
                ];

                $response = Http::withHeaders($headers)->post($endpoint, $payload);

                return ['success' => $response->successful(), 'error' => $response->failed() ? $response->body() : null];
            }

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function sendWhatsappPresence(Assistant $assistant, string $to, string $status = 'composing'): void
    {
        if (empty($assistant->whatsapp_url) || empty($assistant->whatsapp_token)) {
            return;
        }

        try {
            $cleanTo = $this->normalizeWaTarget($to);
            $baseUrl = rtrim($assistant->whatsapp_url, '/');
            $token = trim($assistant->whatsapp_token);
            $instance = trim($assistant->whatsapp_instance ?? '');

            $headers = [
                'token' => $token,
                'Client-Token' => $token,
                'client-token' => $token,
                'apikey' => $token,
                'Content-Type' => 'application/json'
            ];

            if (str_contains($baseUrl, 'uazapi.com') || $assistant->whatsapp_provider === 'uazapi') {
                $tests = [
                    ['path' => '/send/typing', 'payload' => ['token' => $token, 'number' => $cleanTo]],
                    ['path' => '/send/state', 'payload' => ['token' => $token, 'number' => $cleanTo, 'state' => 'composing']],
                    ['path' => '/chat/presence', 'payload' => ['token' => $token, 'number' => $cleanTo, 'presence' => $status]],
                    ['path' => '/message/presence', 'payload' => ['token' => $token, 'number' => $cleanTo, 'presence' => $status]],
                    ['path' => '/instance/presence', 'payload' => ['token' => $token, 'number' => $cleanTo, 'presence' => $status]],
                ];

                foreach ($tests as $t) {
                    $url = $baseUrl . $t['path'] . '?token=' . urlencode($token);
                    $res = Http::withHeaders($headers)->timeout(4)->post($url, $t['payload']);

                    $allowHeader = $res->header('Allow') ?? 'N/A';
                    Log::info("Test Uazapi [POST {$t['path']}]: HTTP " . $res->status() . " (Allow: {$allowHeader}) - " . $res->body());

                    if ($res->successful()) {
                        break;
                    }
                }
            } else {
                $endpoint = $baseUrl . '/chat/sendPresence/' . $instance;
                $payload = [
                    'number' => $cleanTo,
                    'presence' => $status,
                    'delay' => 12000
                ];
                $res = Http::withHeaders($headers)->timeout(5)->post($endpoint, $payload);
                Log::info("Tentativa Presenca Evolution: HTTP " . $res->status() . " - " . $res->body());
            }

        } catch (\Throwable $e) {
            Log::warning("Aviso: Falha ao enviar presenca WhatsApp: " . $e->getMessage());
        }
    }
}