<?php

namespace App\Services;

use App\Http\Controllers\AssistantController;
use App\Models\Assistant;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastContact;
use App\Models\BroadcastMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquestra o módulo Broadcasting: sanitiza o mailing de uma campanha e dispara mensagens em lote,
 * puxado pelo cron externo (/cron/broadcasting-dispatch/{secret}) a cada poucos minutos - mesmo
 * padrão do TestHarnessService e do processFollowupAutomations(), já que este app não tem
 * fila/worker. Cada tick processa um lote pequeno por campanha ativa e retorna rápido.
 */
class BroadcastDispatchService
{
    private const DEFAULT_BATCH_SIZE_META = 30;
    private const DEFAULT_BATCH_SIZE_UNOFFICIAL = 15;
    private const DELAY_MICROSECONDS_META = 300000;
    private const DELAY_MICROSECONDS_UNOFFICIAL = 1000000;

    /**
     * Fase de saneamento: dedup por dígitos normalizados, descarta vazio e formato implausível.
     * Só validação de formato - sem chamada de API pra confirmar se o número tem WhatsApp de
     * verdade (decisão explícita do usuário).
     */
    public function sanitizeMailing(BroadcastCampaign $campaign): void
    {
        $seen = [];
        $validCount = 0;
        $invalidCount = 0;

        $campaign->contacts()->orderBy('id')->chunkById(500, function ($contacts) use (&$seen, &$validCount, &$invalidCount) {
            foreach ($contacts as $contact) {
                $digits = preg_replace('/\D/', '', (string) $contact->phone_raw);

                if ($digits === '') {
                    $contact->update(['is_valid' => false, 'invalid_reason' => 'empty', 'phone_normalized' => null]);
                    $invalidCount++;
                    continue;
                }

                if (strlen($digits) < 10 || strlen($digits) > 15) {
                    $contact->update(['is_valid' => false, 'invalid_reason' => 'invalid_format', 'phone_normalized' => null]);
                    $invalidCount++;
                    continue;
                }

                if (isset($seen[$digits])) {
                    $contact->update(['is_valid' => false, 'invalid_reason' => 'duplicate', 'phone_normalized' => $digits]);
                    $invalidCount++;
                    continue;
                }

                $seen[$digits] = true;
                $contact->update(['is_valid' => true, 'invalid_reason' => null, 'phone_normalized' => $digits]);
                $validCount++;
            }
        });

        $campaign->update([
            'valid_contacts_count' => $validCount,
            'invalid_contacts_count' => $invalidCount,
            'sanitized_at' => now(),
        ]);
    }

    /**
     * Chamado pelo cron a cada tick. Vira campanhas agendadas pra "sending" quando a hora chega, e
     * drena um lote de cada campanha ativa.
     */
    public function processDueCampaigns(AssistantController $ctrl): array
    {
        BroadcastCampaign::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->update(['status' => 'sending', 'started_at' => now()]);

        $results = [];
        $campaigns = BroadcastCampaign::where('status', 'sending')->with('assistant')->get();

        foreach ($campaigns as $campaign) {
            $results[] = $this->processCampaignBatch($ctrl, $campaign);
        }

        return $results;
    }

    private function processCampaignBatch(AssistantController $ctrl, BroadcastCampaign $campaign): array
    {
        $isMeta = $campaign->isMetaAssistant();
        $batchSize = $campaign->batch_size ?: ($isMeta ? self::DEFAULT_BATCH_SIZE_META : self::DEFAULT_BATCH_SIZE_UNOFFICIAL);
        $delay = $isMeta ? self::DELAY_MICROSECONDS_META : self::DELAY_MICROSECONDS_UNOFFICIAL;

        // Passo de reserva: evita que dois ticks de cron sobrepostos (o anterior ainda rodando
        // quando o próximo dispara) peguem os mesmos contatos e disparem em dobro.
        $claimedIds = [];
        DB::transaction(function () use ($campaign, $batchSize, &$claimedIds) {
            $claimedIds = DB::table('broadcast_contacts')
                ->where('campaign_id', $campaign->id)
                ->where('is_valid', true)
                ->where('send_status', 'pending')
                ->orderBy('id')
                ->limit($batchSize)
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            if (!empty($claimedIds)) {
                DB::table('broadcast_contacts')->whereIn('id', $claimedIds)->update([
                    'send_status' => 'sending',
                    'updated_at' => now(),
                ]);
            }
        });

        $sent = 0;
        $failed = 0;
        $contacts = BroadcastContact::whereIn('id', $claimedIds)->get();
        $total = $contacts->count();
        $i = 0;

        foreach ($contacts as $contact) {
            $i++;
            $result = $this->sendToContact($ctrl, $campaign, $contact);

            if ($result['success']) {
                $contact->update(['send_status' => 'sent', 'sent_at' => now(), 'send_error' => null]);
                $sent++;
            } else {
                $contact->update(['send_status' => 'failed', 'send_error' => $result['error'] ?? 'Falha desconhecida']);
                $failed++;
            }

            if ($i < $total) {
                usleep($delay);
            }
        }

        $remaining = $campaign->contacts()->where('is_valid', true)->where('send_status', 'pending')->exists();
        $campaign->increment('sent_count', $sent);
        $campaign->increment('failed_count', $failed);

        if (!$remaining) {
            $campaign->update(['status' => 'completed', 'completed_at' => now()]);
        }

        return ['campaign_id' => $campaign->id, 'claimed' => $total, 'sent' => $sent, 'failed' => $failed];
    }

    private function sendToContact(AssistantController $ctrl, BroadcastCampaign $campaign, $contact): array
    {
        $selector = (string) $campaign->message_selector;
        $assistant = $campaign->assistant;

        try {
            if (str_starts_with($selector, 'tpl:')) {
                [, $templateName, $templateLanguage, $hasVariable] = array_pad(explode(':', $selector, 4), 4, '');
                $clientName = $hasVariable === '1' ? ($contact->name ?: 'Cliente') : null;
                return $ctrl->sendWhatsappTemplate($assistant, $contact->phone_normalized, $templateName, $templateLanguage ?: 'pt_BR', $clientName);
            }

            if (str_starts_with($selector, 'free:')) {
                $messageId = (int) substr($selector, 5);
                $message = BroadcastMessage::find($messageId);
                if (!$message) {
                    return ['success' => false, 'error' => 'Mensagem cadastrada não encontrada (id ' . $messageId . ').'];
                }
                $body = str_replace('{{nome}}', $contact->name ?: 'Cliente', $message->body);
                $body = $this->varyMessageText($ctrl, $assistant, $body);
                return $ctrl->sendWhatsappMessage($assistant, $contact->phone_normalized, $body);
            }

            return ['success' => false, 'error' => 'Campanha sem mensagem selecionada.'];
        } catch (\Throwable $e) {
            Log::error("Broadcasting: falha ao enviar pro contato {$contact->id} da campanha {$campaign->id}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Mensagens de texto livre (número não-oficial) passam por uma reescrita leve via IA antes de
     * cada envio, pra não mandar o texto byte-a-byte idêntico pra toda a lista - uma das
     * recomendações do próprio usuário pra reduzir o risco de bloqueio por "envio em massa
     * detectável". NUNCA se aplica a template da Meta (alterar o texto de um template aprovado
     * quebra a aprovação e pode gerar banimento do WABA). Se a chamada de IA falhar por qualquer
     * motivo, cai de volta pro texto original sem variação - nunca bloqueia o envio por isso.
     */
    private function varyMessageText(AssistantController $ctrl, Assistant $assistant, string $body): string
    {
        $systemPrompt = "Você reescreve mensagens de WhatsApp pra campanhas de envio em massa, mudando levemente a estrutura das frases e algumas palavras (sinônimos, ordem, pontuação, emojis), SEM mudar o significado, o tom nem nenhuma informação (nomes, números, links, datas, valores) - cada mensagem precisa parecer digitada na hora, não um texto idêntico repetido pra vários contatos. Responda SOMENTE com o texto final da mensagem, sem aspas, sem comentário, sem explicação.";

        try {
            $varied = trim($ctrl->callAiApi($assistant, $systemPrompt, $body, []));
            return $varied !== '' ? $varied : $body;
        } catch (\Throwable $e) {
            Log::warning('Broadcasting: falha ao gerar variação por IA, usando texto original: ' . $e->getMessage());
            return $body;
        }
    }
}
