<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Painel IA')</title>

    @php $navView = request()->query('view', 'robots'); @endphp

    @if($navView === 'settings')
        <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%234F46E5' stroke-width='2'><path stroke-linecap='round' stroke-linejoin='round' d='M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.27 1.06-.12 1.451l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.27-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.149-.894z'/><path stroke-linecap='round' stroke-linejoin='round' d='M15 12a3 3 0 11-6 0 3 3 0 016 0z'/></svg>">
    @elseif($navView === 'agenda')
        <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%234F46E5' stroke-width='2'><path stroke-linecap='round' stroke-linejoin='round' d='M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008z'/></svg>">
    @elseif($navView === 'equipe')
        <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%234F46E5' stroke-width='2'><path stroke-linecap='round' stroke-linejoin='round' d='M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z'/></svg>">
    @else
        <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%234F46E5' stroke-width='2'><path stroke-linecap='round' stroke-linejoin='round' d='M9.75 3.104v1.242c0 .289.23.523.518.523h3.726c.288 0 .518-.234.518-.523V3.104M12 21v-3.75m9-4.5h-1.5M4.5 12.75H3m16.5 0a2.25 2.25 0 002.25-2.25V8.25a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 8.25v2.25a2.25 2.25 0 002.25 2.25h13.5z'/></svg>">
    @endif

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
    </style>

    @stack('head')
</head>
<body class="bg-gray-50 font-sans text-gray-900 min-h-screen flex flex-col overflow-hidden" x-data="{
        userMenuOpen: false,
        profileModalOpen: false,
        profileSaving: false,
        profileError: null,
        user: {
            name: @js(auth()->user()->name),
            email: @js(auth()->user()->email),
            avatarUrl: @js(auth()->user()->avatar_path ? '/?view_file=' . auth()->user()->avatar_path : null),
            initials: @js(collect(explode(' ', auth()->user()->name))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode(''))
        },
        profileForm: { name: '', email: '', password: '', password_confirmation: '' },
        profileInitial: { name: '', email: '' },
        avatarPreview: null,
        avatarFile: null,
        get profileChanged() {
            return this.profileForm.name !== this.profileInitial.name
                || this.profileForm.email !== this.profileInitial.email
                || this.profileForm.password !== ''
                || this.profileForm.password_confirmation !== ''
                || this.avatarFile !== null;
        },
        openProfileModal() {
            this.profileForm = { name: this.user.name, email: this.user.email, password: '', password_confirmation: '' };
            this.profileInitial = { name: this.user.name, email: this.user.email };
            this.avatarPreview = null;
            this.avatarFile = null;
            this.profileError = null;
            this.profileModalOpen = true;
            this.userMenuOpen = false;
        },
        onAvatarFile(e) {
            const f = e.target.files[0];
            if (f) { this.avatarFile = f; this.avatarPreview = URL.createObjectURL(f); }
        },
        async saveProfile() {
            if (!this.profileChanged || this.profileSaving) return;
            this.profileSaving = true;
            this.profileError = null;
            const fd = new FormData();
            fd.append('name', this.profileForm.name);
            fd.append('email', this.profileForm.email);
            if (this.profileForm.password) {
                fd.append('password', this.profileForm.password);
                fd.append('password_confirmation', this.profileForm.password_confirmation);
            }
            if (this.avatarFile) fd.append('avatar', this.avatarFile);
            try {
                const res = await fetch('/profile', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: fd,
                });
                const json = await res.json();
                if (!res.ok) {
                    this.profileError = json.errors ? Object.values(json.errors).flat().join(' ') : (json.message || 'Erro ao salvar.');
                    return;
                }
                this.user.name = json.user.name;
                this.user.email = json.user.email;
                this.user.avatarUrl = json.user.avatar_url;
                this.user.initials = json.user.initials;
                this.profileModalOpen = false;
            } catch (e) {
                this.profileError = 'Erro de conexão. Tente novamente.';
            } finally {
                this.profileSaving = false;
            }
        }
    }">

    <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-6 shrink-0 z-40 shadow-sm">
        <div class="flex items-center gap-6 min-w-0">
            <a href="/" class="flex items-center gap-2 font-bold text-indigo-700 shrink-0">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z" />
                </svg>
                <span class="hidden sm:inline tracking-wide">Painel IA</span>
            </a>

            <nav class="flex items-center gap-1 text-sm font-semibold overflow-x-auto">
                <a href="/" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ $navView === 'robots' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">Assistentes</a>
                <a href="/?view=equipe" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ $navView === 'equipe' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">Equipe & Agendas</a>
                <a href="/?view=agenda" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ $navView === 'agenda' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">Calendário</a>
            </nav>
        </div>

        <div class="relative shrink-0" x-on:click.away="userMenuOpen = false">
            <button type="button" @click="userMenuOpen = !userMenuOpen" class="flex items-center gap-2 pl-2 pr-1 py-1 rounded-full hover:bg-gray-100 transition">
                <template x-if="user.avatarUrl">
                    <img :src="user.avatarUrl" alt="Avatar" class="w-8 h-8 rounded-full object-cover border border-gray-200">
                </template>
                <template x-if="!user.avatarUrl">
                    <span class="w-8 h-8 rounded-full bg-indigo-600 text-white text-xs font-bold flex items-center justify-center shrink-0" x-text="user.initials"></span>
                </template>
                <span class="hidden md:inline text-sm font-semibold text-gray-700 max-w-[140px] truncate" x-text="user.name"></span>
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
            </button>

            <div x-show="userMenuOpen" x-cloak x-transition class="absolute right-0 mt-2 w-56 bg-white border border-gray-200 rounded-xl shadow-lg py-1.5 text-sm z-50">
                <div class="px-4 py-2 border-b border-gray-100">
                    <p class="font-bold text-gray-800 truncate" x-text="user.name"></p>
                    <p class="text-xs text-gray-500 truncate" x-text="user.email"></p>
                </div>

                <button type="button" @click="openProfileModal()" class="w-full flex items-center gap-2.5 px-4 py-2 text-gray-700 hover:bg-gray-50 text-left transition">
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                    Meu perfil
                </button>

                <a href="/settings/users" class="flex items-center gap-2.5 px-4 py-2 text-gray-700 hover:bg-gray-50 transition">
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.27 1.06-.12 1.451l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.27-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.149-.894z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Configurações gerais
                </a>

                <form action="/logout" method="POST" class="border-t border-gray-100 mt-1 pt-1">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-2.5 px-4 py-2 text-red-600 hover:bg-red-50 text-left transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg>
                        Sair
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main id="mainContent" class="flex-1 min-w-0 overflow-y-auto">
        @yield('content')
    </main>

    <!-- MODAL MEU PERFIL -->
    <div x-show="profileModalOpen" x-cloak x-transition @keydown.escape.window="profileModalOpen = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div @click.away="profileModalOpen = false" class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6 relative border border-slate-200">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-base font-bold text-gray-800">Meu Perfil</h3>
                <button type="button" @click="profileModalOpen = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div x-show="profileError" x-cloak class="bg-red-50 border border-red-200 text-red-700 text-xs px-3 py-2 rounded-lg mb-4" x-text="profileError"></div>

            <div class="flex items-center gap-4 mb-5">
                <template x-if="!avatarPreview && user.avatarUrl">
                    <img :src="user.avatarUrl" alt="Avatar" class="w-14 h-14 rounded-full object-cover border border-gray-200">
                </template>
                <template x-if="!avatarPreview && !user.avatarUrl">
                    <span class="w-14 h-14 rounded-full bg-indigo-600 text-white text-lg font-bold flex items-center justify-center" x-text="user.initials"></span>
                </template>
                <template x-if="avatarPreview">
                    <img :src="avatarPreview" alt="Avatar" class="w-14 h-14 rounded-full object-cover border border-gray-200">
                </template>
                <div>
                    <label class="inline-block text-xs font-semibold text-indigo-600 hover:text-indigo-700 cursor-pointer">
                        Alterar foto
                        <input type="file" accept="image/*" class="hidden" @change="onAvatarFile">
                    </label>
                    <p class="text-[11px] text-gray-400 mt-0.5">JPG, PNG ou WEBP, até 2MB.</p>
                </div>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Nome</label>
                    <input type="text" x-model="profileForm.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">E-mail</label>
                    <input type="email" x-model="profileForm.email" autocomplete="username" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Nova Senha</label>
                        <input type="password" x-model="profileForm.password" autocomplete="new-password" placeholder="Manter atual" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Confirmar</label>
                        <input type="password" x-model="profileForm.password_confirmation" autocomplete="new-password" placeholder="Repita a senha" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button type="button" @click="profileModalOpen = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition">Voltar</button>
                <button type="button" @click="saveProfile()" :disabled="!profileChanged || profileSaving" :class="(!profileChanged || profileSaving) ? 'opacity-50 cursor-not-allowed' : ''" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition">
                    <span x-show="!profileSaving">Salvar</span>
                    <span x-show="profileSaving">Salvando...</span>
                </button>
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
