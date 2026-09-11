<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'theme' => 'required|in:light,dark',
        ]);

        $request->user()->update(['theme' => $data['theme']]);

        return response()->json(['success' => true]);
    }
}
