<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class EnvironmentController extends Controller
{
    public function index()
    {
        return response()->json(Setting::branding() + $this->metaConfig());
    }

    public function update(Request $request)
    {
        // Todos os campos de texto são "nullable" porque este endpoint é compartilhado por dois
        // formulários diferentes (o modal Ambiente e, agora, a configuração da Meta dentro do
        // modal Canal WhatsApp) - cada um manda só os campos que é dono, e um não pode apagar o
        // que o outro já salvou. Por isso cada valor só é gravado se vier preenchido (filled()).
        $data = $request->validate([
            'logo_light' => 'nullable|image|max:2048',
            'logo_dark' => 'nullable|image|max:2048',
            'login_bg' => 'nullable|image|max:4096',
            'footer_name' => 'nullable|string|max:100',
            'footer_version' => 'nullable|string|max:30',
            'footer_company' => 'nullable|string|max:150',
            'footer_year' => 'nullable|digits:4',
            'meta_app_id' => 'nullable|string|max:100',
            'meta_app_secret' => 'nullable|string|max:255',
            'meta_config_id' => 'nullable|string|max:100',
            'meta_webhook_verify_token' => 'nullable|string|max:255',
        ]);

        if ($request->hasFile('logo_light')) {
            Setting::setGlobal('app_logo_light_path', $request->file('logo_light')->store('branding', 'public'));
        }

        if ($request->hasFile('logo_dark')) {
            Setting::setGlobal('app_logo_dark_path', $request->file('logo_dark')->store('branding', 'public'));
        }

        if ($request->hasFile('login_bg')) {
            Setting::setGlobal('app_login_bg_path', $request->file('login_bg')->store('branding', 'public'));
        }

        foreach (['footer_name', 'footer_version', 'footer_company', 'footer_year'] as $key) {
            if ($request->filled($key)) {
                Setting::setGlobal('app_' . $key, trim($data[$key]));
            }
        }

        foreach (['meta_app_id', 'meta_app_secret', 'meta_config_id', 'meta_webhook_verify_token'] as $key) {
            if ($request->filled($key)) {
                Setting::setGlobal($key, trim($data[$key]));
            }
        }

        return response()->json(['success' => true, 'message' => 'Configurações atualizadas!'] + Setting::branding() + $this->metaConfig());
    }

    /**
     * meta_app_secret nunca volta pro front (so indica se ja foi preenchido) -
     * e um segredo que so deve trafegar na hora de salvar, nunca na leitura.
     */
    private function metaConfig(): array
    {
        return [
            'meta_app_id' => Setting::getGlobal('meta_app_id', ''),
            'meta_app_secret_set' => Setting::getGlobal('meta_app_secret') ? true : false,
            'meta_config_id' => Setting::getGlobal('meta_config_id', ''),
            'meta_webhook_verify_token' => Setting::getGlobal('meta_webhook_verify_token', ''),
        ];
    }
}
