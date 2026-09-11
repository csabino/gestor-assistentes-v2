@extends('layouts.app')

@section('title', 'Meu Perfil')

@section('content')
        <div class="container mx-auto px-6 max-w-3xl py-8">

            @if(session('success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    {{ session('success') }}
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
                <h1 class="text-xl font-bold text-gray-800">Meu Perfil</h1>
                <p class="text-xs text-gray-500 mt-1">Atualize seus dados de acesso ao painel.</p>
            </div>

            <form action="/profile" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 space-y-6"
                x-data="{ preview: null, onFile(e) { const f = e.target.files[0]; if (f) this.preview = URL.createObjectURL(f); } }">
                @csrf

                <div class="flex items-center gap-5">
                    <template x-if="!preview">
                        @if($user->avatar_path)
                            <img src="/?view_file={{ $user->avatar_path }}" alt="Avatar" class="w-16 h-16 rounded-full object-cover border border-gray-200">
                        @else
                            <span class="w-16 h-16 rounded-full bg-indigo-600 text-white text-xl font-bold flex items-center justify-center">
                                {{ collect(explode(' ', $user->name))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                            </span>
                        @endif
                    </template>
                    <template x-if="preview">
                        <img :src="preview" alt="Avatar" class="w-16 h-16 rounded-full object-cover border border-gray-200">
                    </template>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Foto de Perfil</label>
                        <input type="file" name="avatar" accept="image/*" @change="onFile" class="block text-sm text-gray-500 border border-gray-200 rounded-lg p-1.5">
                        <p class="text-[11px] text-gray-400 mt-1">JPG, PNG ou WEBP, até 2MB.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-gray-100 pt-6">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Nome</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">E-mail</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-gray-100 pt-6">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Nova Senha</label>
                        <input type="password" name="password" placeholder="Deixe em branco para manter a atual" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Confirmar Nova Senha</label>
                        <input type="password" name="password_confirmation" placeholder="Repita a nova senha" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                </div>

                <div class="flex justify-end border-t border-gray-100 pt-6">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-lg text-sm transition shadow-sm">
                        Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
@endsection
