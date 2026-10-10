<?php

namespace App\Http\Controllers;

use App\Models\Assistant;
use App\Models\Holiday;
use App\Models\AutomationFollowup;
use App\Models\WaContactName;
use App\Models\CrawledPage;
use App\Models\PendingMessageBuffer;
use App\Models\TestRun;
use App\Services\TestHarnessService;
use Illuminate\Support\Str;
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
    // Quantos segundos esperar antes de processar uma mensagem de texto, pra dar chance de
    // mensagens seguidas do mesmo número (ex: cliente digita em duas ou três partes) serem
    // agrupadas num único turno de IA - ver bloco de debounce em webhook().
    private const DEBOUNCE_SECONDS = 5;

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

            if (!Schema::hasColumn('assistants', 'status')) {
                Schema::table('assistants', function (Blueprint $table) {
                    $table->string('status', 20)->default('active')->after('is_active');
                });
                // Assistentes ja marcados como inativos antes dessa coluna existir devem
                // continuar inativos (e nao "ativos" so por causa do default da coluna nova).
                DB::table('assistants')->where('is_active', 0)->update(['status' => 'inactive']);
            }

            if (!Schema::hasColumn('assistants', 'whatsapp_waba_id')) {
                Schema::table('assistants', function (Blueprint $table) {
                    $table->string('whatsapp_waba_id')->nullable()->after('whatsapp_instance');
                });
            }

            if (!Schema::hasColumn('assistants', 'whatsapp_pin')) {
                Schema::table('assistants', function (Blueprint $table) {
                    $table->string('whatsapp_pin', 10)->nullable()->after('whatsapp_waba_id');
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
        if ($request->isMethod('post') && $request->input('action') === 'meta_connect') return $this->connectMeta($request);
        if ($request->isMethod('post') && $request->input('action') === 'meta_connect_waba') return $this->connectMetaWabaOnly($request);
        if ($request->isMethod('post') && $request->input('action') === 'meta_connect_manual') return $this->connectMetaManual($request);
        if ($request->isMethod('post') && $request->input('action') === 'meta_list_phone_numbers') return $this->listMetaPhoneNumbers($request);
        if ($request->isMethod('post') && $request->input('action') === 'meta_check_phone_status') return $this->checkMetaPhoneStatus($request);
        if ($request->isMethod('post') && $request->input('action') === 'meta_list_templates') return $this->listMetaTemplates($request);
        if ($request->isMethod('post') && $request->input('action') === 'meta_create_template') return $this->createMetaTemplate($request);
        if ($request->isMethod('post') && $request->input('action') === 'meta_delete_template') return $this->deleteMetaTemplate($request);
        if ($request->isMethod('post') && $request->input('action') === 'meta_update_template') return $this->updateMetaTemplate($request);
        if ($request->isMethod('post') && $request->input('action') === 'meta_add_phone_number') return $this->addMetaPhoneNumber($request);
        if ($request->isMethod('post') && $request->input('action') === 'meta_request_code') return $this->requestMetaVerificationCode($request);
        if ($request->isMethod('post') && $request->input('action') === 'meta_verify_code') return $this->verifyMetaCode($request);

        if ($request->isMethod('post') && $request->input('action') === 'map_site') return $this->mapSite($request);
        if ($request->isMethod('post') && $request->input('action') === 'scrape_single_url') return $this->scrapeSingleUrl($request);
        if ($request->isMethod('post') && $request->input('action') === 'discover_site_menu') return $this->discoverSiteMenu($request);
        if ($request->isMethod('post') && $request->input('action') === 'crawl_menu_page') return $this->crawlMenuPage($request);
        if ($request->isMethod('get') && $request->input('action') === 'export_knowledge_base') return $this->exportKnowledgeBaseCsv($request);
        if ($request->isMethod('get') && $request->input('action') === 'knowledge_base_rows') return response()->json($this->buildKnowledgeBaseRows(Assistant::findOrFail($request->query('assistant_id'))));

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
        $activeContactName = null;
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

                $contactNames = WaContactName::where('assistant_id', $conversationsAssistant->id)->pluck('name', 'phone_number');
                foreach ($conversationThreads as $thread) {
                    $thread->contact_name = $contactNames[$thread->phone_number] ?? null;
                }

                if (!$activePhone && count($conversationThreads) > 0) {
                    $activePhone = $conversationThreads[0]->phone_number;
                }

                if ($activePhone) {
                    $activeThreadMessages = DB::table('chat_messages')
                        ->where('assistant_id', $conversationsAssistant->id)
                        ->where('phone_number', $activePhone)
                        ->orderBy('id', 'asc')
                        ->get();
                    $activeContactName = $contactNames[$activePhone] ?? null;
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
            'conversationsAssistant', 'conversationThreads', 'activeThreadMessages', 'activePhone', 'activeContactName', 'currentView',
            'departments', 'agents', 'assistantTz'
        ));
    }

    /**
     * Dados pra tela "Ver Base de Conhecimento" (grid ordenável + export CSV) - não mexe em nada da
     * lista/checkbox/bulk-delete que já existe, é só uma segunda forma de consulta. Carregado via
     * chamada separada (action=knowledge_base_rows) em vez de embutido na página principal - uma
     * base de conhecimento grande (nomes de página com caracteres variados, emojis, etc.) quebrava o
     * parse do bloco x-data gigante quando embutida inline com @js().
     */
    private function buildKnowledgeBaseRows(Assistant $assistant): array
    {
        $rows = [];
        foreach ((is_array($assistant->knowledge_files) ? $assistant->knowledge_files : []) as $i => $f) {
            $path = $f['path'] ?? null;
            $isSiteCrawl = $path && str_starts_with($path, 'site_crawls/');
            $addedAt = isset($f['added_at']) ? \Carbon\Carbon::parse($f['added_at']) : null;
            $rows[] = [
                'index' => $i,
                'name' => $f['name'] ?? '',
                'type' => $isSiteCrawl ? 'Varredura de site' : (str_starts_with($f['name'] ?? '', '🌐') ? 'Extração simples' : 'Upload'),
                'size' => isset($f['content']) ? strlen($f['content']) : null,
                'crawled_at' => $addedAt ? $addedAt->format('d/m/Y H:i') : null,
                'crawled_at_sort' => $addedAt ? $addedAt->timestamp : 0,
            ];
        }
        return $rows;
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

    /**
     * Apaga o histórico de conversa (chat_messages), estado de pesquisa/automação pendente, nome
     * salvo e logs de um ou mais números, só para este assistente - usado tanto pra reiniciar um
     * teste do zero (um número) quanto pra limpeza em lote (vários números selecionados na tela de
     * Conversas) sem a IA "lembrar" de conversas anteriores.
     */
    public function clearContext(Request $request, $id)
    {
        $request->validate([
            'phone' => 'nullable|string|max:50',
            'phones' => 'nullable|array',
            'phones.*' => 'string|max:50',
        ]);

        $assistant = Assistant::findOrFail($id);

        $rawPhones = $request->input('phones', []);
        if (empty($rawPhones) && $request->filled('phone')) {
            $rawPhones = [$request->input('phone')];
        }
        $phones = array_values(array_unique(array_filter(array_map(
            fn ($p) => preg_replace('/[^0-9]/', '', (string) $p),
            $rawPhones
        ))));

        if (empty($phones)) {
            return response()->json(['message' => 'Nenhum número válido informado.'], 422);
        }

        $chatMessages = DB::table('chat_messages')->where('assistant_id', $assistant->id)->whereIn('phone_number', $phones)->delete();
        $automationFollowups = AutomationFollowup::where('assistant_id', $assistant->id)->whereIn('phone_number', $phones)->delete();
        $surveyResponses = SurveyResponse::where('assistant_id', $assistant->id)->whereIn('phone_number', $phones)->delete();
        $contactNames = WaContactName::where('assistant_id', $assistant->id)->whereIn('phone_number', $phones)->delete();
        $webhookLogs = DB::table('webhook_logs')->where('assistant_id', $assistant->id)
            ->where(function ($q) use ($phones) {
                foreach ($phones as $phone) {
                    $q->orWhere('sender', 'like', '%' . $phone . '%');
                }
            })->delete();

        return response()->json([
            'success' => true,
            'chat_messages' => $chatMessages,
            'automation_followups' => $automationFollowups,
            'survey_responses' => $surveyResponses,
            'contact_names' => $contactNames,
            'webhook_logs' => $webhookLogs,
        ]);
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

    /**
     * Tentativa best-effort de achar o número de telefone real conectado na resposta de status da
     * UazAPI - a API só devolve isso quando já está conectada, e o nome exato do campo varia por
     * provedor/versão (não documentado de forma estável), então checa alguns nomes plausíveis e
     * aceita não achar nada (retorna null) sem quebrar o resto do status check.
     */
    private function extractWaNumber(array $json): ?string
    {
        $raw = $json['owner']
            ?? $json['number']
            ?? $json['wid']
            ?? $json['instance']['owner']
            ?? $json['instance']['number']
            ?? $json['instance']['wid']
            ?? $json['instance']['profileName'] ?? null;

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        // Formatos comuns: "5511999999999@s.whatsapp.net", "5511999999999@c.us" - fica só com os dígitos.
        $digits = preg_replace('/[^0-9]/', '', explode('@', $raw)[0]);

        return ($digits && strlen($digits) >= 10) ? $digits : null;
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
            $connectedNumber = null;
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
                            $connectedNumber = $this->extractWaNumber($json);
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
                                $connectedNumber = $this->extractWaNumber($json);
                                break;
                            }
                        }
                    }
                }
            }

            if ($connected) {
                return response()->json(['connected' => true, 'success' => true, 'message' => 'WhatsApp conectado!', 'number' => $connectedNumber]);
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

    /**
     * Troca de credenciais apos o popup de Embedded Signup da Meta terminar no front-end:
     * recebe o "code" de autorizacao + phone_number_id/waba_id (capturados via postMessage
     * durante o popup) e faz o restante do trabalho no servidor - troca o code por um token,
     * estende pra longa duracao, assina o webhook do App no WABA do cliente, e salva tudo
     * no assistente. Nada disso acontece no front, igual ao fluxo do Google Calendar.
     */
    private function connectMeta(Request $request)
    {
        $request->validate([
            'assistant_id' => 'required|exists:assistants,id',
            'code' => 'required|string',
            'phone_number_id' => 'required|string',
            'waba_id' => 'required|string',
        ]);

        $assistant = Assistant::findOrFail($request->input('assistant_id'));

        $appId = Setting::getGlobal('meta_app_id');
        $appSecret = Setting::getGlobal('meta_app_secret');
        if (empty($appId) || empty($appSecret)) {
            return response()->json(['success' => false, 'message' => 'Configure o App ID e o App Secret da Meta em Ambiente antes de conectar.'], 422);
        }

        try {
            $accessToken = $this->exchangeMetaCodeForToken($request->input('code'), $appId, $appSecret);
            if (!$accessToken) {
                return response()->json(['success' => false, 'message' => 'Não foi possível validar a conexão com a Meta.'], 422);
            }

            $wabaId = $request->input('waba_id');
            $this->subscribeMetaWebhook($wabaId, $accessToken);

            $assistant->whatsapp_provider = 'meta';
            $assistant->whatsapp_instance = $request->input('phone_number_id');
            $assistant->whatsapp_waba_id = $wabaId;
            $assistant->whatsapp_token = $accessToken;
            $assistant->save();

            return response()->json(['success' => true, 'message' => 'WhatsApp conectado via Meta com sucesso!']);
        } catch (\Throwable $e) {
            Log::error('Exceção ao conectar WhatsApp via Meta: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao conectar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Troca o "code" do popup por um access token de longa duracao (~60 dias) - usado tanto
     * pelo fluxo antigo (connectMeta, popup entrega tudo de uma vez) quanto pelo fluxo novo
     * (connectMetaWabaOnly, popup so entrega o WABA e a gente cadastra o telefone depois).
     * Nao e um token permanente de System User (exigiria configuracao adicional fora do
     * Embedded Signup) - um job de renovacao periodica fica como melhoria futura.
     */
    private function exchangeMetaCodeForToken(string $code, string $appId, string $appSecret): ?string
    {
        $tokenResponse = Http::get('https://graph.facebook.com/v21.0/oauth/access_token', [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'code' => $code,
        ]);

        if (!$tokenResponse->successful() || !$tokenResponse->json('access_token')) {
            Log::error('Erro ao trocar code por token (Meta): ' . $tokenResponse->body());
            return null;
        }

        $shortLivedToken = $tokenResponse->json('access_token');

        $longLivedResponse = Http::get('https://graph.facebook.com/v21.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'fb_exchange_token' => $shortLivedToken,
        ]);

        return ($longLivedResponse->successful() && $longLivedResponse->json('access_token'))
            ? $longLivedResponse->json('access_token')
            : $shortLivedToken;
    }

    /**
     * Assina o webhook do seu App no WABA do cliente - sem isso, mensagens desse cliente nunca
     * chegam no /webhook/whatsapp-meta. So loga aviso em caso de falha (nao interrompe o fluxo
     * de conexao - o admin pode reassinar depois se precisar).
     */
    private function subscribeMetaWebhook(string $wabaId, string $accessToken): void
    {
        $response = Http::withToken($accessToken)->post("https://graph.facebook.com/v21.0/{$wabaId}/subscribed_apps");
        if (!$response->successful()) {
            Log::warning('Falha ao inscrever o App nos webhooks do WABA ' . $wabaId . ': ' . $response->body());
        }
    }

    /**
     * Variante de connectMeta() pro caso da Configuration do Embedded Signup estar no modo
     * "sem selecao de numero": o popup devolve so o WABA (sem phone_number_id). Salva o WABA +
     * token no assistente, mas NAO marca whatsapp_provider='meta' ainda - so depois que o
     * numero for cadastrado e verificado de verdade (addMetaPhoneNumber -> requestMetaVerificationCode
     * -> verifyMetaCode), senao o assistente apareceria "conectado" sem nenhum numero.
     */
    private function connectMetaWabaOnly(Request $request)
    {
        $request->validate([
            'assistant_id' => 'required|exists:assistants,id',
            'code' => 'required|string',
            'waba_id' => 'required|string',
        ]);

        $assistant = Assistant::findOrFail($request->input('assistant_id'));

        $appId = Setting::getGlobal('meta_app_id');
        $appSecret = Setting::getGlobal('meta_app_secret');
        if (empty($appId) || empty($appSecret)) {
            return response()->json(['success' => false, 'message' => 'Configure o App ID e o App Secret da Meta em Ambiente antes de conectar.'], 422);
        }

        try {
            $accessToken = $this->exchangeMetaCodeForToken($request->input('code'), $appId, $appSecret);
            if (!$accessToken) {
                return response()->json(['success' => false, 'message' => 'Não foi possível validar a conexão com a Meta.'], 422);
            }

            $wabaId = $request->input('waba_id');
            $this->subscribeMetaWebhook($wabaId, $accessToken);

            $assistant->whatsapp_waba_id = $wabaId;
            $assistant->whatsapp_token = $accessToken;
            $assistant->save();

            return response()->json(['success' => true, 'message' => 'Conta da Meta conectada! Agora cadastre o número de WhatsApp.']);
        } catch (\Throwable $e) {
            Log::error('Exceção ao conectar WABA via Meta: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao conectar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Conexao manual, colando credenciais ja prontas (phone_number_id + access_token + waba_id) -
     * pensada pro numero de teste gratuito que a Meta da em "Etapa 1. Experimente" durante o App
     * Review (ja vem pre-verificado, funciona com Standard Access, sem precisar passar pelo
     * Embedded Signup nem pelo cadastro manual de numero novo). Util tambem pra qualquer numero
     * que ja exista e so precise ser plugado no assistente sem repetir o fluxo todo.
     */
    private function connectMetaManual(Request $request)
    {
        $request->validate([
            'assistant_id' => 'required|exists:assistants,id',
            'phone_number_id' => 'required|string',
            'access_token' => 'required|string',
            'waba_id' => 'nullable|string',
        ]);

        $assistant = Assistant::findOrFail($request->input('assistant_id'));
        $accessToken = $request->input('access_token');
        $wabaId = $request->input('waba_id');

        $assistant->whatsapp_provider = 'meta';
        $assistant->whatsapp_instance = $request->input('phone_number_id');
        $assistant->whatsapp_token = $accessToken;

        if ($wabaId) {
            $assistant->whatsapp_waba_id = $wabaId;
            // Sem isso, a Meta nunca manda nada pro nosso webhook pra esse WABA - mesmo com
            // numero/token certos, fica tudo em silencio (bug ja encontrado uma vez aqui).
            $this->subscribeMetaWebhook($wabaId, $accessToken);
        }
        $assistant->save();

        $message = $wabaId
            ? 'Número conectado manualmente!'
            : 'Número conectado, mas sem o WABA ID o App não foi inscrito nos webhooks desse WABA - a Meta não vai mandar mensagens pra gente assim. Preencha o campo WABA ID e conecte de novo.';

        return response()->json(['success' => true, 'message' => $message]);
    }

    /**
     * O popup da Meta pode ter deixado um numero ja ADICIONADO ao WABA mesmo sem a verificacao
     * ter sido concluida (ex: o admin entrou com o numero, o SMS saiu disparado pelo bug da
     * telinha, mas ele fechou o popup antes de confirmar o codigo) - adicionar e verificar sao
     * passos separados na API da Meta. Lista os numeros existentes nesse WABA pra evitar tentar
     * cadastrar de novo um numero que ja existe (a Meta rejeitaria como duplicado) e permitir
     * retomar a verificacao de onde parou, por fora do popup.
     */
    private function listMetaPhoneNumbers(Request $request)
    {
        $request->validate(['assistant_id' => 'required|exists:assistants,id']);

        $assistant = Assistant::findOrFail($request->input('assistant_id'));
        if (empty($assistant->whatsapp_waba_id) || empty($assistant->whatsapp_token)) {
            return response()->json(['success' => false, 'message' => 'Conecte a conta da Meta antes de listar números.'], 422);
        }

        try {
            $response = Http::withToken($assistant->whatsapp_token)
                ->get("https://graph.facebook.com/v21.0/{$assistant->whatsapp_waba_id}/phone_numbers", [
                    'fields' => 'id,display_phone_number,verified_name,code_verification_status',
                ]);

            if (!$response->successful()) {
                Log::error('Erro ao listar números do WABA na Meta: ' . $response->body());
                return response()->json(['success' => false, 'message' => 'Não foi possível listar os números já cadastrados.'], 422);
            }

            return response()->json(['success' => true, 'numbers' => $response->json('data') ?? []]);
        } catch (\Throwable $e) {
            Log::error('Exceção ao listar números do WABA na Meta: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao listar números: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Consulta o status real do numero direto na Meta
     * (status/name_status/code_verification_status/quality_rating) - sem documentacao oficial
     * sobre o motivo exato de "Pendente", esses campos mostram em qual sub-etapa o numero esta
     * parado (revisao do nome de exibicao / emissao do certificado / verificacao do codigo).
     */
    private function getMetaPhoneStatus(string $phoneNumberId, string $token): ?array
    {
        $response = Http::withToken($token)->get("https://graph.facebook.com/v21.0/{$phoneNumberId}", [
            'fields' => 'status,name_status,code_verification_status,quality_rating,display_phone_number,verified_name',
        ]);

        if (!$response->successful()) {
            Log::error('Erro ao consultar status do número na Meta: ' . $response->body());
            return null;
        }

        return $response->json();
    }

    /**
     * Chama o register() no numero ja verificado. A documentacao da Meta nao lista nenhum passo
     * depois do register(), mas ha relatos (de outros desenvolvedores reproduzindo o mesmo
     * cenario) de que repetir essa chamada destrava um numero preso em status "Pendente".
     * Idempotente do lado da Meta - chamar de novo em um numero ja registrado nao tem efeito
     * colateral conhecido, so reafirma o registro.
     */
    private function registerMetaPhoneWithMeta(string $phoneNumberId, string $token, string $pin): bool
    {
        $response = Http::withToken($token)->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/register", [
            'messaging_product' => 'whatsapp',
            'pin' => $pin,
        ]);

        if (!$response->successful()) {
            Log::error('Erro ao registrar número na Meta: ' . $response->body());
        }

        return $response->successful();
    }

    /**
     * Autocura: confere o status real do numero e, se nao estiver CONNECTED, chama register() de
     * novo sozinha - pra quem esta configurando (ou o cliente, na futura tela publica) nunca
     * precisar notar ou agir manualmente sobre um numero preso em "Pendente". Roda tanto logo
     * apos o primeiro registro quanto toda vez que o status e consultado no painel.
     */
    private function ensureMetaPhoneActive(string $phoneNumberId, string $token, string $pin): ?array
    {
        $status = $this->getMetaPhoneStatus($phoneNumberId, $token);
        if ($status && ($status['status'] ?? null) !== 'CONNECTED') {
            $this->registerMetaPhoneWithMeta($phoneNumberId, $token, $pin);
            $status = $this->getMetaPhoneStatus($phoneNumberId, $token);
        }
        return $status;
    }

    /**
     * Diagnostico no painel: consulta o status do numero e, se precisar, já aciona a autocura
     * (ensureMetaPhoneActive) antes de responder - o admin nunca ve um status "Pendente" parado
     * sem a aplicacao ja ter tentado resolver sozinha.
     */
    private function checkMetaPhoneStatus(Request $request)
    {
        $request->validate(['assistant_id' => 'required|exists:assistants,id']);

        $assistant = Assistant::findOrFail($request->input('assistant_id'));
        if (empty($assistant->whatsapp_instance) || empty($assistant->whatsapp_token)) {
            return response()->json(['success' => false, 'message' => 'Esse assistente ainda não tem um número conectado via Meta.'], 422);
        }

        try {
            $pin = $assistant->whatsapp_pin ?: (string) random_int(100000, 999999);
            $status = $this->ensureMetaPhoneActive($assistant->whatsapp_instance, $assistant->whatsapp_token, $pin);

            if ($assistant->whatsapp_pin !== $pin) {
                $assistant->whatsapp_pin = $pin;
                $assistant->save();
            }

            if (!$status) {
                return response()->json(['success' => false, 'message' => 'Não foi possível consultar o status na Meta.'], 422);
            }

            return response()->json(['success' => true] + $status);
        } catch (\Throwable $e) {
            Log::error('Exceção ao consultar status do número na Meta: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao consultar status: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Passo 1 do cadastro manual do numero (fora do popup, por causa do bug da Meta na tela
     * SMS/Ligacao): cria o recurso do numero de telefone no WABA ja conectado. O numero so
     * funciona de verdade depois dos proximos passos (request_code -> verify_code -> register).
     */
    private function addMetaPhoneNumber(Request $request)
    {
        $request->validate([
            'assistant_id' => 'required|exists:assistants,id',
            'cc' => 'required|string',
            'phone_number' => 'required|string',
            'verified_name' => 'required|string|max:100',
        ]);

        $assistant = Assistant::findOrFail($request->input('assistant_id'));
        if (empty($assistant->whatsapp_waba_id) || empty($assistant->whatsapp_token)) {
            return response()->json(['success' => false, 'message' => 'Conecte a conta da Meta antes de cadastrar um número.'], 422);
        }

        try {
            $response = Http::withToken($assistant->whatsapp_token)
                ->post("https://graph.facebook.com/v21.0/{$assistant->whatsapp_waba_id}/phone_numbers", [
                    'cc' => $request->input('cc'),
                    'phone_number' => $request->input('phone_number'),
                    'verified_name' => $request->input('verified_name'),
                ]);

            if (!$response->successful() || !$response->json('id')) {
                Log::error('Erro ao cadastrar número na Meta: ' . $response->body());
                return response()->json(['success' => false, 'message' => $response->json('error.error_user_msg') ?? $response->json('error.message') ?? 'Não foi possível cadastrar esse número.'], 422);
            }

            return response()->json(['success' => true, 'phone_number_id' => $response->json('id')]);
        } catch (\Throwable $e) {
            Log::error('Exceção ao cadastrar número na Meta: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao cadastrar número: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Passo 2: pede o codigo de verificacao pro numero recem-cadastrado, por SMS ou por
     * ligacao - a escolha de verdade (sem o bug da telinha do popup que some em menos de 1s).
     */
    private function requestMetaVerificationCode(Request $request)
    {
        $request->validate([
            'assistant_id' => 'required|exists:assistants,id',
            'phone_number_id' => 'required|string',
            'code_method' => 'required|in:sms,voice',
        ]);

        $assistant = Assistant::findOrFail($request->input('assistant_id'));

        try {
            $response = Http::withToken($assistant->whatsapp_token)
                ->post("https://graph.facebook.com/v21.0/{$request->input('phone_number_id')}/request_code", [
                    'code_method' => $request->input('code_method'),
                    'language' => 'pt_BR',
                ]);

            if (!$response->successful()) {
                Log::error('Erro ao pedir código de verificação (Meta): ' . $response->body());
                return response()->json(['success' => false, 'message' => $response->json('error.error_user_msg') ?? $response->json('error.message') ?? 'Não foi possível enviar o código.'], 422);
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('Exceção ao pedir código de verificação (Meta): ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao pedir código: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Passo 3 e 4: confirma o codigo recebido e, se valido, registra o numero de vez (exige um
     * PIN de 2 fatores - gerado aqui, guardado no assistente pra eventual re-registro futuro).
     * So agora, com o numero realmente ativo, o assistente passa a usar whatsapp_provider='meta'.
     */
    private function verifyMetaCode(Request $request)
    {
        $request->validate([
            'assistant_id' => 'required|exists:assistants,id',
            'phone_number_id' => 'required|string',
            'code' => 'required|string',
        ]);

        $assistant = Assistant::findOrFail($request->input('assistant_id'));
        $phoneNumberId = $request->input('phone_number_id');

        try {
            $verifyResponse = Http::withToken($assistant->whatsapp_token)
                ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/verify_code", [
                    'code' => $request->input('code'),
                ]);

            if (!$verifyResponse->successful()) {
                Log::error('Erro ao verificar código (Meta): ' . $verifyResponse->body());
                return response()->json(['success' => false, 'message' => $verifyResponse->json('error.error_user_msg') ?? $verifyResponse->json('error.message') ?? 'Código inválido ou expirado.'], 422);
            }

            $pin = (string) random_int(100000, 999999);
            if (!$this->registerMetaPhoneWithMeta($phoneNumberId, $assistant->whatsapp_token, $pin)) {
                return response()->json(['success' => false, 'message' => 'Não foi possível concluir o registro do número.'], 422);
            }

            $assistant->whatsapp_provider = 'meta';
            $assistant->whatsapp_instance = $phoneNumberId;
            $assistant->whatsapp_pin = $pin;
            $assistant->save();

            // Autocura: o register() acima as vezes deixa o numero preso em status "Pendente" no
            // WhatsApp Manager (comportamento conhecido e relatado, sem documentacao oficial da
            // Meta sobre o motivo) - confere e repete o register() sozinho se precisar, pra quem
            // esta configurando nunca precisar notar/agir manualmente sobre isso.
            $this->ensureMetaPhoneActive($phoneNumberId, $assistant->whatsapp_token, $pin);

            return response()->json(['success' => true, 'message' => 'Número de WhatsApp conectado e verificado com sucesso!']);
        } catch (\Throwable $e) {
            Log::error('Exceção ao verificar/registrar número (Meta): ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao verificar código: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Webhook unico do App inteiro na Meta (nao tem um por assistente como a UazAPI) - GET e o
     * handshake de verificacao (uma vez, quando voce configura/troca a URL no painel da Meta);
     * POST e mensagem de verdade, roteada pro assistente certo via phone_number_id do payload
     * e entao delegada pro webhook() ja existente (mesma pipeline de IA/resposta de sempre).
     */
    public function webhookMeta(Request $request)
    {
        if ($request->isMethod('get')) {
            $mode = $request->query('hub_mode');
            $verifyToken = $request->query('hub_verify_token');
            $challenge = $request->query('hub_challenge');

            if ($mode === 'subscribe' && $verifyToken && $verifyToken === Setting::getGlobal('meta_webhook_verify_token')) {
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            }

            return response('Forbidden', 403);
        }

        $phoneNumberId = $request->input('entry.0.changes.0.value.metadata.phone_number_id');
        if (!$phoneNumberId) {
            return response()->json(['status' => 'ignored_no_phone_number_id']);
        }

        $assistant = Assistant::where('whatsapp_provider', 'meta')->where('whatsapp_instance', $phoneNumberId)->first();
        if (!$assistant) {
            Log::warning("Webhook da Meta recebido pra phone_number_id desconhecido: {$phoneNumberId}");
            return response()->json(['status' => 'ignored_unknown_number']);
        }

        return $this->webhook($request, $assistant->id);
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
                    $f['added_at'] = now()->toDateTimeString();
                    $exists = true;
                    break;
                }
            }
            unset($f);

            if (!$exists) {
                $files[] = [
                    'name' => '🌐 ' . $url,
                    'path' => null,
                    'content' => $content,
                    'added_at' => now()->toDateTimeString(),
                ];
            }
            
            $assistant->forceFill(['knowledge_files' => array_values($files)])->save();
            return response()->json(['success' => true, 'url' => $url]);
        }

        return response()->json(['success' => false, 'message' => 'Sem conteúdo na página.']);
    }

    /**
     * "Varrer Site" (módulo novo, separado do "Extrair Site" acima): descobre a ESTRUTURA de menu
     * da home (item de topo -> submenu), pra depois cada página virar um arquivo em disco
     * organizado nessa mesma hierarquia (ver crawlMenuPage()). Baixa o HTML cru (não o texto já
     * limpo do fetchContentFromUrl) porque aqui precisamos da árvore DOM do menu, não só do texto.
     */
    private function discoverSiteMenu(Request $request)
    {
        $url = trim($request->input('website_url'));
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }

        $domain = parse_url($url, PHP_URL_HOST);
        if (!$domain) return response()->json(['success' => false, 'message' => 'URL inválida.']);
        $domainStr = str_replace('www.', '', $domain);

        try {
            $response = Http::timeout(15)->get($url);
            if (!$response->successful()) {
                return response()->json(['success' => false, 'message' => 'Falha ao acessar o site.']);
            }
            $html = $response->body();
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Falha ao acessar o site.']);
        }

        $tree = $this->parseMenuTree($html, $url, $domainStr);

        if (empty($tree)) {
            // Nenhuma estrutura de menu reconhecível (site sem <nav> semântico, mega-menu via JS,
            // etc.) - cai pro mesmo link-scan simples do "Extrair Site", sem hierarquia.
            $content = $this->fetchContentFromUrl($url);
            $flatLinks = $content ? $this->extractLinksFromText($content) : [];
            $cleanLinks = [];
            foreach ($flatLinks as $link) {
                $link = rtrim(explode('#', explode('?', $link)[0])[0], '/');
                $linkDomain = parse_url($link, PHP_URL_HOST);
                if ($linkDomain && str_ends_with(str_replace('www.', '', $linkDomain), $domainStr)
                    && !preg_match('/\.(jpg|jpeg|png|gif|pdf|zip|rar|mp4|mp3|css|js|svg|webp|doc|docx|xml)$/i', $link)
                    && !in_array($link, $cleanLinks)) {
                    $cleanLinks[] = $link;
                }
            }
            $cleanLinks = array_slice($cleanLinks, 0, 150);
            $tree = [];
            foreach ($cleanLinks as $link) {
                $label = trim(parse_url($link, PHP_URL_PATH) ?: '', '/');
                $label = $label !== '' ? ucwords(str_replace(['-', '_', '/'], ' ', $label)) : 'Home';
                $tree[] = ['label' => $label, 'url' => $link, 'children' => []];
            }
        }

        return response()->json(['success' => true, 'domain' => $domainStr, 'tree' => $tree]);
    }

    /**
     * Extrai a árvore de menu (topo -> submenu, 2 níveis) de um HTML cru via DOMDocument/DOMXPath.
     * Procura um <nav> primeiro; se não achar, tenta um elemento cujo id/class contenha "menu".
     * Retorna [] se não conseguir identificar nada reconhecível (deixa o chamador cair no fallback).
     */
    private function parseMenuTree(string $html, string $homeUrl, string $domainStr): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $navNodes = $xpath->query('//nav');
        if ($navNodes === false || $navNodes->length === 0) {
            $navNodes = $xpath->query('//*[contains(translate(@class, "MENU", "menu"), "menu") or contains(translate(@id, "MENU", "menu"), "menu")]');
        }
        if ($navNodes === false || $navNodes->length === 0) return [];

        $nav = $navNodes->item(0);
        $topItems = $xpath->query('.//li[parent::ul[1]]', $nav);
        if ($topItems === false || $topItems->length === 0) {
            $topItems = $xpath->query('.//a', $nav);
        }

        $tree = [];
        $seenUrls = [];
        $totalCount = 0;

        foreach ($topItems as $node) {
            if ($totalCount >= 150) break;

            $isAnchor = $node->nodeName === 'a';
            $anchorNode = $isAnchor ? $node : $xpath->query('.//a', $node)->item(0);
            if (!$anchorNode) continue;

            $absoluteUrl = $this->resolveMenuUrl($anchorNode->getAttribute('href'), $homeUrl);
            $label = trim($anchorNode->textContent);
            if (!$absoluteUrl || $label === '' || in_array($absoluteUrl, $seenUrls)) continue;

            $linkDomain = parse_url($absoluteUrl, PHP_URL_HOST);
            if (!$linkDomain || !str_ends_with(str_replace('www.', '', $linkDomain), $domainStr)) continue;

            $seenUrls[] = $absoluteUrl;
            $children = [];

            if (!$isAnchor) {
                $subItems = $xpath->query('.//ul//a', $node);
                foreach ($subItems as $subAnchor) {
                    if ($totalCount >= 150) break;
                    $subUrl = $this->resolveMenuUrl($subAnchor->getAttribute('href'), $homeUrl);
                    $subLabel = trim($subAnchor->textContent);
                    if (!$subUrl || $subLabel === '' || in_array($subUrl, $seenUrls)) continue;
                    $subDomain = parse_url($subUrl, PHP_URL_HOST);
                    if (!$subDomain || !str_ends_with(str_replace('www.', '', $subDomain), $domainStr)) continue;
                    $seenUrls[] = $subUrl;
                    $children[] = ['label' => $subLabel, 'url' => $subUrl, 'children' => []];
                    $totalCount++;
                }
            }

            $tree[] = ['label' => $label, 'url' => $absoluteUrl, 'children' => $children];
            $totalCount++;
        }

        return $tree;
    }

    private function resolveMenuUrl(string $href, string $baseUrl): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')
            || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return rtrim(explode('#', explode('?', $href)[0])[0], '/');
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';

        if (str_starts_with($href, '//')) {
            return rtrim(explode('#', explode('?', $scheme . ':' . $href)[0])[0], '/');
        }
        if (str_starts_with($href, '/')) {
            return rtrim(explode('#', explode('?', $scheme . '://' . $host . $href)[0])[0], '/');
        }

        $basePath = rtrim(dirname($base['path'] ?? '/'), '/');
        return rtrim(explode('#', explode('?', $scheme . '://' . $host . $basePath . '/' . $href)[0])[0], '/');
    }

    /**
     * Busca o conteúdo de UMA página do "Varrer Site" e salva num arquivo real em disco,
     * organizado pela hierarquia de menu (menu_path) - ex: site_crawls/5/inhouse.com.br/
     * produtos/insoft-omni.txt. Registra os metadados em crawled_pages e adiciona/atualiza a
     * entrada correspondente em knowledge_files, pra entrar no prompt da IA como qualquer outro
     * documento (buildSystemPromptWithKnowledge não precisa saber que isso existe).
     */
    private function crawlMenuPage(Request $request)
    {
        $assistant = Assistant::findOrFail($request->assistant_id);
        $url = trim($request->input('website_url'));
        $menuPath = $request->input('menu_path', []);
        if (!is_array($menuPath)) $menuPath = [];

        $content = $this->fetchContentFromUrl($url);
        if (!$content) {
            return response()->json(['success' => false, 'message' => 'Sem conteúdo na página.', 'url' => $url]);
        }

        $domain = parse_url($url, PHP_URL_HOST) ?: 'site';
        $domainStr = str_replace('www.', '', $domain);

        $slugs = array_map(fn($seg) => Str::slug((string) $seg) ?: 'pagina', $menuPath);
        if (empty($slugs)) $slugs = ['home'];
        $fileName = array_pop($slugs) . '.txt';
        $relativeDir = 'site_crawls/' . $assistant->id . '/' . $domainStr . (count($slugs) ? '/' . implode('/', $slugs) : '');
        $relativePath = $relativeDir . '/' . $fileName;

        Storage::makeDirectory($relativeDir);
        Storage::put($relativePath, $content);

        $now = now();
        CrawledPage::updateOrCreate(
            ['assistant_id' => $assistant->id, 'file_path' => $relativePath],
            ['url' => $url, 'menu_path' => $menuPath, 'content_size' => strlen($content), 'crawled_at' => $now]
        );

        $displayName = $menuPath ? implode(' › ', $menuPath) : $domainStr;
        $files = is_array($assistant->knowledge_files) ? $assistant->knowledge_files : [];
        $exists = false;
        foreach ($files as &$f) {
            if (($f['path'] ?? null) === $relativePath) {
                $f['content'] = $content;
                $f['name'] = $displayName;
                $f['added_at'] = $now->toDateTimeString();
                $exists = true;
                break;
            }
        }
        unset($f);
        if (!$exists) {
            $files[] = ['name' => $displayName, 'path' => $relativePath, 'content' => $content, 'added_at' => $now->toDateTimeString()];
        }
        $assistant->forceFill(['knowledge_files' => array_values($files)])->save();

        return response()->json(['success' => true, 'url' => $url, 'name' => $displayName]);
    }

    /**
     * CSV da tela "Ver Base de Conhecimento" - mesmo padrão de SurveyController::exportResponsesCsv().
     */
    private function exportKnowledgeBaseCsv(Request $request)
    {
        $assistant = Assistant::findOrFail($request->query('assistant_id'));
        $rows = $this->buildKnowledgeBaseRows($assistant);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nome', 'Tipo', 'Tamanho (bytes)', 'Data/Hora'], ';');
            foreach ($rows as $row) {
                fputcsv($out, [$row['name'], $row['type'], $row['size'] ?? '', $row['crawled_at'] ?? ''], ';');
            }
            fclose($out);
        }, 'base_conhecimento_' . $assistant->id . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
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

        $uploadSkippedDuplicate = [];
        $uploadSkippedOlder = [];
        $uploadReplaced = [];
        $uploadAdded = [];

        if ($request->hasFile('documents')) {
            $uploadedFiles = $request->file('documents');
            if (!is_array($uploadedFiles)) {
                $uploadedFiles = [$uploadedFiles];
            }
            $clientModifiedTimes = $request->input('document_modified_at', []);

            foreach ($uploadedFiles as $i => $file) {
                if (!$file || !$file->isValid()) {
                    continue;
                }

                $fileName = $file->getClientOriginalName();
                $fileSize = $file->getSize();
                $clientModifiedAt = (int) ($clientModifiedTimes[$i] ?? 0);

                $existingIndex = null;
                foreach ($existingFiles as $idx => $existing) {
                    if (($existing['name'] ?? null) === $fileName) {
                        $existingIndex = $idx;
                        break;
                    }
                }

                if ($existingIndex !== null) {
                    $existing = $existingFiles[$existingIndex];
                    $existingModifiedAt = $existing['file_modified_at'] ?? null;
                    $existingSize = $existing['file_size'] ?? null;

                    if ($existingModifiedAt !== null && $existingSize !== null) {
                        if ($fileSize === $existingSize && $clientModifiedAt === $existingModifiedAt) {
                            $uploadSkippedDuplicate[] = $fileName;
                            continue;
                        }
                        if ($clientModifiedAt < $existingModifiedAt) {
                            $uploadSkippedOlder[] = $fileName;
                            continue;
                        }
                    }

                    // Substitui: remove a versao anterior do storage e do array antes de gravar a nova.
                    if (!empty($existing['path'])) {
                        Storage::delete($existing['path']);
                    }
                    array_splice($existingFiles, $existingIndex, 1);
                    $uploadReplaced[] = $fileName;
                } else {
                    $uploadAdded[] = $fileName;
                }

                try {
                    $path = $file->store('knowledge_base');
                    $fullPath = Storage::path($path);
                    $extractedText = $this->extractTextFromFile($fullPath, $fileName);

                    $existingFiles[] = [
                        'name' => $fileName,
                        'path' => $path,
                        'content' => $extractedText,
                        'added_at' => now()->toDateTimeString(),
                        'file_size' => $fileSize,
                        'file_modified_at' => $clientModifiedAt,
                    ];
                    $hasKnowledgeChanges = true;
                } catch (\Throwable $e) {
                    Log::error('Erro no anexo ' . $fileName . ': ' . $e->getMessage());
                }
            }
        }

        if ($hasKnowledgeChanges) {
            $data['knowledge_files'] = array_values($existingFiles);
        }

        $assistant->forceFill($data)->save();

        if ($request->expectsJson()) {
            $messageParts = [];
            if ($uploadAdded) $messageParts[] = count($uploadAdded) . ' arquivo(s) anexado(s)';
            if ($uploadReplaced) $messageParts[] = count($uploadReplaced) . ' atualizado(s) para a versão mais recente';
            if ($uploadSkippedDuplicate) $messageParts[] = count($uploadSkippedDuplicate) . ' ignorado(s) por já existir(em) sem alteração';
            if ($uploadSkippedOlder) $messageParts[] = count($uploadSkippedOlder) . ' ignorado(s) por ser(em) mais antigo(s) que a versão já cadastrada';

            return response()->json([
                'success' => true,
                'message' => $messageParts ? implode(', ', $messageParts) . '.' : 'Configurações atualizadas!',
            ]);
        }

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

        $status = $request->input('status');
        if (!in_array($status, ['active', 'inactive', 'maintenance'], true)) {
            // Compatibilidade com o botao antigo (sem seletor de status): simples alterna ativo/inativo.
            $status = $assistant->is_active ? 'inactive' : 'active';
        }

        $assistant->status = $status;
        $assistant->is_active = ($status === 'active');
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

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Fontes de conhecimento removidas com sucesso.']);
            }

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

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Arquivo/URL removido.']);
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
        // Calendário pronto dos próximos dias: NÃO calcule de cabeça em que dia da semana cai uma
        // data (é um erro comum e recorrente) - sempre consulte esta lista antes de responder algo
        // como "próxima segunda", "quinta que vem" etc.
        $prompt .= "• Calendário dos próximos 14 dias (use para responder \"próxima segunda\", \"quinta que vem\" etc. - NUNCA calcule de cabeça):\n";
        for ($i = 1; $i <= 14; $i++) {
            $futureDay = $now->copy()->addDays($i);
            $futureDiaPt = $diasSemana[$futureDay->format('l')] ?? $futureDay->format('l');
            $prompt .= "   " . $futureDay->format('d/m/Y') . " = {$futureDiaPt}\n";
        }
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

                $prompt .= "2. FINALIZAÇÃO DO AGENDAMENTO - REGRA ABSOLUTA, SEM EXCEÇÃO:\n";
                $prompt .= "No exato momento em que o cliente confirmar que NÃO há mais e-mails/convidados a adicionar (ex: responder \"não\", \"pode ser só eu\", \"sem mais ninguém\", etc.), você é OBRIGADA a emitir a tag abaixo NA MESMA MENSAGEM DE RESPOSTA - nunca deixe pra depois, nunca pule essa etapa, e NUNCA diga que a reunião foi agendada/confirmada sem emitir essa tag: é ela que aciona o sistema que realmente cria o evento e manda os convites. Se você disser \"confirmado\" sem emitir a tag, NENHUM convite é enviado e o cliente fica sem reunião de verdade.\n";
                $prompt .= "Emita no final da mensagem: [AGENDAR_REUNIAO: departamento=\"NOME_DO_SETOR\", data_hora_inicio=\"YYYY-MM-DD HH:MM:SS\", email_cliente=\"email@cliente.com\", emails_adicionais=\"email1@...,email2@...\"]\n";
                $prompt .= "O sistema, ao processar essa tag, substitui automaticamente sua mensagem pelo resumo real da reunião (com atendente, setor, data e confirmação de convites enviados) - você não precisa escrever esse resumo você mesma, só precisa garantir que a tag seja emitida nesse momento exato.\n\n";

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
                $content = $this->stripSourceLines($content);

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

    /**
     * Prompt enxuto pro módulo Testador: sem os módulos de agendamento/pesquisa/lead-fields (que
     * não fazem sentido fora de um atendimento real e dependem de queries por assistant_id de
     * verdade) - só o contexto temporal genérico + a persona do cenário + o prompt/base
     * "congelados" do assistente alvo (pra IA poder mirar propositalmente nos pontos fracos, como
     * um QA humano leria o prompt do outro lado) + o histórico da conversa deste cenário. O
     * histórico entra formatado dentro do próprio prompt (em vez de usar o parâmetro $history do
     * callAiApi) porque esse parâmetro é ignorado pelos providers Gemini/Anthropic - embutir aqui
     * garante contexto completo em qualquer provider configurado pro número de teste.
     */
    public function buildTestHarnessPrompt(
        string $personaInstruction,
        string $targetPromptSnapshot,
        ?string $targetKnowledgeSnapshot,
        array $conversationHistory
    ): string {
        $tz = $this->getTimezone();
        $now = Carbon::now($tz);

        $diasSemana = [
            'Sunday' => 'Domingo', 'Monday' => 'Segunda-feira', 'Tuesday' => 'Terça-feira',
            'Wednesday' => 'Quarta-feira', 'Thursday' => 'Quinta-feira', 'Friday' => 'Sexta-feira', 'Saturday' => 'Sábado',
        ];
        $diaPt = $diasSemana[$now->format('l')] ?? $now->format('l');

        $prompt = "===============================================\n";
        $prompt .= "CONTEXTO TEMPORAL (OBRIGATÓRIO):\n";
        $prompt .= "• Data e Hora Atual: " . $now->format('d/m/Y \à\s H:i:s') . " ({$diaPt})\n";
        $prompt .= "• Ano Corrente: " . $now->year . "\n";
        $prompt .= "===============================================\n\n";

        $prompt .= "IDENTIDADE\n";
        $prompt .= "Você NÃO é um assistente de atendimento - você é um ATOR de QA simulando uma conversa real de WhatsApp pra testar outro assistente de IA. Seu objetivo é agir de forma 100% crível como o tipo de cliente descrito abaixo, nunca revelar que é um teste, e sondar deliberadamente os pontos fracos do outro assistente.\n\n";

        $prompt .= "SEU PERSONAGEM NESTE CENÁRIO\n";
        $prompt .= $personaInstruction . "\n\n";

        $prompt .= "REGRA DE CONCLUSÃO\n";
        $prompt .= "Quando você considerar que já testou o suficiente desse cenário (objetivo cumprido, o outro assistente já reagiu de forma clara ao que você queria sondar, ou a conversa natural chegou a um fim), termine sua mensagem com a tag [CENARIO_CONCLUIDO] no final, sem nenhum texto depois dela. Não encerre cedo demais - dê ao outro assistente pelo menos 2-3 trocas de mensagem antes de concluir, a menos que ele já tenha revelado claramente o que você queria testar.\n\n";

        $prompt .= "===============================================\n";
        $prompt .= "PROMPT DO ASSISTENTE SENDO TESTADO (contexto só pra você mirar os testes - NUNCA mencione que viu isso)\n";
        $prompt .= "===============================================\n";
        $prompt .= $targetPromptSnapshot . "\n\n";

        if (!empty($targetKnowledgeSnapshot)) {
            $prompt .= "===============================================\n";
            $prompt .= "BASE DE CONHECIMENTO DO ASSISTENTE SENDO TESTADO (idem - só referência sua)\n";
            $prompt .= "===============================================\n";
            $prompt .= $targetKnowledgeSnapshot . "\n\n";
        }

        if (!empty($conversationHistory)) {
            $prompt .= "===============================================\n";
            $prompt .= "HISTÓRICO DA CONVERSA NESTE CENÁRIO ATÉ AGORA\n";
            $prompt .= "===============================================\n";
            foreach ($conversationHistory as $turn) {
                $label = ($turn['role'] ?? '') === 'target' ? 'ASSISTENTE TESTADO' : 'VOCÊ (testador)';
                $prompt .= "[{$label}]: " . ($turn['content'] ?? '') . "\n";
            }
            $prompt .= "\nGere agora sua PRÓXIMA mensagem como o testador, respondendo à última fala do assistente testado acima. Responda só com o texto da mensagem (e a tag de conclusão se for o caso) - nada de comentário fora do personagem.\n";
        } else {
            $prompt .= "Esta é a primeira mensagem da conversa - gere a mensagem de abertura do seu personagem, como se estivesse mandando um WhatsApp pela primeira vez. Responda só com o texto da mensagem.\n";
        }

        return $prompt;
    }

    /**
     * O prompt instrui a IA a nunca citar "Fonte:" numa resposta, mas alguns documentos da base
     * de conhecimento trazem essa linha no proprio texto (ex: credito da origem do conteudo) - se
     * a IA for fiel ao texto literal do documento, corre o risco de vazar isso mesmo assim. Trava
     * em codigo, removendo a linha antes dela entrar no prompt, pra nao depender só da IA obedecer.
     */
    private function stripSourceLines(string $content): string
    {
        if ($content === '') {
            return $content;
        }
        return trim(preg_replace('/^\s*Fonte:.*$/im', '', $content));
    }

    protected function extractTextFromFile(string $filePath, string $fileName): string
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

    protected function sanitizeText($text, int $limit = 8000): string
    {
        if (!is_string($text) || empty($text)) return '';
        $clean = @mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $clean);
        return trim(mb_substr($clean, 0, $limit));
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

    /**
     * A Meta só manda o ID da mídia no payload do webhook, nunca a URL - precisa de uma
     * chamada extra à Graph API pra resolver o ID numa URL de download temporária (que
     * ainda exige o mesmo Bearer token do App pra baixar o binário de fato).
     */
    private function resolveMetaMediaUrl(string $mediaId, ?string $token): ?string
    {
        if (empty($token)) {
            return null;
        }

        try {
            $response = Http::withToken(trim($token))->get('https://graph.facebook.com/v21.0/' . $mediaId);
            if ($response->successful()) {
                return $response->json('url');
            }
        } catch (\Throwable $e) {
            Log::error('Erro ao resolver mídia da Meta (ID ' . $mediaId . '): ' . $e->getMessage());
        }

        return null;
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
            $assistantStatus = $assistant ? ($assistant->status ?? ($assistant->is_active ? 'active' : 'inactive')) : null;
            if (!$assistant || $assistantStatus === 'inactive') {
                return response()->json(['status' => 'ignored']);
            }

            $rawSender = $request->input('entry.0.changes.0.value.messages.0.from')
                ?? $request->input('message.sender_pn')
                ?? $request->input('message.chatid')
                ?? $request->input('chat.phone')
                ?? $request->input('chat.wa_chatid')
                ?? $request->input('data.key.remoteJid')
                ?? $request->input('key.remoteJid')
                ?? $request->input('phone')
                ?? $request->input('from')
                ?? $request->input('sender')
                ?? 'desconhecido';

            // Webhook da Meta sem mensagem (ex: apenas confirmação de entrega/leitura em "statuses")
            // não tem "messages" no payload - ignora silenciosamente, não é uma mensagem de cliente.
            if ($assistant->whatsapp_provider === 'meta' && !$request->filled('entry.0.changes.0.value.messages.0.from')) {
                return response()->json(['status' => 'ignored_no_message']);
            }

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
            $messageId = $request->input('entry.0.changes.0.value.messages.0.id')
                ?? $request->input('message.id')
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

            // Assistente em manutencao: nao processa IA, so responde com a mensagem fixa. Nunca se
            // aplica a um numero do modulo Testador (is_test_harness) - mesmo que alguem deixe o
            // status dele como "maintenance" sem querer via o toggle generico, isso nao pode
            // quebrar silenciosamente um teste em andamento.
            if ($assistantStatus === 'maintenance' && !$assistant->is_test_harness) {
                $maintenanceMessage = 'Este assistente está em manutenção no momento. Pedimos desculpas pelo transtorno, tente novamente mais tarde.';
                $this->sendWhatsappMessage($assistant, $sendTarget, $maintenanceMessage);

                $nowFormatted = now()->format('Y-m-d H:i:s');
                DB::table('chat_messages')->insert([
                    'assistant_id' => $assistant->id,
                    'phone_number' => $cleanSender,
                    'protocol' => null,
                    'role' => 'assistant',
                    'content' => $maintenanceMessage,
                    'created_at' => $nowFormatted,
                    'updated_at' => $nowFormatted,
                ]);

                return response()->json(['status' => 'maintenance_reply_sent']);
            }

            // Automação de retomada: o cliente acabou de falar, então zera o relógio de silêncio
            // (se a automação estiver ligada pra esse assistente) - não importa em qual fluxo o
            // resto do webhook vai cair (mensagem normal, mídia, resposta de pesquisa etc.).
            if (Setting::where('assistant_id', $assistant->id)->where('key', 'automation_enabled')->value('value') === '1') {
                AutomationFollowup::updateOrCreate(
                    ['assistant_id' => $assistant->id, 'phone_number' => $cleanSender],
                    ['send_target' => $sendTarget, 'attempts_sent' => 0, 'last_activity_at' => now()]
                );
            }

            $audioService = new \App\Services\AudioService();

            $msgType = strtolower(
                $request->input('entry.0.changes.0.value.messages.0.type')
                ?? $request->input('message.mediaType')
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

            // A Meta não manda a URL da mídia direto no payload, só um ID que precisa ser
            // resolvido via Graph API (e a URL resultante exige o Bearer token pra baixar).
            if ($assistant->whatsapp_provider === 'meta' && empty($mediaUrl) && in_array($msgType, ['image', 'audio', 'video', 'document', 'sticker'])) {
                $metaMediaId = $request->input('entry.0.changes.0.value.messages.0.' . $msgType . '.id');
                if ($metaMediaId) {
                    $mediaUrl = $this->resolveMetaMediaUrl($metaMediaId, $assistant->whatsapp_token);
                }
            }

            $isAudioMessage = in_array($msgType, ['ptt', 'audio', 'audiomessage', 'voice']) 
                || (!empty($mediaUrl) && (str_contains($mediaUrl, '.og') || str_contains($mediaUrl, '.mp3') || str_contains($mediaUrl, 'audio')));

            $isMediaMessage = $isAudioMessage 
                || in_array($msgType, ['image', 'video', 'document', 'sticker', 'imagemessage', 'videomessage', 'documentmessage', 'documentwithcaptionmessage']) 
                || (!empty($mediaUrl) && str_contains($mediaUrl, 'http'));

            $rawMessage = $request->input('entry.0.changes.0.value.messages.0.text.body')
                ?? $request->input('entry.0.changes.0.value.messages.0.button.text')
                ?? $request->input('entry.0.changes.0.value.messages.0.interactive.button_reply.title')
                ?? $request->input('entry.0.changes.0.value.messages.0.interactive.list_reply.title')
                ?? $request->input('message.content')
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

            // Guarda o texto "limpo" (sem o embrulho "Mensagem de Voz: ... 🔊 Link do Áudio: ...",
            // usado só pra exibição no histórico) - é isso que deve ir pro casamento de resposta de
            // pesquisa (ver mais abaixo), senão uma resposta por áudio tipo "Oito" chega lá como toda
            // aquela string formatada e não casa com nenhuma opção nem faz sentido como texto livre.
            $cleanUserMessage = $userMessage;

            // 🤖 NÚMERO DE TESTE (módulo Testador): este assistant nunca atende cliente de verdade -
            // é só o veículo de envio/recebimento do teste automatizado. Pula todo o pipeline normal
            // (mídia, debounce, Omni, histórico de chat_messages, chamada de IA do atendimento) e
            // desvia pro fluxo próprio do TestRun 'running' que estiver esperando resposta deste número.
            if ($assistant->is_test_harness) {
                $testRun = TestRun::where('harness_assistant_id', $assistant->id)
                    ->where('status', 'running')
                    ->latest('started_at')
                    ->first();

                if ($testRun) {
                    app(TestHarnessService::class)->handleTargetReply($this, $testRun, $cleanSender, $sendTarget, $cleanUserMessage);
                }

                return response()->json(['status' => 'test_harness_processed']);
            }

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
                            // URLs de mídia da Meta não são públicas - exigem o mesmo Bearer token do App pra baixar o binário.
                            $dl = $assistant->whatsapp_provider === 'meta'
                                ? Http::withToken(trim($assistant->whatsapp_token))->timeout(25)->get($mediaUrl)
                                : Http::timeout(25)->get($mediaUrl);
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
                    // Recaptura aqui, pois pra áudio $userMessage só virou o texto transcrito
                    // ("Oito") depois da captura lá em cima - agora sim reflete a transcrição real.
                    $cleanUserMessage = $caption;
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
                ?? $request->input('entry.0.changes.0.value.contacts.0.profile.name')
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
                // Usa o texto limpo (pré-embrulho de áudio), não $userMessage - por essa altura
                // $userMessage já pode estar formatado como "Mensagem de Voz: \"Oito\"\n🔊 Link do
                // Áudio: ..." pra exibição no histórico, o que quebra o casamento com as opções.
                if ($pendingSurveyResponse->status === 'awaiting_confirmation') {
                    $this->handleSurveyConfirmation($assistant, $pendingSurveyResponse, $cleanUserMessage);
                } else {
                    $this->handleSurveyAnswer($assistant, $pendingSurveyResponse, $cleanUserMessage);
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

            // 🕓 DEBOUNCE: agrupa mensagens de texto mandadas em sequência rápida (ex: cliente
            // digita "Isso" e, dois segundos depois, o e-mail, como duas mensagens separadas) num
            // único turno de IA - evita que cada uma dispare sua própria resposta em paralelo, uma
            // "pisando" na outra. Não se aplica a mídia (já tratada acima) nem a resposta de
            // pesquisa (já retornou antes de chegar aqui).
            if (!$isMediaMessage) {
                $debounceToken = Str::random(20);

                $existingBuffer = PendingMessageBuffer::where('assistant_id', $assistant->id)->where('phone_number', $cleanSender)->first();
                $combinedText = $existingBuffer ? trim($existingBuffer->buffered_text . "\n" . $userMessage) : $userMessage;

                PendingMessageBuffer::updateOrCreate(
                    ['assistant_id' => $assistant->id, 'phone_number' => $cleanSender],
                    ['buffered_text' => $combinedText, 'token' => $debounceToken]
                );

                sleep(self::DEBOUNCE_SECONDS);

                $currentBuffer = PendingMessageBuffer::where('assistant_id', $assistant->id)->where('phone_number', $cleanSender)->first();

                if (!$currentBuffer || $currentBuffer->token !== $debounceToken) {
                    // Chegou mensagem mais nova durante a espera - essa requisição perdeu a
                    // corrida, quem vai processar o lote é a mais recente.
                    return response()->json(['status' => 'debounced']);
                }

                $userMessage = trim($currentBuffer->buffered_text);
                $currentBuffer->delete();
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

            // Guarda/atualiza o nome conhecido desse número (pushName do WhatsApp, ou o nome
            // resolvido pelo Omni acima, se houver) - usado só pra exibir na tela de Conversas.
            if ($displayName !== 'Cliente') {
                WaContactName::updateOrCreate(
                    ['assistant_id' => $assistant->id, 'phone_number' => $cleanSender],
                    ['name' => $displayName]
                );
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

            // 🛑 REDE DE SEGURANÇA: a IA às vezes afirma que agendou/confirmou/cancelou uma reunião e
            // já mandou o convite, em texto livre, SEM emitir a tag técnica que de fato aciona o
            // Google Calendar - resultado: ela mente pro cliente que está tudo certo e nenhum convite
            // é enviado de verdade. Reforçar a instrução no prompt não bastou (aconteceu de novo mesmo
            // depois disso), então aqui detectamos essa alegação falsa e forçamos uma segunda tentativa
            // pedindo pra ela emitir a tag de verdade, antes de deixar essa resposta ir pro cliente.
            $hasAnySchedulingTagYet = (bool) preg_match('/\[(VERIFICAR_AGENDA|AGENDAR_REUNIAO|CANCELAR_REUNIAO|REAGENDAR_REUNIAO)\s*:/is', $aiReply);
            if (!$hasAnySchedulingTagYet && preg_match('/\b(reuni[ãa]o (foi )?agendada|reuni[ãa]o confirmada|convite (foi |j[áa] )?enviado|agendei (a |sua )?reuni[ãa]o|confirmei (a |sua )?reuni[ãa]o)\b/iu', $aiReply)) {
                try {
                    $retryReply = $this->callAiApi(
                        $assistant,
                        $systemPrompt,
                        '[SISTEMA: Na sua última resposta você disse que a reunião foi agendada/confirmada e que um convite foi enviado, mas você NÃO emitiu a tag técnica [AGENDAR_REUNIAO:...] (ou [CANCELAR_REUNIAO:...]/[REAGENDAR_REUNIAO:...], conforme o caso) necessária pra isso realmente acontecer - ou seja, NADA foi agendado de verdade e NENHUM convite foi enviado. Refaça agora essa resposta, dessa vez EMITINDO CORRETAMENTE a tag correspondente, com os dados já confirmados nesta conversa (departamento, data/hora, e-mail do cliente).]',
                        $history
                    );
                    if (preg_match('/\[(VERIFICAR_AGENDA|AGENDAR_REUNIAO|CANCELAR_REUNIAO|REAGENDAR_REUNIAO)\s*:/is', $retryReply)) {
                        $aiReply = $retryReply;
                    }
                } catch (\Throwable $e) {
                    Log::error('Erro na segunda tentativa de emitir tag de agendamento: ' . $e->getMessage());
                }
            }

            // 🛑 DETECÇÃO BLINDADA: Identifica tags de agendamento (ignorando maiúsculas, espaços e quebras de linha)
            $hasSchedulingTag = preg_match('/\[(VERIFICAR_AGENDA|AGENDAR_REUNIAO|CANCELAR_REUNIAO|REAGENDAR_REUNIAO)\s*:/is', $aiReply);

            // PROCESSA TAGS DE AGENDAMENTO
            if (method_exists($this, 'processAppointmentTag')) {
                $aiReply = $this->processAppointmentTag($assistant, $aiReply, $displayName, $cleanSender);
            }

            // 🛑 FORÇA TEXTO: Se houver tag de agendamento OU se a resposta contiver termos de confirmação da reunião
            // (o modificador /u é essencial aqui - sem ele, o /i não faz o "case-fold" direito de
            // letras acentuadas tipo Ã/ã, e "Reunião confirmada" em case normal não batia com o
            // padrão em maiúsculas, mesmo sendo "case insensitive").
            if ($hasSchedulingTag || preg_match('/(reuni[ãa]o confirmada|reuni[ãa]o cancelada|reuni[ãa]o reagendada|est[áa] \*?livre\*?|google meet|atendente:)/iu', $aiReply)) {
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
                        // Tira a frase fixa de despedida ("Agradecemos por entrar em contato...")
                        // dessa mensagem - ela vai ser reenviada sozinha pelo sendAiGeneratedClosing()
                        // depois que o cliente responder a oferta da pesquisa (aceitar/recusar), e
                        // sem isso a despedida saía duplicada (uma aqui, outra ali). Preserva
                        // qualquer conteúdo substancial que viesse antes dela na mesma mensagem (ex:
                        // resumo de um agendamento confirmado - atendente, setor, link do Meet).
                        $aiReply = trim(preg_replace('/Agradecemos por entrar em contato com a InHouse\.[\s\S]*?Tenha um[a]? ótim[oa] (dia|tarde|noite)!?/iu', '', $aiReply));
                        $aiReply = trim($aiReply . "\n\nAntes de finalizarmos, você poderia nos ajudar respondendo uma breve pesquisa de satisfação, bem rapidinha, aqui mesmo pelo WhatsApp?");
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

    public function callAiApi(Assistant $assistant, string $systemPrompt, string $userMessage, array $history = []): string
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
    protected function normalizeWaTarget(string $to): string
    {
        return str_contains($to, '@') ? $to : preg_replace('/[^0-9]/', '', $to);
    }

    public function sendWhatsappMessage(Assistant $assistant, string $to, string $message): array
    {
        if ($assistant->whatsapp_provider === 'meta') {
            if (empty($assistant->whatsapp_instance) || empty($assistant->whatsapp_token)) {
                return ['success' => false, 'error' => 'WhatsApp (Meta) não configurado.'];
            }

            try {
                $cleanTo = $this->normalizeWaTarget($to);
                $response = Http::withToken(trim($assistant->whatsapp_token))
                    ->post('https://graph.facebook.com/v21.0/' . $assistant->whatsapp_instance . '/messages', [
                        'messaging_product' => 'whatsapp',
                        'to' => $cleanTo,
                        'type' => 'text',
                        'text' => ['body' => $message],
                    ]);

                return ['success' => $response->successful(), 'error' => $response->failed() ? $response->body() : null];
            } catch (\Throwable $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

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
     * Envia um Modelo de Mensagem (Template) ja aprovado pela Meta - diferente de
     * sendWhatsappMessage() (texto livre), que a Meta so aceita dentro da janela de 24h apos a
     * ultima mensagem do cliente. Mensagens proativas (retomada de atendimento parado, que por
     * natureza acontecem fora dessa janela) sao OBRIGADAS a usar template. So existe pra Meta -
     * UazAPI nao tem esse conceito/restricao.
     */
    private function sendWhatsappTemplate(Assistant $assistant, string $to, string $templateName, string $language, ?string $clientName = null): array
    {
        if (empty($assistant->whatsapp_instance) || empty($assistant->whatsapp_token)) {
            return ['success' => false, 'error' => 'WhatsApp (Meta) não configurado.'];
        }

        try {
            $cleanTo = $this->normalizeWaTarget($to);
            $template = [
                'name' => $templateName,
                'language' => ['code' => $language],
            ];

            // {{1}} no corpo do template = nome do cliente (unica variavel suportada por enquanto).
            // So manda o componente "body" com parametro se o admin realmente usou {{1}} no texto -
            // mandar parametro pra um template sem variavel faria a Meta rejeitar o envio.
            if ($clientName !== null) {
                $template['components'] = [[
                    'type' => 'body',
                    'parameters' => [['type' => 'text', 'text' => $clientName]],
                ]];
            }

            $response = Http::withToken(trim($assistant->whatsapp_token))
                ->post('https://graph.facebook.com/v21.0/' . $assistant->whatsapp_instance . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $cleanTo,
                    'type' => 'template',
                    'template' => $template,
                ]);

            return ['success' => $response->successful(), 'error' => $response->failed() ? $response->body() : null];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Lista os Modelos de Mensagem (Templates) ja cadastrados no WABA do assistente - usado na
     * tela de Automacao pra montar o seletor de templates aprovados, em vez de texto livre.
     */
    private function listMetaTemplates(Request $request)
    {
        $request->validate(['assistant_id' => 'required|exists:assistants,id']);

        $assistant = Assistant::findOrFail($request->input('assistant_id'));
        if (empty($assistant->whatsapp_waba_id) || empty($assistant->whatsapp_token)) {
            return response()->json(['success' => false, 'message' => 'Esse assistente não tem uma conta Meta conectada.'], 422);
        }

        try {
            $response = Http::withToken($assistant->whatsapp_token)
                ->get("https://graph.facebook.com/v21.0/{$assistant->whatsapp_waba_id}/message_templates", [
                    'fields' => 'id,name,language,status,category,components,rejected_reason',
                    'limit' => 100,
                ]);

            if (!$response->successful()) {
                Log::error('Erro ao listar templates do WABA na Meta: ' . $response->body());
                $metaMessage = $response->json('error.error_user_msg') ?? $response->json('error.message');
                return response()->json([
                    'success' => false,
                    'message' => 'Não foi possível listar os templates.' . ($metaMessage ? ' Meta: ' . $metaMessage : ''),
                ], 422);
            }

            $templates = array_values(array_filter($response->json('data') ?? [], fn ($tpl) => !$this->isMetaSampleTemplate($tpl['name'] ?? '')));

            return response()->json(['success' => true, 'templates' => $templates]);
        } catch (\Throwable $e) {
            Log::error('Exceção ao listar templates do WABA na Meta: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao listar templates: ' . $e->getMessage()], 500);
        }
    }

    /**
     * A Meta cria sozinha alguns templates de demonstracao em toda conta nova (hello_world, e a
     * familia "Jasper's Market" usada nos tutoriais deles) - nem da pra apagar esses. Filtra pelo
     * prefixo do nome pra nao poluir a lista com template que o admin nunca vai usar de verdade.
     */
    private function isMetaSampleTemplate(string $name): bool
    {
        $name = strtolower($name);
        foreach (['hello_world', 'sample_', 'jaspers_market'] as $prefix) {
            if (str_starts_with($name, $prefix)) return true;
        }
        return false;
    }

    /**
     * Cria um Modelo de Mensagem novo direto na Meta, sem precisar abrir o WhatsApp Manager.
     * Categoria fixa em UTILITY (nao exposta no formulario) - e o encaixe certo pra mensagem de
     * retomada de uma conversa existente; a Meta reclassifica na propria revisao se achar que
     * nao e. Todo template novo nasce com status PENDING (aprovacao leva minutos a horas).
     */
    private function createMetaTemplate(Request $request)
    {
        $request->validate([
            'assistant_id' => 'required|exists:assistants,id',
            'name' => 'required|string|max:512|regex:/^[a-z0-9_]+$/',
            'language' => 'required|string|max:10',
            'category' => 'required|in:UTILITY,MARKETING',
            'body' => 'required|string|max:1024',
        ]);

        $assistant = Assistant::findOrFail($request->input('assistant_id'));
        if (empty($assistant->whatsapp_waba_id) || empty($assistant->whatsapp_token)) {
            return response()->json(['success' => false, 'message' => 'Esse assistente não tem uma conta Meta conectada.'], 422);
        }

        $body = $request->input('body');
        $hasVariable = str_contains($body, '{{1}}');

        $bodyComponent = ['type' => 'BODY', 'text' => $body];
        if ($hasVariable) {
            // A Meta exige um valor de exemplo pra {{1}} pra conseguir revisar o template - nao e
            // o valor real que vai ser usado no envio (isso so acontece na hora do disparo, com o
            // nome de verdade do cliente), e so uma amostra pro avaliador humano.
            $bodyComponent['example'] = ['body_text' => [['Maria']]];
        }

        try {
            $response = Http::withToken($assistant->whatsapp_token)
                ->post("https://graph.facebook.com/v21.0/{$assistant->whatsapp_waba_id}/message_templates", [
                    'name' => $request->input('name'),
                    'language' => $request->input('language'),
                    'category' => $request->input('category'),
                    'components' => [$bodyComponent],
                ]);

            if (!$response->successful()) {
                Log::error('Erro ao criar template na Meta: ' . $response->body());
                return response()->json(['success' => false, 'message' => $response->json('error.error_user_msg') ?? $response->json('error.message') ?? 'Não foi possível criar o template.'], 422);
            }

            return response()->json(['success' => true, 'template' => [
                'name' => $request->input('name'),
                'language' => $request->input('language'),
                'status' => 'PENDING',
                'category' => $request->input('category'),
                'hasVariable' => $hasVariable,
            ]]);
        } catch (\Throwable $e) {
            Log::error('Exceção ao criar template na Meta: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao criar template: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Edita um template ja existente direto pelo id numerico dele (nao o name) - endereco
     * diferente do create, que usa o WABA. Editar categoria/corpo manda o template de volta pra
     * revisao da Meta (status some do APPROVED/REJECTED e volta pra PENDING), que e exatamente o
     * "reenviar pra aprovacao" que o admin precisa quando um template e recusado ou reclassificado.
     */
    private function updateMetaTemplate(Request $request)
    {
        $request->validate([
            'assistant_id' => 'required|exists:assistants,id',
            'template_id' => 'required|string',
            'category' => 'required|in:UTILITY,MARKETING',
            'body' => 'required|string|max:1024',
        ]);

        $assistant = Assistant::findOrFail($request->input('assistant_id'));
        if (empty($assistant->whatsapp_token)) {
            return response()->json(['success' => false, 'message' => 'Esse assistente não tem uma conta Meta conectada.'], 422);
        }

        $body = $request->input('body');
        $hasVariable = str_contains($body, '{{1}}');

        $bodyComponent = ['type' => 'BODY', 'text' => $body];
        if ($hasVariable) {
            $bodyComponent['example'] = ['body_text' => [['Maria']]];
        }

        try {
            $response = Http::withToken($assistant->whatsapp_token)
                ->post("https://graph.facebook.com/v21.0/{$request->input('template_id')}", [
                    'category' => $request->input('category'),
                    'components' => [$bodyComponent],
                ]);

            if (!$response->successful()) {
                Log::error('Erro ao editar template na Meta: ' . $response->body());
                return response()->json(['success' => false, 'message' => $response->json('error.error_user_msg') ?? $response->json('error.message') ?? 'Não foi possível editar o template.'], 422);
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('Exceção ao editar template na Meta: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao editar template: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Deleta um template direto na Meta (DELETE /{waba_id}/message_templates?name=...) - a Meta
     * remove TODAS as variacoes de idioma desse nome de uma vez (nao da pra deletar so uma).
     */
    private function deleteMetaTemplate(Request $request)
    {
        $request->validate([
            'assistant_id' => 'required|exists:assistants,id',
            'name' => 'required|string',
        ]);

        $assistant = Assistant::findOrFail($request->input('assistant_id'));
        if (empty($assistant->whatsapp_waba_id) || empty($assistant->whatsapp_token)) {
            return response()->json(['success' => false, 'message' => 'Esse assistente não tem uma conta Meta conectada.'], 422);
        }

        try {
            $response = Http::withToken($assistant->whatsapp_token)
                ->delete("https://graph.facebook.com/v21.0/{$assistant->whatsapp_waba_id}/message_templates?name=" . urlencode($request->input('name')));

            if (!$response->successful()) {
                Log::error('Erro ao deletar template na Meta: ' . $response->body());
                return response()->json(['success' => false, 'message' => $response->json('error.error_user_msg') ?? $response->json('error.message') ?? 'Não foi possível deletar o template.'], 422);
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('Exceção ao deletar template na Meta: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erro ao deletar template: ' . $e->getMessage()], 500);
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
                $normalizedAnswer = mb_strtolower(trim($answerText));
                $matchedOption = null;
                foreach ($options as $option) {
                    if (mb_strtolower(trim($option->option_text)) === $normalizedAnswer) {
                        $matchedOption = $option;
                        break;
                    }
                }
                // Resposta por voz costuma vir como frase completa ("eu acho que foi excelente"),
                // não bate exato com "Excelente" - se não achou igualdade exata, tenta achar a opção
                // como um trecho dentro da frase (a mais longa primeiro, pra "ótimo" não roubar de
                // "muito ótimo" se ambas existirem).
                if (!$matchedOption) {
                    $sortedOptions = $options->sortByDesc(fn($o) => mb_strlen($o->option_text));
                    foreach ($sortedOptions as $option) {
                        $optionText = mb_strtolower(trim($option->option_text));
                        if ($optionText !== '' && mb_stripos($normalizedAnswer, $optionText) !== false) {
                            $matchedOption = $option;
                            break;
                        }
                    }
                }
                if ($matchedOption) {
                    $answerText = $matchedOption->option_text;
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

        // A negação tem prioridade: "não quero" contém a palavra "quero" (afirmativa), então checar
        // só a presença de palavras afirmativas dava falso positivo nesse tipo de frase - muito comum
        // em respostas por voz transcritas ("Não, não quero."), raro em respostas digitadas curtas.
        $normalizedMessage = trim($userMessage);
        $hasNegative = (bool) preg_match('/\b(não|nao|num|agora não|depois|não posso)\b/iu', $normalizedMessage);
        $hasAffirmative = (bool) preg_match('/\b(sim|s|quero|claro|pode|ok|okay|beleza|bora|vamos|topo|aceito|com certeza)\b/iu', $normalizedMessage);
        $accepted = $hasAffirmative && !$hasNegative;
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
     * Chamado periodicamente (via comando artisan automation:process-followups, sem nenhuma
     * requisição HTTP em andamento) pra varrer conversas paradas e tocar a automação de retomada:
     * manda a próxima mensagem configurada quando o intervalo estoura, ou encerra o atendimento
     * (via sendAiGeneratedClosing) depois de esgotar todas as tentativas configuradas.
     */
    public function processFollowupAutomations(): void
    {
        $assistantIds = Setting::where('key', 'automation_enabled')->where('value', '1')->pluck('assistant_id');

        foreach ($assistantIds as $assistantId) {
            $assistant = Assistant::find($assistantId);
            if (!$assistant) continue;

            $intervalMinutes = (int) (Setting::where('assistant_id', $assistantId)->where('key', 'automation_interval_minutes')->value('value') ?? 30);
            $messages = json_decode(Setting::where('assistant_id', $assistantId)->where('key', 'automation_messages')->value('value') ?? '[]', true) ?: [];
            if ($intervalMinutes < 1 || empty($messages)) continue;

            $dueFollowups = AutomationFollowup::where('assistant_id', $assistantId)
                ->where('last_activity_at', '<=', now()->subMinutes($intervalMinutes))
                ->get();

            foreach ($dueFollowups as $followup) {
                if ($this->validateBusinessHours(now(), $assistantId)) {
                    continue; // Fora do horário comercial/feriado: tenta de novo no próximo ciclo.
                }

                $nowFormatted = now()->setTimezone($this->getTimezone($assistantId))->toDateTimeString();

                if ($followup->attempts_sent < count($messages)) {
                    $message = trim((string) $messages[$followup->attempts_sent]);
                    if ($message === '') {
                        $followup->update(['attempts_sent' => $followup->attempts_sent + 1, 'last_activity_at' => now()]);
                        continue;
                    }

                    // Mensagem proativa fora da janela de 24h: assistente Meta precisa de um
                    // Template ja aprovado em vez de texto livre - identificado pelo prefixo
                    // "tpl:nome:idioma:temVariavel" que a tela de Automacao grava quando o admin
                    // escolhe um template, em vez do texto puro usado pela UazAPI. O 4o campo
                    // (0 ou 1) diz se o template usa {{1}} = nome do cliente - so manda esse
                    // parametro quando o template de fato tem a variavel, senao a Meta rejeita.
                    if (str_starts_with($message, 'tpl:')) {
                        [, $templateName, $templateLanguage, $hasVariable] = array_pad(explode(':', $message, 4), 4, '');
                        $clientName = null;
                        if ($hasVariable === '1') {
                            $clientName = WaContactName::where('assistant_id', $assistantId)
                                ->where('phone_number', $followup->phone_number)
                                ->value('name') ?: 'Cliente';
                        }
                        $waResult = $this->sendWhatsappTemplate($assistant, $followup->send_target, $templateName, $templateLanguage ?: 'pt_BR', $clientName);
                        $logMessage = "[Template: {$templateName} ({$templateLanguage})]" . ($clientName ? " nome={$clientName}" : '');
                    } else {
                        $waResult = $this->sendWhatsappMessage($assistant, $followup->send_target, $message);
                        $logMessage = $message;
                    }
                    $this->sendToOmni($logMessage, $followup->phone_number, 'output', $followup->send_target, $assistantId);

                    DB::table('chat_messages')->insert([
                        'assistant_id' => $assistantId, 'phone_number' => $followup->phone_number, 'protocol' => null,
                        'role' => 'assistant', 'content' => $logMessage, 'created_at' => $nowFormatted, 'updated_at' => $nowFormatted,
                    ]);
                    DB::table('webhook_logs')->insert([
                        'assistant_id' => $assistantId,
                        'sender' => substr($followup->phone_number, 0, 255),
                        'user_message' => '[AUTOMAÇÃO] sem resposta do cliente',
                        'ai_reply' => '[RETOMADA ' . ($followup->attempts_sent + 1) . '] ' . $logMessage,
                        'wa_send_result' => json_encode($waResult, JSON_INVALID_UTF8_IGNORE),
                        'raw_snippet' => null,
                        'timestamp' => $nowFormatted,
                        'created_at' => $nowFormatted,
                        'updated_at' => $nowFormatted,
                    ]);

                    $followup->update(['attempts_sent' => $followup->attempts_sent + 1, 'last_activity_at' => now()]);
                } else {
                    $this->sendAiGeneratedClosing(
                        $assistant,
                        $followup->send_target,
                        $followup->phone_number,
                        '[SISTEMA: o cliente não respondeu depois de todas as tentativas de retomada de atendimento. Encerre o atendimento agora, seguindo estritamente as suas instruções de encerramento. Não pergunte mais nada.]'
                    );
                    $followup->delete();
                }
            }
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