<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('departments.assistant')->orderBy('name')->get()->map(function (User $user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'department_ids' => $user->departments->pluck('id'),
                'department_names' => $user->departments->map(fn ($d) => $d->name . ' (' . ($d->assistant->name ?? '') . ')')->implode(', '),
            ];
        });

        return response()->json([
            'users' => $users,
            'departments' => $this->departmentOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        if ($data['role'] !== 'admin') {
            $user->departments()->sync($data['department_ids'] ?? []);
        }

        return response()->json(['success' => true, 'message' => 'Usuário criado com sucesso!']);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validateData($request, $user);

        if ($user->id === $request->user()->id && array_key_exists('is_active', $data) && !$data['is_active']) {
            return response()->json(['success' => false, 'message' => 'Você não pode inativar o seu próprio usuário.'], 422);
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        $user->is_active = $data['is_active'] ?? $user->is_active;

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        $user->departments()->sync($data['role'] !== 'admin' ? ($data['department_ids'] ?? []) : []);

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
        $departmentIdsRule = $request->input('role') === 'agente' ? 'required|array|min:1' : 'nullable|array';

        return $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email' . ($user ? ',' . $user->id : ''),
            'password' => ($user ? 'nullable' : 'required') . '|string|min:8|confirmed',
            'role' => 'required|in:admin,gestor,agente',
            'is_active' => 'boolean',
            'department_ids' => $departmentIdsRule,
            'department_ids.*' => 'exists:departments,id',
        ]);
    }

    private function departmentOptions()
    {
        return Department::with('assistant')->orderBy('name')->get()->map(function (Department $dept) {
            return [
                'id' => $dept->id,
                'label' => $dept->name . ' (' . ($dept->assistant->name ?? '') . ')',
            ];
        });
    }
}
