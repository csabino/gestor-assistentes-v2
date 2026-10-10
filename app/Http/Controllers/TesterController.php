<?php

namespace App\Http\Controllers;

use App\Models\Assistant;
use App\Models\TestRun;
use App\Services\TestHarnessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Módulo Testador: QA automatizado via WhatsApp real, desacoplado de qualquer assistente
 * específico (ver plano "Módulo Testador" no histórico de planejamento). Estende
 * AssistantController só pra herdar callAiApi/sendWhatsappMessage/buildTestHarnessPrompt/
 * extractTextFromFile/sanitizeText (protected) - não reusa nem sobrescreve nenhuma rota/action
 * de assistente de verdade.
 */
class TesterController extends AssistantController
{
    public function handle(Request $request)
    {
        if ($request->isMethod('post')) {
            $action = $request->input('action');
            if ($action === 'start_test_run') return $this->startTestRun($request);
            if ($action === 'stop_test_run') return $this->stopTestRun($request);
            if ($action === 'upload_test_kb_file') return $this->uploadTestKbFile($request);
            if ($action === 'store_test_harness_assistant') return $this->storeTestHarnessAssistant($request);
        }

        if ($request->isMethod('get') && $request->input('action') === 'get_test_run_detail') {
            $testRun = TestRun::findOrFail($request->query('test_run_id'));
            return response()->json($testRun);
        }

        $targets = Assistant::where('is_test_harness', false)->orderBy('name')->get(['id', 'name', 'system_prompt', 'knowledge_files']);
        $harnessAssistant = Assistant::where('is_test_harness', true)->first();
        $testRuns = TestRun::orderByDesc('created_at')->limit(200)->get(['id', 'name', 'target_label', 'status', 'created_at', 'completed_at']);
        $currentView = 'tester';

        return view('tester.index', compact('targets', 'harnessAssistant', 'testRuns', 'currentView'));
    }

    private function storeTestHarnessAssistant(Request $request)
    {
        if (!Assistant::where('is_test_harness', true)->exists()) {
            (new Assistant())->forceFill([
                'name' => 'Número de Teste (Testador)',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'context_limit' => 12,
                'system_prompt' => '',
                'is_active' => true,
                'is_test_harness' => true,
            ])->save();
        }

        return redirect('/?view=tester')->with('success', 'Número de teste criado! Agora conecte o WhatsApp dele abaixo.');
    }

    private function startTestRun(Request $request)
    {
        $request->validate([
            'target_label' => 'required|string|max:255',
            'target_phone_number' => 'required|string|max:50',
            'target_prompt_snapshot' => 'required|string',
            'target_knowledge_snapshot' => 'nullable|string',
        ]);

        $harnessAssistant = Assistant::where('is_test_harness', true)->first();
        if (!$harnessAssistant) {
            return redirect('/?view=tester')->with('error', 'Crie o número de teste antes de iniciar um teste.');
        }
        if (empty($harnessAssistant->whatsapp_provider) || empty($harnessAssistant->whatsapp_token)) {
            return redirect('/?view=tester')->with('error', 'Conecte o WhatsApp do número de teste antes de iniciar um teste.');
        }
        $providerKeyField = ($harnessAssistant->provider ?? 'openai') . '_api_key';
        if (empty($harnessAssistant->{$providerKeyField})) {
            return redirect('/?view=tester')->with('error', 'Configure a chave de API de IA do número de teste antes de iniciar um teste.');
        }

        if (TestRun::where('harness_assistant_id', $harnessAssistant->id)->where('status', 'running')->exists()) {
            return redirect('/?view=tester')->with('error', 'Já existe um teste em andamento - aguarde ele terminar (ou pare manualmente) antes de iniciar outro.');
        }

        $name = $request->input('target_label') . '_' . now()->format('Ymd') . '_' . now()->format('H\hi');

        $testRun = TestRun::create([
            'name' => $name,
            'target_label' => $request->input('target_label'),
            'target_phone_number' => $request->input('target_phone_number'),
            'harness_assistant_id' => $harnessAssistant->id,
            'target_prompt_snapshot' => $request->input('target_prompt_snapshot'),
            'target_knowledge_snapshot' => $request->input('target_knowledge_snapshot'),
            'status' => 'draft',
            'created_by_user_id' => $request->user()?->id,
        ]);

        app(TestHarnessService::class)->launchRun($this, $testRun);

        return redirect('/?view=tester')->with('success', 'Teste "' . $name . '" iniciado!');
    }

    private function stopTestRun(Request $request)
    {
        $testRun = TestRun::findOrFail($request->input('test_run_id'));
        $testRun->status = 'stopped';
        $testRun->save();

        return redirect('/?view=tester')->with('success', 'Teste interrompido.');
    }

    private function uploadTestKbFile(Request $request)
    {
        $request->validate(['file' => 'required|file|max:10240']);

        $file = $request->file('file');
        $path = $file->store('tmp_test_harness_uploads');

        try {
            $text = $this->extractTextFromFile(Storage::path($path), $file->getClientOriginalName());
            $text = $this->sanitizeText($text, 35000);
            return response()->json(['success' => true, 'name' => $file->getClientOriginalName(), 'text' => $text]);
        } finally {
            Storage::delete($path);
        }
    }
}
