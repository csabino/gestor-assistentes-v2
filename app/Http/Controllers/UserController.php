<?php

namespace App\Http\Controllers;

use App\Models\HumanAgent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('humanAgent')->orderBy('name')->get()->map(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'human_agent_id' => $user->human_agent_id,
                'human_agent_name' => $user->humanAgent->name ?? null,
            ];
        });

        return response()->json([
            'users' => $users,
            'human_agents' => $this->humanAgentOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'human_agent_id' => $data['role'] === 'agente' ? $data['human_agent_id'] : null,
        ]);

        return response()->json(['success' => true, 'message' => 'Usuário criado com sucesso!']);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validateData($request, $user);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        $user->human_agent_id = $data['role'] === 'agente' ? $data['human_agent_id'] : null;

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return response()->json(['success' => true, 'message' => 'Usuário atualizado com sucesso!']);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Você não pode excluir o seu próprio usuário.'], 422);
        }

        $user->delete();

        return response()->json(['success' => true, 'message' => 'Usuário removido com sucesso!']);
    }

    private function validateData(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email' . ($user ? ',' . $user->id : ''),
            'password' => ($user ? 'nullable' : 'required') . '|string|min:8|confirmed',
            'role' => 'required|in:admin,gestor,agente',
            'human_agent_id' => 'nullable|required_if:role,agente|exists:human_agents,id',
        ]);
    }

    private function humanAgentOptions()
    {
        return HumanAgent::with('department.assistant')->orderBy('name')->get()->map(function (HumanAgent $agent) {
            return [
                'id' => $agent->id,
                'label' => $agent->name . ' — ' . ($agent->department->name ?? '') . ' (' . ($agent->department->assistant->name ?? '') . ')',
            ];
        });
    }
}
