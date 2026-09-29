<?php

namespace App\Http\Controllers;

use App\Models\Assistant;
use App\Models\Setting;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    public function handle(Request $request)
    {
        if ($request->isMethod('post')) {
            $action = $request->input('action');
            if ($action === 'update_automation') return $this->updateAutomation($request);
        }

        $assistantId = (int) $request->query('assistant_id');
        $assistant = Assistant::findOrFail($assistantId);

        $automationEnabled = Setting::where('assistant_id', $assistantId)->where('key', 'automation_enabled')->value('value') ?? '0';
        $automationIntervalMinutes = Setting::where('assistant_id', $assistantId)->where('key', 'automation_interval_minutes')->value('value') ?? '30';
        $automationMessagesRaw = Setting::where('assistant_id', $assistantId)->where('key', 'automation_messages')->value('value');
        $automationMessages = $automationMessagesRaw ? (json_decode($automationMessagesRaw, true) ?: []) : [];

        $currentView = 'automation';

        return view('automation.index', compact('assistant', 'automationEnabled', 'automationIntervalMinutes', 'automationMessages', 'currentView'));
    }

    private function updateAutomation(Request $request)
    {
        $assistantId = (int) $request->input('assistant_id');
        $request->validate([
            'automation_interval_minutes' => 'required|integer|min:1',
            'messages' => 'nullable|array',
            'messages.*' => 'nullable|string|max:1000',
        ]);

        $messages = array_values(array_filter(array_map('trim', $request->input('messages', []))));
        $enabled = $request->boolean('automation_enabled');

        if ($enabled && empty($messages)) {
            return redirect("/?view=automation&assistant_id={$assistantId}")
                ->with('error', 'Pra ativar a automação, cadastre pelo menos uma mensagem de retomada preenchida.');
        }

        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'automation_enabled'],
            ['value' => $enabled ? '1' : '0']
        );
        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'automation_interval_minutes'],
            ['value' => (string) $request->input('automation_interval_minutes')]
        );
        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'automation_messages'],
            ['value' => json_encode($messages)]
        );

        return redirect("/?view=automation&assistant_id={$assistantId}")->with('success', 'Automação salva!');
    }
}
