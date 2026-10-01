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
        $data = $request->validate([
            'logo_light' => 'nullable|image|max:2048',
            'logo_dark' => 'nullable|image|max:2048',
            'login_bg' => 'nullable|image|max:4096',
            'footer_name' => 'required|string|max:100',
            'footer_version' => 'required|string|max:30',
            'footer_company' => 'required|string|max:150',
            'footer_year' => 'required|digits:4',
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

        Setting::setGlobal('app_footer_name', $data['footer_name']);
        Setting::setGlobal('app_footer_version', $data['footer_version']);
        Setting::setGlobal('app_footer_company', $data['footer_company']);
        Setting::setGlobal('app_footer_year', $data['footer_year']);

        Setting::setGlobal('meta_app_id', trim($data['meta_app_id'] ?? ''));
        if ($request->filled('meta_app_secret')) {
            Setting::setGlobal('meta_app_secret', trim($data['meta_app_secret']));
        }
        Setting::setGlobal('meta_config_id', trim($data['meta_config_id'] ?? ''));
        Setting::setGlobal('meta_webhook_verify_token', trim($data['meta_webhook_verify_token'] ?? ''));

        return response()->json(['success' => true, 'message' => 'Ambiente atualizado com sucesso!'] + Setting::branding() + $this->metaConfig());
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
