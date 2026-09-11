<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'theme' => 'required|in:light,dark',
        ]);

        $request->user()->update(['theme' => $data['theme']]);

        // A tela de login (sem usuário logado) segue sempre o último tema escolhido por alguém no sistema.
        Setting::setGlobal('app_theme_default', $data['theme']);

        return response()->json(['success' => true]);
    }
}
