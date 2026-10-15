<?php

namespace App\Http\Controllers;

use App\Models\Assistant;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastContact;
use App\Models\BroadcastMessage;
use App\Services\BroadcastDispatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Módulo Broadcasting: campanhas de envio de mensagens em massa via WhatsApp. Estende
 * AssistantController só pra herdar sendWhatsappMessage/sendWhatsappTemplate/normalizeWaTarget
 * (mesmo truque de herança do TesterController) - não reusa nem sobrescreve nenhuma rota/action de
 * assistente de verdade.
 */
class BroadcastingController extends AssistantController
{
    public function handle(Request $request)
    {
        if ($request->isMethod('post')) {
            $action = $request->input('action');
            if ($action === 'store_campaign') return $this->storeCampaign($request);
            if ($action === 'update_campaign') return $this->updateCampaign($request);
            if ($action === 'delete_campaign') return $this->deleteCampaign($request);
            if ($action === 'upload_broadcast_mailing') return $this->uploadMailing($request);
            if ($action === 'run_sanitization') return $this->runSanitization($request);
            if ($action === 'store_broadcast_message') return $this->storeBroadcastMessage($request);
            if ($action === 'delete_broadcast_message') return $this->deleteBroadcastMessage($request);
            if ($action === 'dispatch_campaign') return $this->dispatchCampaign($request);
            if ($action === 'pause_campaign') return $this->pauseCampaign($request);
            if ($action === 'resume_campaign') return $this->resumeCampaign($request);
            if ($action === 'cancel_campaign') return $this->cancelCampaign($request);
        }

        if ($request->isMethod('get') && $request->input('action') === 'campaign_detail') {
            return $this->campaignDetail($request);
        }

        if ($request->isMethod('get') && $request->input('action') === 'download_mailing_template') {
            return $this->downloadMailingTemplate();
        }

        $campaigns = BroadcastCampaign::with('assistant')->orderByDesc('created_at')->get();
        $assistants = Assistant::where('is_test_harness', false)->orderBy('name')->get(['id', 'name', 'whatsapp_provider']);
        $messages = BroadcastMessage::orderByDesc('created_at')->get();
        $currentView = 'broadcasting';

        return view('broadcasting.index', compact('campaigns', 'assistants', 'messages', 'currentView'));
    }

    private function storeCampaign(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'assistant_id' => 'required|exists:assistants,id',
        ]);

        $campaign = BroadcastCampaign::create([
            'name' => $request->input('name'),
            'assistant_id' => $request->input('assistant_id'),
            'status' => 'draft',
            'created_by_user_id' => $request->user()?->id,
        ]);

        return response()->json(['success' => true, 'campaign' => $campaign]);
    }

    private function updateCampaign(Request $request)
    {
        $request->validate(['campaign_id' => 'required|exists:broadcast_campaigns,id']);

        $campaign = BroadcastCampaign::findOrFail($request->input('campaign_id'));
        if (in_array($campaign->status, ['sending', 'completed'])) {
            return response()->json(['success' => false, 'message' => 'Essa campanha já está em andamento ou concluída e não pode ser editada.'], 422);
        }

        $data = [];
        if ($request->filled('name')) $data['name'] = $request->input('name');
        if ($request->filled('assistant_id')) $data['assistant_id'] = $request->input('assistant_id');
        if ($request->has('batch_size')) $data['batch_size'] = $request->input('batch_size') ?: null;

        if ($request->filled('message_selector')) {
            $data['message_selector'] = $request->input('message_selector');
            $data['message_preview'] = $this->buildMessagePreview($request->input('message_selector'), $request->input('message_preview_text'));
        }

        $campaign->update($data);

        if ($campaign->status === 'draft' && $campaign->message_selector && $campaign->sanitized_at && $campaign->valid_contacts_count > 0) {
            $campaign->update(['status' => 'ready']);
        }

        return response()->json(['success' => true, 'campaign' => $campaign->fresh()]);
    }

    private function buildMessagePreview(string $selector, ?string $previewText = null): string
    {
        if ($previewText) {
            return $previewText;
        }
        if (str_starts_with($selector, 'tpl:')) {
            [, $name, $lang] = array_pad(explode(':', $selector, 4), 4, '');
            return "Template Meta: {$name} ({$lang})";
        }
        if (str_starts_with($selector, 'free:')) {
            $message = BroadcastMessage::find((int) substr($selector, 5));
            return $message ? $message->body : '';
        }
        return '';
    }

    private function deleteCampaign(Request $request)
    {
        $campaign = BroadcastCampaign::findOrFail($request->input('campaign_id'));
        if ($campaign->status === 'sending') {
            return response()->json(['success' => false, 'message' => 'Pare a campanha antes de excluir.'], 422);
        }
        $campaign->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Recebe o CSV do mailing (nome;telefone, cabeçalho opcional), substitui os contatos atuais
     * da campanha e volta o status pra "draft" (precisa sanear de novo). Mesmo padrão de upload
     * temporário do TesterController::uploadTestKbFile() (store -> processa -> delete no finally).
     */
    private function uploadMailing(Request $request)
    {
        $request->validate([
            'campaign_id' => 'required|exists:broadcast_campaigns,id',
            'file' => 'required|file|max:5120',
        ]);

        $campaign = BroadcastCampaign::findOrFail($request->input('campaign_id'));
        if (in_array($campaign->status, ['sending', 'completed'])) {
            return response()->json(['success' => false, 'message' => 'Essa campanha já está em andamento ou concluída.'], 422);
        }

        $file = $request->file('file');
        $path = $file->store('tmp_broadcast_uploads');

        try {
            $rows = [];
            $handle = fopen(Storage::path($path), 'r');
            if ($handle === false) {
                return response()->json(['success' => false, 'message' => 'Não foi possível ler o arquivo.'], 422);
            }

            $isFirstRow = true;
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                if (count($row) === 1) {
                    $row = str_getcsv($row[0], ',');
                }
                $col0 = trim((string) ($row[0] ?? ''));
                $col1 = trim((string) ($row[1] ?? ''));

                if ($isFirstRow) {
                    $isFirstRow = false;
                    $looksLikeHeader = preg_match('/nome|name|telefone|phone|whats/i', $col0 . ' ' . $col1);
                    if ($looksLikeHeader) continue;
                }

                if ($col0 === '' && $col1 === '') continue;

                // Se só tem uma coluna preenchida, assume que é o telefone (sem nome).
                if ($col1 === '' && preg_match('/\d{8,}/', $col0)) {
                    $rows[] = ['name' => null, 'phone_raw' => $col0];
                } else {
                    $rows[] = ['name' => $col0 ?: null, 'phone_raw' => $col1];
                }
            }
            fclose($handle);

            if (empty($rows)) {
                return response()->json(['success' => false, 'message' => 'Nenhum contato encontrado no arquivo.'], 422);
            }

            DB::transaction(function () use ($campaign, $rows) {
                BroadcastContact::where('campaign_id', $campaign->id)->delete();

                $now = now();
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('broadcast_contacts')->insert(array_map(fn ($r) => [
                        'campaign_id' => $campaign->id,
                        'name' => $r['name'],
                        'phone_raw' => $r['phone_raw'],
                        'send_status' => 'pending',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $chunk));
                }

                $campaign->update([
                    'raw_contacts_count' => count($rows),
                    'valid_contacts_count' => 0,
                    'invalid_contacts_count' => 0,
                    'sanitized_at' => null,
                    'status' => 'draft',
                ]);
            });

            return response()->json(['success' => true, 'raw_contacts_count' => count($rows)]);
        } finally {
            Storage::delete($path);
        }
    }

    private function runSanitization(Request $request)
    {
        $request->validate(['campaign_id' => 'required|exists:broadcast_campaigns,id']);

        $campaign = BroadcastCampaign::findOrFail($request->input('campaign_id'));
        app(BroadcastDispatchService::class)->sanitizeMailing($campaign);
        $campaign->refresh();

        return response()->json([
            'success' => true,
            'valid_contacts_count' => $campaign->valid_contacts_count,
            'invalid_contacts_count' => $campaign->invalid_contacts_count,
        ]);
    }

    private function storeBroadcastMessage(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'body' => 'required|string|max:4000',
        ]);

        $message = BroadcastMessage::create([
            'name' => $request->input('name'),
            'body' => $request->input('body'),
            'created_by_user_id' => $request->user()?->id,
        ]);

        return response()->json(['success' => true, 'message' => $message]);
    }

    private function deleteBroadcastMessage(Request $request)
    {
        $message = BroadcastMessage::findOrFail($request->input('message_id'));
        $message->delete();

        return response()->json(['success' => true]);
    }

    private function dispatchCampaign(Request $request)
    {
        $request->validate([
            'campaign_id' => 'required|exists:broadcast_campaigns,id',
            'mode' => 'required|in:now,scheduled',
            'scheduled_at' => 'required_if:mode,scheduled|nullable|date',
        ]);

        $campaign = BroadcastCampaign::findOrFail($request->input('campaign_id'));
        if ($campaign->status !== 'ready') {
            return response()->json(['success' => false, 'message' => 'A campanha precisa estar com o mailing saneado e a mensagem escolhida antes de disparar.'], 422);
        }

        if ($request->input('mode') === 'now') {
            $campaign->update(['status' => 'sending', 'started_at' => now(), 'scheduled_at' => null]);
        } else {
            $campaign->update(['status' => 'scheduled', 'scheduled_at' => $request->input('scheduled_at')]);
        }

        return response()->json(['success' => true, 'campaign' => $campaign->fresh()]);
    }

    private function pauseCampaign(Request $request)
    {
        $campaign = BroadcastCampaign::findOrFail($request->input('campaign_id'));
        if (!in_array($campaign->status, ['sending', 'scheduled'])) {
            return response()->json(['success' => false, 'message' => 'Essa campanha não está em andamento.'], 422);
        }
        $campaign->update(['status' => 'paused']);

        return response()->json(['success' => true]);
    }

    private function resumeCampaign(Request $request)
    {
        $campaign = BroadcastCampaign::findOrFail($request->input('campaign_id'));
        if ($campaign->status !== 'paused') {
            return response()->json(['success' => false, 'message' => 'Essa campanha não está pausada.'], 422);
        }
        $campaign->update(['status' => $campaign->scheduled_at && $campaign->scheduled_at->isFuture() ? 'scheduled' : 'sending']);

        return response()->json(['success' => true]);
    }

    private function cancelCampaign(Request $request)
    {
        $campaign = BroadcastCampaign::findOrFail($request->input('campaign_id'));
        if ($campaign->status === 'completed') {
            return response()->json(['success' => false, 'message' => 'Essa campanha já foi concluída.'], 422);
        }
        $campaign->update(['status' => 'canceled']);

        return response()->json(['success' => true]);
    }

    private function campaignDetail(Request $request)
    {
        $campaign = BroadcastCampaign::with('assistant')->findOrFail($request->query('campaign_id'));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;

        $contactsQuery = BroadcastContact::where('campaign_id', $campaign->id)->orderBy('id');
        $totalContacts = $contactsQuery->count();
        $contacts = $contactsQuery->forPage($page, $perPage)->get();

        $statusCounts = BroadcastContact::where('campaign_id', $campaign->id)
            ->selectRaw('send_status, count(*) as total')
            ->groupBy('send_status')
            ->pluck('total', 'send_status');

        return response()->json([
            'campaign' => $campaign,
            'contacts' => $contacts,
            'total_contacts' => $totalContacts,
            'page' => $page,
            'per_page' => $perPage,
            'status_counts' => $statusCounts,
        ]);
    }

    private function downloadMailingTemplate()
    {
        return response()->streamDownload(function () {
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, ['nome', 'telefone'], ';');
            fputcsv($out, ['Maria Silva', '5513981234567'], ';');
            fclose($out);
        }, 'modelo-mailing.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
