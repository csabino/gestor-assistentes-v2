<?php

namespace App\Console\Commands;

use App\Http\Controllers\AssistantController;
use Illuminate\Console\Command;

class ProcessFollowupAutomations extends Command
{
    protected $signature = 'automation:process-followups';

    protected $description = 'Varre conversas paradas e dispara a automação de retomada de atendimento (mensagens de retomada e, ao esgotar as tentativas, encerramento automático)';

    public function handle(AssistantController $controller): int
    {
        $controller->processFollowupAutomations();
        $this->info('Automação de retomada processada.');
        return self::SUCCESS;
    }
}
