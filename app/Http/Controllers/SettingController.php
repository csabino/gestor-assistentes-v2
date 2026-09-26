<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Models\Assistant;
use App\Models\Holiday;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function handle(Request $request)
    {
        $this->ensureTableExists();

        if ($request->has('code') || $request->input('action') === 'google_callback') {
            return $this->handleGoogleCallback($request);
        }

        if ($request->input('action') === 'google_redirect') {
            return $this->redirectToGoogle($request);
        }

        if ($request->isMethod('post')) {
            if ($request->input('action') === 'add_holiday') {
                return $this->addHoliday($request);
            }
            if ($request->input('action') === 'delete_holiday') {
                return $this->deleteHoliday($request);
            }
            return $this->update($request);
        }

        return $this->index($request);
    }

    private function ensureTableExists()
    {
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('assistant_id')->index();
                $table->string('key');
                $table->text('value')->nullable();
                $table->timestamps();
                $table->unique(['assistant_id', 'key']);
            });
        } elseif (!Schema::hasColumn('settings', 'assistant_id')) {
            Schema::dropIfExists('settings');
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('assistant_id')->index();
                $table->string('key');
                $table->text('value')->nullable();
                $table->timestamps();
                $table->unique(['assistant_id', 'key']);
            });
        }
    }

    public function index(Request $request)
    {
        $assistantId = $request->input('assistant_id');
        
        if (!$assistantId) {
            return redirect('/')->with('error', 'Selecione um assistente para acessar as configurações avançadas.');
        }

        $assistant = Assistant::findOrFail($assistantId);
        $timezones = \DateTimeZone::listIdentifiers();

        $currentTz = Setting::where('assistant_id', $assistantId)->where('key', 'timezone')->value('value') ?? 'America/Sao_Paulo';
        $webhookUrl = Setting::where('assistant_id', $assistantId)->where('key', 'omni_webhook_url')->value('value') ?? '';
        
        $maxFileSize = Setting::where('assistant_id', $assistantId)->where('key', 'max_file_size_mb')->value('value') ?? '4';
        $allowedExtRaw = Setting::where('assistant_id', $assistantId)->where('key', 'allowed_extensions')->value('value');
        $allowedExtensions = $allowedExtRaw ? json_decode($allowedExtRaw, true) : [];
        if (!is_array($allowedExtensions)) {
            $allowedExtensions = [];
        }

        // Credenciais do Google Calendar por Assistente
        $googleClientId = Setting::where('assistant_id', $assistantId)->where('key', 'google_client_id')->value('value') ?? '';
        $googleClientSecret = Setting::where('assistant_id', $assistantId)->where('key', 'google_client_secret')->value('value') ?? '';
        $googleCalendarId = Setting::where('assistant_id', $assistantId)->where('key', 'google_calendar_id')->value('value') ?? 'primary';
        $googleRefreshToken = Setting::where('assistant_id', $assistantId)->where('key', 'google_refresh_token')->value('value') ?? '';

        // Novas Regras Dinâmicas de Agendamento
        $schedulingEnabled = Setting::where('assistant_id', $assistantId)->where('key', 'scheduling_enabled')->value('value') ?? '1';
        $defaultDepartmentId = Setting::where('assistant_id', $assistantId)->where('key', 'default_department_id')->value('value') ?? '';
        $schedulingCustomPrompt = Setting::where('assistant_id', $assistantId)->where('key', 'scheduling_custom_prompt')->value('value') ?? '';
        $meetingDurationMinutes = Setting::where('assistant_id', $assistantId)->where('key', 'meeting_duration_minutes')->value('value') ?? '60';
        $businessHoursStart = Setting::where('assistant_id', $assistantId)->where('key', 'business_hours_start')->value('value') ?? '09:00';
        $businessHoursEnd = Setting::where('assistant_id', $assistantId)->where('key', 'business_hours_end')->value('value') ?? '17:00';
        $businessBlockWeekends = Setting::where('assistant_id', $assistantId)->where('key', 'business_block_weekends')->value('value') ?? '1';

        // Carrega departamentos cadastrados no sistema
        $departments = DB::table('departments')->get();

        $holidays = Holiday::where('assistant_id', $assistantId)->orderBy('date')->get();

        $currentView = 'settings';

        return view('settings.index', compact(
            'timezones', 
            'currentTz', 
            'webhookUrl', 
            'maxFileSize', 
            'allowedExtensions', 
            'googleClientId',
            'googleClientSecret',
            'googleCalendarId',
            'googleRefreshToken',
            'schedulingEnabled',
            'defaultDepartmentId',
            'schedulingCustomPrompt',
            'meetingDurationMinutes',
            'holidays',
            'businessHoursStart',
            'businessHoursEnd',
            'businessBlockWeekends',
            'departments',
            'currentView', 
            'assistant'
        ));
    }

    public function update(Request $request)
    {
        $request->validate([
            'assistant_id' => 'required|exists:assistants,id',
            'timezone' => 'required|string',
            'omni_webhook_url' => 'nullable|string',
            'max_file_size_mb' => 'required|in:1,2,4,6,8',
            'allowed_extensions' => 'nullable|array',
            'google_client_id' => 'nullable|string',
            'google_client_secret' => 'nullable|string',
            'google_calendar_id' => 'nullable|string',
            'google_refresh_token' => 'nullable|string',
            'scheduling_enabled' => 'required|in:0,1',
            'default_department_id' => 'nullable|string',
            'scheduling_custom_prompt' => 'nullable|string',
            'meeting_duration_minutes' => 'required|integer|min:15|max:480',
            'business_hours_start' => 'required|date_format:H:i',
            'business_hours_end' => 'required|date_format:H:i',
            'business_block_weekends' => 'nullable|in:0,1',
        ]);

        $assistantId = $request->input('assistant_id');
        $webhookUrl = trim($request->input('omni_webhook_url') ?? '');
        
        if ($webhookUrl !== '') {
            $webhookUrl = rtrim($webhookUrl, '/') . '/';
        }

        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'timezone'],
            ['value' => $request->input('timezone')]
        );
        
        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'omni_webhook_url'],
            ['value' => $webhookUrl]
        );

        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'max_file_size_mb'],
            ['value' => $request->input('max_file_size_mb', '4')]
        );

        $allowedExtensions = $request->input('allowed_extensions', []);
        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'allowed_extensions'],
            ['value' => json_encode(array_values($allowedExtensions))]
        );

        // Salvamento das Configurações do Google Calendar
        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'google_client_id'],
            ['value' => trim($request->input('google_client_id') ?? '')]
        );

        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'google_client_secret'],
            ['value' => trim($request->input('google_client_secret') ?? '')]
        );

        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'google_calendar_id'],
            ['value' => trim($request->input('google_calendar_id') ?? 'primary')]
        );

        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'google_refresh_token'],
            ['value' => trim($request->input('google_refresh_token') ?? '')]
        );

        // Salvamento das Regras Dinâmicas de Agendamento
        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'scheduling_enabled'],
            ['value' => $request->input('scheduling_enabled', '1')]
        );

        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'default_department_id'],
            ['value' => trim($request->input('default_department_id') ?? '')]
        );

        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'scheduling_custom_prompt'],
            ['value' => trim($request->input('scheduling_custom_prompt') ?? '')]
        );

        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'meeting_duration_minutes'],
            ['value' => (string) $request->input('meeting_duration_minutes', 60)]
        );

        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'business_hours_start'],
            ['value' => $request->input('business_hours_start', '09:00')]
        );

        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'business_hours_end'],
            ['value' => $request->input('business_hours_end', '17:00')]
        );

        Setting::updateOrCreate(
            ['assistant_id' => $assistantId, 'key' => 'business_block_weekends'],
            ['value' => $request->boolean('business_block_weekends') ? '1' : '0']
        );

        return redirect()->to('/?view=settings&assistant_id=' . $assistantId)->with('success', 'Configurações atualizadas para este assistente!');
    }

    private function addHoliday(Request $request)
    {
        $request->validate([
            'assistant_id' => 'required|exists:assistants,id',
            'holiday_name' => 'required|string|max:255',
            'holiday_date' => 'required|date',
        ]);

        Holiday::create([
            'assistant_id' => $request->input('assistant_id'),
            'name' => trim($request->input('holiday_name')),
            'date' => $request->input('holiday_date'),
            'is_recurring' => $request->boolean('holiday_recurring'),
        ]);

        return redirect()->to('/?view=settings&assistant_id=' . $request->input('assistant_id'))->with('success', 'Feriado cadastrado!');
    }

    private function deleteHoliday(Request $request)
    {
        $assistantId = $request->input('assistant_id');

        Holiday::where('id', $request->input('holiday_id'))->where('assistant_id', $assistantId)->delete();

        return redirect()->to('/?view=settings&assistant_id=' . $assistantId)->with('success', 'Feriado removido!');
    }

    private function redirectToGoogle(Request $request)
    {
        $assistantId = $request->input('assistant_id');
        $clientId = Setting::where('assistant_id', $assistantId)->where('key', 'google_client_id')->value('value');

        if (!$clientId) {
            return redirect()->to('/?view=settings&assistant_id=' . $assistantId)->with('error', 'Preencha e salve o Google Client ID antes de conectar.');
        }

        $redirectUri = 'https://' . $request->getHost() . '/?view=settings&action=google_callback';
        
        $params = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'prompt' => 'select_account consent',
            'state' => $assistantId,
        ]);

        return redirect()->to('https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    }

    private function handleGoogleCallback(Request $request)
    {
        $code = $request->input('code');
        $assistantId = $request->input('state');

        if (!$code || !$assistantId) {
            return redirect('/')->with('error', 'Falha na autenticação do Google: Código de autorização não recebido.');
        }

        $clientId = Setting::where('assistant_id', $assistantId)->where('key', 'google_client_id')->value('value');
        $clientSecret = Setting::where('assistant_id', $assistantId)->where('key', 'google_client_secret')->value('value');

        if (!$clientId || !$clientSecret) {
            return redirect()->to('/?view=settings&assistant_id=' . $assistantId)->with('error', 'Credenciais de Client ID e Client Secret não encontradas para este assistente.');
        }

        $redirectUri = 'https://' . $request->getHost() . '/?view=settings&action=google_callback';

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
            ]);

            if ($response->successful()) {
                $refreshToken = $response->json('refresh_token');

                if ($refreshToken) {
                    Setting::updateOrCreate(
                        ['assistant_id' => $assistantId, 'key' => 'google_refresh_token'],
                        ['value' => $refreshToken]
                    );
                    return redirect()->to('/?view=settings&assistant_id=' . $assistantId)->with('success', 'Conta do Google autenticada e vinculada com sucesso!');
                } else {
                    return redirect()->to('/?view=settings&assistant_id=' . $assistantId)->with('error', 'O Google não retornou o Refresh Token. Certifique-se de revogar a permissão no Google e tentar novamente.');
                }
            }

            Log::error('Erro ao obter token do Google: ' . $response->body());
            return redirect()->to('/?view=settings&assistant_id=' . $assistantId)->with('error', 'Erro ao trocar código por token junto ao Google.');
        } catch (\Throwable $e) {
            Log::error('Exceção no callback do Google: ' . $e->getMessage());
            return redirect()->to('/?view=settings&assistant_id=' . $assistantId)->with('error', 'Exceção na autenticação do Google: ' . $e->getMessage());
        }
    }
}