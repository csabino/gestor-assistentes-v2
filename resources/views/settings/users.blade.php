@extends('layouts.app')

@section('title', 'Configurações Gerais - Usuários')

@section('content')
        <div class="container mx-auto px-6 max-w-5xl py-8" x-data="{
            editModal: false,
            editData: { id: null, name: '', email: '' }
        }">

            @if(session('success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 text-sm shadow-sm">
                    <ul class="list-disc ml-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-6">
                <h1 class="text-xl font-bold text-gray-800">Configurações Gerais</h1>
                <p class="text-xs text-gray-500 mt-1">Gerencie os usuários que têm acesso ao painel administrativo.</p>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 mb-6">
                <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">Novo Usuário</h2>
                <form action="/settings/users" method="POST" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                    @csrf
                    <div class="sm:col-span-1">
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Nome</label>
                        <input type="text" name="name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div class="sm:col-span-1">
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">E-mail</label>
                        <input type="email" name="email" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div class="sm:col-span-1">
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Senha</label>
                        <input type="password" name="password" required minlength="8" autocomplete="new-password" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div class="sm:col-span-1">
                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 rounded-lg text-sm transition shadow-sm">
                            + Adicionar Usuário
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider">
                            <th class="py-3 px-5 font-semibold">Nome</th>
                            <th class="py-3 px-5 font-semibold">E-mail</th>
                            <th class="py-3 px-5 font-semibold text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($users as $u)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="py-3 px-5 font-bold text-gray-800">{{ $u->name }}</td>
                                <td class="py-3 px-5 text-gray-600 font-mono text-xs">{{ $u->email }}</td>
                                <td class="py-3 px-5 text-right">
                                    <div class="flex justify-end items-center gap-2">
                                        <button type="button" @click="editData = { id: {{ $u->id }}, name: '{{ $u->name }}', email: '{{ $u->email }}' }; editModal = true" class="text-indigo-600 hover:bg-indigo-50 px-2.5 py-1.5 rounded-md text-xs font-bold transition">
                                            Editar
                                        </button>
                                        <form action="/settings/users/{{ $u->id }}" method="POST" onsubmit="return confirm('Remover este usuário?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:bg-red-50 px-2.5 py-1.5 rounded-md text-xs font-bold transition">
                                                Excluir
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center py-8 text-gray-400">Nenhum usuário cadastrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- MODAL EDITAR USUÁRIO -->
            <div x-show="editModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4" x-transition>
                <div @click.away="editModal = false" class="bg-white rounded-xl shadow-2xl max-w-sm w-full p-6 relative border border-slate-200">
                    <h3 class="text-base font-bold text-gray-800 mb-4">Editar Usuário</h3>
                    <form :action="'/settings/users/' + editData.id" method="POST">
                        @csrf @method('PUT')
                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Nome</label>
                        <input type="text" name="name" x-model="editData.name" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-xs mb-3 outline-none focus:border-indigo-500">

                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">E-mail</label>
                        <input type="email" name="email" x-model="editData.email" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-xs mb-3 outline-none focus:border-indigo-500">

                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Nova Senha (opcional)</label>
                        <input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Deixe em branco para manter a atual" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-xs mb-5 outline-none focus:border-indigo-500">

                        <div class="flex justify-end gap-2">
                            <button type="button" @click="editModal = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition">Cancelar</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
@endsection
