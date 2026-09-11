<?php

namespace App\Http\Controllers;

use App\Models\Assistant;
use App\Models\HumanAgent;
use App\Models\Appointment;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function handle(Request $request)
    {
        $action = $request->input('action');

        if ($action === 'get_events') return $this->getEvents($request);
        if ($action === 'store_event') return $this->storeEvent($request);
        if ($action === 'update_event') return $this->updateEvent($request);
        if ($action === 'delete_event') return $this->destroyEvent($request);

        return $this->index($request);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $isAgente = $user->isAgente();
        $readOnly = $isAgente;
        $lockedAgent = $isAgente;

        // 1. Filtro de Status (Padrão: ativo)
        $statusFilter = $request->input('status', 'ativo');

        // 2. Busca assistentes com base no status
        $assistantsQuery = Assistant::with(['departments.agents'])->orderBy('name', 'asc');

        if ($statusFilter === 'ativo') {
            $assistantsQuery->where('is_active', 1);
        } elseif ($statusFilter === 'inativo') {
            $assistantsQuery->where('is_active', 0);
        }

        $assistants = $assistantsQuery->get();

        // 3. Define o Assistente atual. Para o Agente, é sempre o assistente do seu próprio setor.
        if ($isAgente) {
            $currentAssistant = $user->humanAgent?->department?->assistant;
            $currentAssistantId = $currentAssistant?->id;
        } else {
            $currentAssistantId = $request->input('assistant_id');

            if (!$currentAssistantId || !$assistants->contains('id', $currentAssistantId)) {
                $currentAssistantId = $assistants->isNotEmpty() ? $assistants->first()->id : null;
            }

            $currentAssistant = $assistants->firstWhere('id', $currentAssistantId);
        }

        // 4. Popula os Agentes. Para o Agente, a lista contém apenas ele mesmo.
        $agents = collect();
        if ($isAgente) {
            if ($user->humanAgent) {
                $ag = $user->humanAgent;
                $ag->department_name = $ag->department->name ?? '';
                $agents->push($ag);
            }
        } elseif ($currentAssistant) {
            foreach ($currentAssistant->departments as $dept) {
                foreach ($dept->agents as $ag) {
                    $ag->department_name = $dept->name;
                    $agents->push($ag);
                }
            }
        }

        $agents = $agents->sortBy('name')->values();

        // 5. Agente selecionado. Para o Agente, é sempre o seu próprio, sem opção de "todos".
        if ($isAgente) {
            $currentAgentId = $user->human_agent_id ?: 'all';
        } else {
            $currentAgentId = $request->input('agent_id', 'all');
            if ($currentAgentId !== 'all' && !$agents->contains('id', $currentAgentId)) {
                $currentAgentId = 'all';
            }
        }

        // Guarda o assistente selecionado pra API de eventos saber qual buscar se for "all"
        session(['last_agenda_ast_id' => $currentAssistantId]);

        return view('calendar.index', compact('assistants', 'currentAssistantId', 'agents', 'currentAgentId', 'statusFilter', 'readOnly', 'lockedAgent'));
    }

    private function getEvents(Request $request)
    {
        $user = $request->user();

        if ($user->isAgente()) {
            // Agente só pode ver os próprios compromissos, ignora qualquer filtro enviado.
            $agentId = $user->human_agent_id;
            if (!$agentId) {
                return response()->json([]);
            }
        } else {
            $agentId = $request->input('agent_id', 'all');
        }

        // Puxa o assistente_id diretamente da URL para não depender só da sessão
        $astId = $request->input('assistant_id') ?: session('last_agenda_ast_id');
        
        // Retornamos ao uso do Appointment::query() para evitar bugs de conversão do Laravel
        $query = Appointment::query()
            ->leftJoin('human_agents', 'appointments.human_agent_id', '=', 'human_agents.id')
            ->leftJoin('departments', 'human_agents.department_id', '=', 'departments.id')
            ->select(
                'appointments.*',
                'human_agents.name as agent_name',
                'departments.name as department_name'
            )
            ->where('appointments.status', '!=', 'cancelled');
        
        if ($agentId !== 'all' && $agentId) {
            $query->where('appointments.human_agent_id', $agentId);
        } else {
            if ($astId) {
                $agentIds = \Illuminate\Support\Facades\DB::table('human_agents')
                    ->join('departments', 'human_agents.department_id', '=', 'departments.id')
                    ->where('departments.assistant_id', $astId)
                    ->pluck('human_agents.id')
                    ->toArray();

                $query->whereIn('appointments.human_agent_id', $agentIds);
            } else {
                return response()->json([]);
            }
        }

        $appointments = $query->get();
        
        $events = $appointments->map(function($app) {
            $isBlock = ($app->client_name === 'BLOQUEIO_MANUAL');
            
            // Garante a conversão blindada da data para o calendário ler sem dar erro
            $startTime = $app->start_time instanceof \Carbon\Carbon ? $app->start_time : \Carbon\Carbon::parse($app->start_time);
            $endTime = $app->end_time instanceof \Carbon\Carbon ? $app->end_time : \Carbon\Carbon::parse($app->end_time);

            return [
                'id' => $app->id,
                'title' => $isBlock ? '🚫 Indisponível' : "Reunião com {$app->client_name}",
                'start' => $startTime->format('Y-m-d\TH:i:s'),
                'end' => $endTime->format('Y-m-d\TH:i:s'),
                'backgroundColor' => $isBlock ? '#ef4444' : '#4f46e5',
                'borderColor' => $isBlock ? '#dc2626' : '#4338ca',
                'extendedProps' => [
                    'type' => $isBlock ? 'block' : 'appointment',
                    'client_name' => $app->client_name ?? 'Cliente',
                    'client_email' => $app->client_email ?? '-',
                    'client_phone' => $app->client_phone ?? '-',
                    'agent_name' => $app->agent_name ?? 'Não atribuído',
                    'department_name' => $app->department_name ?? 'Geral',
                    'status' => $app->status === 'rescheduled' ? 'Reagendada' : 'Agendada',
                    'start_formatted' => $startTime->format('d/m/Y \à\s H:i'),
                    'end_formatted' => $endTime->format('H:i')
                ]
            ];
        });

        return response()->json($events);
    }

    private function storeEvent(Request $request)
    {
        if ($request->user()->isAgente()) {
            abort(403, 'Agentes têm acesso somente para consulta da agenda.');
        }

        if ($request->human_agent_id === 'all') {
            return response()->json(['success' => false, 'message' => 'Selecione um Agente específico no filtro para poder agendar ou bloquear horário.']);
        }

        $request->validate([
            'human_agent_id' => 'required|exists:human_agents,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $type = $request->input('type', 'block');
        
        Appointment::create([
            'human_agent_id' => $request->human_agent_id,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'client_name' => $type === 'block' ? 'BLOQUEIO_MANUAL' : $request->client_name,
            'client_phone' => $type === 'block' ? '00000000000' : $request->client_phone,
            'client_email' => $type === 'block' ? 'bloqueio@interno' : $request->client_email,
            'status' => 'scheduled'
        ]);

        return response()->json(['success' => true]);
    }

    private function updateEvent(Request $request)
    {
        if ($request->user()->isAgente()) {
            abort(403, 'Agentes têm acesso somente para consulta da agenda.');
        }

        $app = Appointment::findOrFail($request->id);
        $app->update([
            'start_time' => $request->start_time,
            'end_time' => $request->end_time
        ]);
        return response()->json(['success' => true]);
    }

    private function destroyEvent(Request $request)
    {
        if ($request->user()->isAgente()) {
            abort(403, 'Agentes têm acesso somente para consulta da agenda.');
        }

        Appointment::findOrFail($request->id)->delete();
        return response()->json(['success' => true]);
    }
}