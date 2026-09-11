<?php

namespace App\Http\Controllers;

use App\Models\Assistant;
use App\Models\Department;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $assistantsQuery = Assistant::orderBy('name', 'asc');

        if ($statusFilter === 'ativo') {
            $assistantsQuery->where('is_active', 1);
        } elseif ($statusFilter === 'inativo') {
            $assistantsQuery->where('is_active', 0);
        }

        $allAssistants = $assistantsQuery->get();

        // Agente só enxerga os assistentes dos departamentos aos quais está vinculado.
        if ($isAgente) {
            $myAssistantIds = DB::table('department_user')
                ->join('departments', 'department_user.department_id', '=', 'departments.id')
                ->where('department_user.user_id', $user->id)
                ->pluck('departments.assistant_id')
                ->unique();

            $assistants = $allAssistants->whereIn('id', $myAssistantIds)->values();
        } else {
            $assistants = $allAssistants;
        }

        // 3. Define o Assistente atual
        $currentAssistantId = $request->input('assistant_id');

        if (!$currentAssistantId || !$assistants->contains('id', $currentAssistantId)) {
            $currentAssistantId = $assistants->isNotEmpty() ? $assistants->first()->id : null;
        }

        $currentAssistant = $assistants->firstWhere('id', $currentAssistantId);

        // 4. Popula os Agentes (usuários vinculados a departamentos do assistente atual).
        //    Para o Agente, a lista contém apenas ele mesmo.
        $agents = collect();
        if ($isAgente) {
            $myDept = $currentAssistantId
                ? $user->departments()->where('departments.assistant_id', $currentAssistantId)->first()
                : null;

            $agents->push((object) [
                'id' => $user->id,
                'name' => $user->name,
                'department_name' => $myDept->name ?? '',
            ]);
        } elseif ($currentAssistant) {
            $depts = Department::where('assistant_id', $currentAssistantId)->with('users')->get();
            foreach ($depts as $dept) {
                foreach ($dept->users as $u) {
                    $agents->push((object) [
                        'id' => $u->id,
                        'name' => $u->name,
                        'department_name' => $dept->name,
                    ]);
                }
            }
        }

        $agents = $agents->sortBy('name')->values();

        // 5. Agente selecionado. Para o Agente, é sempre ele mesmo, sem opção de "todos".
        if ($isAgente) {
            $currentAgentId = $user->id;
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
            $agentId = $user->id;
        } else {
            $agentId = $request->input('agent_id', 'all');
        }

        // Puxa o assistente_id diretamente da URL para não depender só da sessão
        $astId = $request->input('assistant_id') ?: session('last_agenda_ast_id');

        $query = Appointment::query()
            ->leftJoin('users', 'appointments.user_id', '=', 'users.id')
            ->select(
                'appointments.*',
                'users.name as agent_name',
                'users.id as agent_user_id'
            )
            ->where('appointments.status', '!=', 'cancelled');

        if ($agentId !== 'all' && $agentId) {
            $query->where('appointments.user_id', $agentId);
        } else {
            if ($astId) {
                $userIds = DB::table('department_user')
                    ->join('departments', 'department_user.department_id', '=', 'departments.id')
                    ->where('departments.assistant_id', $astId)
                    ->pluck('department_user.user_id')
                    ->unique()
                    ->toArray();

                $query->whereIn('appointments.user_id', $userIds);
            } else {
                return response()->json([]);
            }
        }

        $appointments = $query->get();

        // Nome do departamento do agente dentro do assistente atual (evita duplicar linhas com JOIN direto).
        $departmentNameByUserId = [];
        if ($astId) {
            $departmentNameByUserId = DB::table('department_user')
                ->join('departments', 'department_user.department_id', '=', 'departments.id')
                ->where('departments.assistant_id', $astId)
                ->pluck('departments.name', 'department_user.user_id')
                ->toArray();
        }

        $events = $appointments->map(function($app) use ($departmentNameByUserId) {
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
                    'department_name' => $departmentNameByUserId[$app->agent_user_id] ?? 'Geral',
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

        if ($request->user_id === 'all') {
            return response()->json(['success' => false, 'message' => 'Selecione um Agente específico no filtro para poder agendar ou bloquear horário.']);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $type = $request->input('type', 'block');

        Appointment::create([
            'user_id' => $request->user_id,
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
