<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Documentação/testabilidade local (`php artisan schedule:list`) - em produção (EasyPanel,
        // sem nenhum processo de cron/schedule:work rodando) é um Cron Job separado no painel que
        // efetivamente dispara `php artisan automation:process-followups` a cada 5 minutos.
        $schedule->command('automation:process-followups')->everyFiveMinutes();
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'webhook/*',
            'webhook/whatsapp/*',
            'omni/send',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        // O cookie "theme" é gravado em texto puro pelo JS (document.cookie) e lido de volta
        // no servidor sem passar por decriptação, senão a leitura falha e cai sempre no padrão.
        $middleware->encryptCookies(except: ['theme']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();