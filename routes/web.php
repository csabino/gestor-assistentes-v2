<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\OmniController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EnvironmentController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\SurveyController;

// Webhook do WhatsApp: chamado pelo provedor externo (Evolution/UazAPI), sem sessão de navegador.
// A UazAPI (uazapiGO) acrescenta um sufixo com o tipo de evento na URL configurada
// (ex: /webhook/whatsapp/1/messages/text, /webhook/whatsapp/1/connection/update),
// por isso a rota aceita qualquer coisa depois do {id}.
Route::match(['get', 'post', 'patch', 'put', 'delete'], '/webhook/whatsapp/{id}/{path?}', [AssistantController::class, 'webhook'])
    ->where('path', '.*')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Encaminhamento para o sistema Omni: endpoint público chamado por integração externa.
Route::post('/omni/send', [OmniController::class, 'forwardToOmni'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Widget de chat de teste: página pública compartilhável (link "Abrir chat"/"Copiar link"), sem login.
Route::get('/chat/{id}', [AssistantController::class, 'showChatWidget']);
Route::post('/chat/{id}/send', [AssistantController::class, 'chatWidgetSend']);

// Arquivos públicos (logotipo, fundo do login, anexos enviados a contatos externos via WhatsApp/Google Calendar), sem login.
Route::get('/files/{path}', [AssistantController::class, 'servePublicFileRoute'])->where('path', '.*');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::match(['get', 'post', 'patch', 'put', 'delete'], '/', function (\Illuminate\Http\Request $request) {
        // Servir arquivos públicos (avatares etc.) independe do papel do usuário.
        if ($request->has('view_file')) {
            return app(AssistantController::class)->index($request);
        }

        // Gestor e Agente só têm acesso ao módulo de calendário, qualquer que seja a view pedida.
        if (!$request->user()->isAdmin()) {
            return app(CalendarController::class)->handle($request);
        }

        if ($request->input('view') === 'equipe') return app(AgentController::class)->handle($request);
        if ($request->input('view') === 'agenda') return app(CalendarController::class)->handle($request);
        if ($request->input('view') === 'settings') return app(SettingController::class)->handle($request);
        if ($request->input('view') === 'surveys') return app(SurveyController::class)->handle($request);

        return app(AssistantController::class)->index($request);
    });

    Route::post('/profile', [ProfileController::class, 'update']);
    Route::post('/theme', [ThemeController::class, 'update']);

    Route::middleware('admin')->group(function () {
        Route::get('/settings/users', [UserController::class, 'index']);
        Route::post('/settings/users', [UserController::class, 'store']);
        Route::put('/settings/users/{user}', [UserController::class, 'update']);
        Route::delete('/settings/users/{user}', [UserController::class, 'destroy']);

        Route::get('/settings/environment', [EnvironmentController::class, 'index']);
        Route::post('/settings/environment', [EnvironmentController::class, 'update']);

        Route::patch('/assistants/{id}/rename', [AssistantController::class, 'rename']);
    });
});
