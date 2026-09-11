<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentController extends Controller
{
    private function configureTimezone()
    {
        date_default_timezone_set('America/Sao_Paulo');
    }

    public function handle(Request $request)
    {
        $this->configureTimezone();

        if ($request->isMethod('post')) {
            $action = $request->input('action');
            if ($action === 'link_user') return $this->linkUser($request);
            if ($action === 'unlink_user') return $this->unlinkUser($request);

            if ($action === 'store_department') return $this->storeDepartment($request);
            if ($action === 'update_department') return $this->updateDepartment($request);
            if ($action === 'delete_department') return $this->deleteDepartment($request);
        }

        // Filtro de status (Padrão: ativo)
        $statusFilter = $request->query('status', 'ativo');

        // Busca os assistentes baseados no filtro de status
        $assistantsQuery = DB::table('assistants')->orderBy('name', 'asc');
        if ($statusFilter === 'ativo') {
            $assistantsQuery->where('is_active', 1);
        } elseif ($statusFilter === 'inativo') {
            $assistantsQuery->where('is_active', 0);
        }
        $assistants = $assistantsQuery->get();

        // Pega o ID do assistente na URL, se não tiver ou não existir na lista atual, pega o primeiro
        $selectedAssistantId = (int) $request->query('assistant_id');
        if (!$selectedAssistantId || !$assistants->contains('id', $selectedAssistantId)) {
            $selectedAssistantId = $assistants->isNotEmpty() ? $assistants->first()->id : 0;
        }

        // Busca apenas os departamentos DO ASSISTENTE SELECIONADO
        $departments = DB::table('departments')
            ->where('assistant_id', $selectedAssistantId)
            ->orderBy('name', 'asc')
            ->get();

        $deptIds = $departments->pluck('id');

        // Usuários (perfil Agente/Gestor) vinculados aos departamentos carregados
        $agents = DB::table('department_user')
            ->join('users', 'department_user.user_id', '=', 'users.id')
            ->whereIn('department_user.department_id', $deptIds)
            ->select('users.id', 'users.name', 'users.email', 'users.role', 'department_user.department_id')
            ->orderBy('users.name', 'asc')
            ->get();

        $linkedUserIdsByDept = $agents->groupBy('department_id')->map(fn ($g) => $g->pluck('id')->all());

        // Usuários elegíveis pra vincular (perfil Gestor ou Agente)
        $eligibleUsers = DB::table('users')
            ->whereIn('role', ['agente', 'gestor'])
            ->orderBy('name', 'asc')
            ->get();

        $currentView = 'equipe';

        return view('agents.index', compact('departments', 'agents', 'assistants', 'currentView', 'selectedAssistantId', 'statusFilter', 'eligibleUsers', 'linkedUserIdsByDept'));
    }

    private function storeDepartment(Request $request)
    {
        $assistantId = (int)$request->input('assistant_id');
        $statusFilter = $request->input('status', 'ativo');
        $request->validate(['name' => 'required|string|max:255']);

        // Força maiúscula no Back-end garantindo consistência
        $departmentName = mb_strtoupper(trim($request->name), 'UTF-8');

        DB::table('departments')->insert([
            'assistant_id' => $assistantId,
            'name' => $departmentName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect("/?view=equipe&status={$statusFilter}&assistant_id={$assistantId}")->with('success', 'Departamento criado com sucesso!');
    }

    private function updateDepartment(Request $request)
    {
        $assistantId = (int)$request->input('assistant_id');
        $statusFilter = $request->input('status', 'ativo');
        $request->validate([
            'department_id' => 'required|integer',
            'name' => 'required|string|max:255'
        ]);

        // Força maiúscula na atualização
        $departmentName = mb_strtoupper(trim($request->name), 'UTF-8');

        DB::table('departments')->where('id', $request->department_id)->update([
            'name' => $departmentName,
            'updated_at' => now(),
        ]);

        return redirect("/?view=equipe&status={$statusFilter}&assistant_id={$assistantId}")->with('success', 'Departamento atualizado!');
    }

    private function deleteDepartment(Request $request)
    {
        $assistantId = (int)$request->input('assistant_id');
        $statusFilter = $request->input('status', 'ativo');
        $deptId = (int)$request->input('department_id');

        DB::table('department_user')->where('department_id', $deptId)->delete();
        DB::table('departments')->where('id', $deptId)->delete();

        return redirect("/?view=equipe&status={$statusFilter}&assistant_id={$assistantId}")->with('success', 'Departamento excluído!');
    }

    private function linkUser(Request $request)
    {
        $assistantId = (int)$request->input('assistant_id');
        $statusFilter = $request->input('status', 'ativo');
        $departmentId = (int) $request->input('department_id');
        $userId = (int) $request->input('user_id');

        if (!$departmentId || !$userId) {
            return redirect("/?view=equipe&status={$statusFilter}&assistant_id={$assistantId}")->with('error', 'Selecione um usuário para vincular.');
        }

        DB::table('department_user')->insertOrIgnore([
            'department_id' => $departmentId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect("/?view=equipe&status={$statusFilter}&assistant_id={$assistantId}")->with('success', 'Usuário vinculado ao departamento!');
    }

    private function unlinkUser(Request $request)
    {
        $assistantId = (int)$request->input('assistant_id');
        $statusFilter = $request->input('status', 'ativo');
        $departmentId = (int) $request->input('department_id');
        $userId = (int) $request->input('user_id');

        DB::table('department_user')
            ->where('department_id', $departmentId)
            ->where('user_id', $userId)
            ->delete();

        return redirect("/?view=equipe&status={$statusFilter}&assistant_id={$assistantId}")->with('success', 'Usuário desvinculado do departamento!');
    }
}
