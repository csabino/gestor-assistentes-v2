@php
    $branding = \App\Models\Setting::branding();
    $effectiveTheme = auth()->user()->theme ?? $branding['theme_default'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR" class="{{ $effectiveTheme === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Painel IA')</title>

    @php $navView = auth()->user()->isAdmin() ? request()->query('view', 'robots') : 'agenda'; @endphp

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
    <script>tailwind.config = { darkMode: 'class' };</script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
    </style>

    @stack('head')
</head>
<body class="bg-gray-50 dark:bg-gray-900 font-sans text-gray-900 dark:text-gray-100 min-h-screen flex flex-col overflow-hidden" x-data="{
        theme: '{{ $effectiveTheme }}',
        toggleTheme() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';
            document.documentElement.classList.toggle('dark', this.theme === 'dark');
            fetch('/theme', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({ theme: this.theme }),
            });
        },
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
        },

        usersModalOpen: false,
        usersLoading: false,
        usersError: null,
        usersList: [],
        departmentOptions: [],
        userForm: { id: null, name: '', email: '', password: '', password_confirmation: '', role: 'agente', is_active: true, department_ids: [] },
        userFormMode: 'create',
        openUsersModal() {
            this.usersModalOpen = true;
            this.userMenuOpen = false;
            this.resetUserForm();
            this.loadUsers();
        },
        resetUserForm() {
            this.userForm = { id: null, name: '', email: '', password: '', password_confirmation: '', role: 'agente', is_active: true, department_ids: [] };
            this.userFormMode = 'create';
            this.usersError = null;
        },
        editUser(u) {
            this.userForm = { id: u.id, name: u.name, email: u.email, password: '', password_confirmation: '', role: u.role, is_active: !!u.is_active, department_ids: [...(u.department_ids || [])] };
            this.userFormMode = 'edit';
            this.usersError = null;
        },
        toggleDept(id) {
            const idx = this.userForm.department_ids.indexOf(id);
            if (idx === -1) this.userForm.department_ids.push(id);
            else this.userForm.department_ids.splice(idx, 1);
        },
        async loadUsers() {
            this.usersLoading = true;
            this.usersError = null;
            try {
                const res = await fetch('/settings/users', { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || 'Erro ao carregar usuários.');
                this.usersList = json.users;
                this.departmentOptions = json.departments;
            } catch (e) {
                this.usersError = e.message || 'Erro ao carregar usuários.';
            } finally {
                this.usersLoading = false;
            }
        },
        async saveUser() {
            this.usersLoading = true;
            this.usersError = null;
            const isEdit = this.userFormMode === 'edit';
            const payload = {
                name: this.userForm.name,
                email: this.userForm.email,
                role: this.userForm.role,
                is_active: this.userForm.is_active,
                department_ids: this.userForm.role === 'admin' ? [] : this.userForm.department_ids,
            };
            if (this.userForm.password || !isEdit) {
                payload.password = this.userForm.password;
                payload.password_confirmation = this.userForm.password_confirmation;
            }
            try {
                const res = await fetch(isEdit ? ('/settings/users/' + this.userForm.id) : '/settings/users', {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify(payload),
                });
                const json = await res.json();
                if (!res.ok) {
                    this.usersError = json.errors ? Object.values(json.errors).flat().join(' ') : (json.message || 'Erro ao salvar usuário.');
                    return;
                }
                await this.loadUsers();
                this.resetUserForm();
            } catch (e) {
                this.usersError = 'Erro de conexão. Tente novamente.';
            } finally {
                this.usersLoading = false;
            }
        },
        async toggleActive(u) {
            this.usersLoading = true;
            try {
                const res = await fetch('/settings/users/' + u.id, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        name: u.name,
                        email: u.email,
                        role: u.role,
                        is_active: !u.is_active,
                        department_ids: u.department_ids,
                    }),
                });
                const json = await res.json();
                if (!res.ok || json.success === false) {
                    alert(json.message || 'Erro ao atualizar usuário.');
                    return;
                }
                await this.loadUsers();
            } finally {
                this.usersLoading = false;
            }
        },
        async deleteUser(u) {
            if (!confirm('Remover o usuário ' + u.name + '?')) return;
            this.usersLoading = true;
            try {
                const res = await fetch('/settings/users/' + u.id, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                });
                const json = await res.json();
                if (!res.ok || json.success === false) {
                    alert(json.message || 'Erro ao excluir usuário.');
                    return;
                }
                await this.loadUsers();
            } finally {
                this.usersLoading = false;
            }
        },

        envModalOpen: false,
        envLoading: false,
        envError: null,
        envLogoLightUrl: @js($branding['logo_light_url']),
        envLogoDarkUrl: @js($branding['logo_dark_url']),
        envLoginBgUrl: @js($branding['login_bg_url']),
        envLogoLightFile: null,
        envLogoLightPreview: null,
        envLogoDarkFile: null,
        envLogoDarkPreview: null,
        envLoginBgFile: null,
        envLoginBgPreview: null,
        envForm: {
            footer_name: @js($branding['footer_name']),
            footer_version: @js($branding['footer_version']),
            footer_company: @js($branding['footer_company']),
            footer_year: @js($branding['footer_year']),
        },
        openEnvModal() {
            this.envModalOpen = true;
            this.userMenuOpen = false;
            this.envError = null;
        },
        onEnvLogoLightFile(e) {
            const f = e.target.files[0];
            if (f) { this.envLogoLightFile = f; this.envLogoLightPreview = URL.createObjectURL(f); }
        },
        onEnvLogoDarkFile(e) {
            const f = e.target.files[0];
            if (f) { this.envLogoDarkFile = f; this.envLogoDarkPreview = URL.createObjectURL(f); }
        },
        onEnvLoginBgFile(e) {
            const f = e.target.files[0];
            if (f) { this.envLoginBgFile = f; this.envLoginBgPreview = URL.createObjectURL(f); }
        },
        async saveEnvironment() {
            this.envLoading = true;
            this.envError = null;
            const fd = new FormData();
            fd.append('footer_name', this.envForm.footer_name);
            fd.append('footer_version', this.envForm.footer_version);
            fd.append('footer_company', this.envForm.footer_company);
            fd.append('footer_year', this.envForm.footer_year);
            if (this.envLogoLightFile) fd.append('logo_light', this.envLogoLightFile);
            if (this.envLogoDarkFile) fd.append('logo_dark', this.envLogoDarkFile);
            if (this.envLoginBgFile) fd.append('login_bg', this.envLoginBgFile);
            try {
                const res = await fetch('/settings/environment', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: fd,
                });
                const json = await res.json();
                if (!res.ok) {
                    this.envError = json.errors ? Object.values(json.errors).flat().join(' ') : (json.message || 'Erro ao salvar.');
                    return;
                }
                this.envLogoLightUrl = json.logo_light_url;
                this.envLogoDarkUrl = json.logo_dark_url;
                this.envLoginBgUrl = json.login_bg_url;
                this.envLogoLightFile = null;
                this.envLogoLightPreview = null;
                this.envLogoDarkFile = null;
                this.envLogoDarkPreview = null;
                this.envLoginBgFile = null;
                this.envLoginBgPreview = null;
                this.envModalOpen = false;
            } catch (e) {
                this.envError = 'Erro de conexão. Tente novamente.';
            } finally {
                this.envLoading = false;
            }
        }
    }">

    <header class="h-16 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between px-4 sm:px-6 shrink-0 z-40 shadow-sm">
        <div class="flex items-center gap-6 min-w-0">
            <a href="/" class="flex items-center gap-2 font-bold text-indigo-700 dark:text-indigo-400 shrink-0">
                @php $headerLogo = $effectiveTheme === 'dark' ? ($branding['logo_dark_url'] ?? $branding['logo_light_url']) : ($branding['logo_light_url'] ?? $branding['logo_dark_url']); @endphp
                @if($headerLogo)
                    <img src="{{ $headerLogo }}" alt="Logotipo" class="h-11 w-11 object-contain">
                @endif
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z" />
                </svg>
                <span class="hidden sm:inline tracking-wide">Painel IA</span>
            </a>

            <nav class="flex items-center gap-1 text-sm font-semibold overflow-x-auto">
                @if(auth()->user()->isAdmin())
                    <a href="/" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ $navView === 'robots' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">Assistentes</a>
                    <a href="/?view=equipe" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ $navView === 'equipe' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">Equipe & Agendas</a>
                @endif
                <a href="/?view=agenda" class="px-3 py-2 rounded-lg transition whitespace-nowrap {{ $navView === 'agenda' ? 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">Calendário</a>
            </nav>
        </div>

        <div class="flex items-center gap-2 shrink-0">
        <button type="button" @click="toggleTheme()" title="Alternar tema claro/escuro" class="p-2 rounded-full text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
            <svg x-show="theme === 'light'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" /></svg>
            <svg x-show="theme === 'dark'" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-6.364-.386 1.591-1.591M3 12h2.25m.386-6.364 1.591 1.591M16.5 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" /></svg>
        </button>
        <div class="relative" x-on:click.away="userMenuOpen = false">
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

                @if(auth()->user()->isAdmin())
                    <button type="button" @click="openUsersModal()" class="w-full flex items-center gap-2.5 px-4 py-2 text-gray-700 hover:bg-gray-50 text-left transition">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                        Usuários
                    </button>

                    <button type="button" @click="openEnvModal()" class="w-full flex items-center gap-2.5 px-4 py-2 text-gray-700 hover:bg-gray-50 text-left transition">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" /></svg>
                        Ambiente
                    </button>
                @endif

                <form action="/logout" method="POST" class="border-t border-gray-100 mt-1 pt-1">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-2.5 px-4 py-2 text-red-600 hover:bg-red-50 text-left transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg>
                        Sair
                    </button>
                </form>
            </div>
        </div>
        </div>
    </header>

    <main id="mainContent" class="flex-1 min-w-0 overflow-y-auto">
        @yield('content')
    </main>

    <footer class="shrink-0 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-center text-[11px] text-gray-400 dark:text-gray-500 py-2 px-4">
        {{ $branding['footer_name'] }} {{ $branding['footer_version'] }} &middot; &copy; {{ $branding['footer_year'] }} {{ $branding['footer_company'] }}
    </footer>

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

    <!-- MODAL USUÁRIOS -->
    <div x-show="usersModalOpen" x-cloak x-transition @keydown.escape.window="usersModalOpen = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div @click.away="usersModalOpen = false" class="bg-white rounded-xl shadow-2xl max-w-5xl w-full p-6 relative border border-slate-200 max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between mb-5 shrink-0">
                <div>
                    <h3 class="text-base font-bold text-gray-800">Usuários</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Gerencie os usuários que têm acesso ao painel administrativo.</p>
                </div>
                <button type="button" @click="usersModalOpen = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div x-show="usersError" x-cloak class="bg-red-50 border border-red-200 text-red-700 text-xs px-3 py-2 rounded-lg mb-4 shrink-0" x-text="usersError"></div>

            <div class="flex flex-col md:flex-row gap-5 flex-1 min-h-0">
                <!-- FORMULÁRIO: ESQUERDA (campos rolam, botão fica fixo no rodapé) -->
                <div class="md:w-96 shrink-0 bg-gray-50 border border-gray-200 rounded-lg flex flex-col overflow-hidden">
                <div class="p-4 overflow-y-auto flex-1 min-h-0 custom-scroll">
                    <h4 class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-3" x-text="userFormMode === 'edit' ? 'Editar Usuário' : 'Novo Usuário'"></h4>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Nome</label>
                            <input type="text" x-model="userForm.name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">E-mail</label>
                            <input type="email" x-model="userForm.email" autocomplete="off" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1" x-text="userFormMode === 'edit' ? 'Nova Senha' : 'Senha'"></label>
                                <input type="password" x-model="userForm.password" autocomplete="new-password" :placeholder="userFormMode === 'edit' ? 'Manter atual' : ''" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Confirmar</label>
                                <input type="password" x-model="userForm.password_confirmation" autocomplete="new-password" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Perfil</label>
                            <select x-model="userForm.role" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                                <option value="admin">Admin</option>
                                <option value="gestor">Gestor</option>
                                <option value="agente">Agente</option>
                            </select>
                        </div>
                        <div x-show="userForm.role !== 'admin'" x-cloak>
                            <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">
                                Assistentes / Departamentos <span x-show="userForm.role === 'agente'" class="text-red-500">*</span>
                            </label>
                            <div class="border border-gray-300 rounded-lg max-h-36 overflow-y-auto p-2 space-y-1 bg-white">
                                <template x-for="dept in departmentOptions" :key="dept.id">
                                    <label class="flex items-center gap-2 text-xs text-gray-700 py-0.5 cursor-pointer">
                                        <input type="checkbox" :checked="userForm.department_ids.includes(dept.id)" @change="toggleDept(dept.id)" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span x-text="dept.label"></span>
                                    </label>
                                </template>
                                <p x-show="departmentOptions.length === 0" class="text-[11px] text-gray-400">Nenhum departamento cadastrado.</p>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1">Um usuário pode estar em um ou mais departamentos/assistentes.</p>
                        </div>
                        <div class="flex items-center gap-2 pt-1">
                            <input type="checkbox" x-model="userForm.is_active" id="userActiveToggle" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="userActiveToggle" class="text-xs font-semibold text-gray-700 cursor-pointer">Usuário ativo</label>
                        </div>
                    </div>
                </div>
                    <div class="flex justify-end gap-2 p-4 pt-3 border-t border-gray-200 shrink-0 bg-gray-50">
                        <button type="button" x-show="userFormMode === 'edit'" x-cloak @click="resetUserForm()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition">Cancelar</button>
                        <button type="button" @click="saveUser()" :disabled="usersLoading" :class="usersLoading ? 'opacity-50 cursor-not-allowed' : ''" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition">
                            <span x-text="userFormMode === 'edit' ? 'Salvar Alterações' : '+ Adicionar Usuário'"></span>
                        </button>
                    </div>
                </div>

                <!-- LISTA: DIREITA -->
                <div class="flex-1 min-w-0 border border-gray-200 rounded-lg overflow-y-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead class="sticky top-0">
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider">
                                <th class="py-2.5 px-4 font-semibold">Nome <span class="font-bold text-gray-700" x-text="'(' + usersList.length + ')'"></span></th>
                                <th class="py-2.5 px-4 font-semibold">Perfil</th>
                                <th class="py-2.5 px-4 font-semibold">Status</th>
                                <th class="py-2.5 px-4 font-semibold text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="u in usersList" :key="u.id">
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="py-2.5 px-4">
                                        <p class="font-bold text-gray-800" x-text="u.name"></p>
                                        <p class="text-[11px] text-gray-500 font-mono" x-text="u.email"></p>
                                        <p class="text-[11px] text-gray-400 mt-0.5" x-show="u.department_names" x-text="u.department_names"></p>
                                    </td>
                                    <td class="py-2.5 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold"
                                            :class="{
                                                'bg-indigo-100 text-indigo-700': u.role === 'admin',
                                                'bg-emerald-100 text-emerald-700': u.role === 'gestor',
                                                'bg-amber-100 text-amber-700': u.role === 'agente'
                                            }"
                                            x-text="u.role === 'admin' ? 'Admin' : (u.role === 'gestor' ? 'Gestor' : 'Agente')"></span>
                                    </td>
                                    <td class="py-2.5 px-4">
                                        <button type="button" @click="toggleActive(u)" class="px-2 py-0.5 rounded-full text-[11px] font-bold transition"
                                            :class="u.is_active ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-gray-200 text-gray-500 hover:bg-gray-300'"
                                            x-text="u.is_active ? 'Ativo' : 'Inativo'"></button>
                                    </td>
                                    <td class="py-2.5 px-4 text-right">
                                        <div class="flex justify-end items-center gap-2">
                                            <button type="button" @click="editUser(u)" class="text-indigo-600 hover:bg-indigo-50 px-2.5 py-1.5 rounded-md text-xs font-bold transition">Editar</button>
                                            <button type="button" @click="deleteUser(u)" class="text-red-600 hover:bg-red-50 px-2.5 py-1.5 rounded-md text-xs font-bold transition">Excluir</button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="!usersLoading && usersList.length === 0">
                                <td colspan="4" class="text-center py-8 text-gray-400">Nenhum usuário cadastrado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL AMBIENTE -->
    <div x-show="envModalOpen" x-cloak x-transition @keydown.escape.window="envModalOpen = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
        <div @click.away="envModalOpen = false" class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-lg w-full p-6 relative border border-slate-200 dark:border-gray-700 max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between mb-5 shrink-0">
                <div>
                    <h3 class="text-base font-bold text-gray-800 dark:text-gray-100">Ambiente</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Identidade visual e informações do sistema.</p>
                </div>
                <button type="button" @click="envModalOpen = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div x-show="envError" x-cloak class="bg-red-50 border border-red-200 text-red-700 text-xs px-3 py-2 rounded-lg mb-4 shrink-0" x-text="envError"></div>

            <div class="overflow-y-auto flex-1 min-h-0 space-y-4 pr-1 custom-scroll">
                <div class="flex items-center gap-4">
                    <template x-if="envLogoLightPreview || envLogoLightUrl">
                        <img :src="envLogoLightPreview || envLogoLightUrl" alt="Logotipo (tema claro)" class="w-14 h-14 rounded-lg object-contain border border-gray-200 dark:border-gray-600 bg-white p-1">
                    </template>
                    <template x-if="!envLogoLightPreview && !envLogoLightUrl">
                        <span class="w-14 h-14 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400 text-[10px] text-center">Sem logo</span>
                    </template>
                    <div>
                        <label class="inline-block text-xs font-semibold text-indigo-600 hover:text-indigo-700 cursor-pointer">
                            Logotipo (tema claro)
                            <input type="file" accept="image/*" class="hidden" @change="onEnvLogoLightFile">
                        </label>
                        <p class="text-[11px] text-gray-400 mt-0.5">Usado no menu e no login quando o tema é claro.</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <template x-if="envLogoDarkPreview || envLogoDarkUrl">
                        <img :src="envLogoDarkPreview || envLogoDarkUrl" alt="Logotipo (tema escuro)" class="w-14 h-14 rounded-lg object-contain border border-gray-200 dark:border-gray-600 bg-gray-800 p-1">
                    </template>
                    <template x-if="!envLogoDarkPreview && !envLogoDarkUrl">
                        <span class="w-14 h-14 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400 text-[10px] text-center">Sem logo</span>
                    </template>
                    <div>
                        <label class="inline-block text-xs font-semibold text-indigo-600 hover:text-indigo-700 cursor-pointer">
                            Logotipo (tema escuro)
                            <input type="file" accept="image/*" class="hidden" @change="onEnvLogoDarkFile">
                        </label>
                        <p class="text-[11px] text-gray-400 mt-0.5">Usado no menu e no login quando o tema é escuro.</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <template x-if="envLoginBgPreview || envLoginBgUrl">
                        <img :src="envLoginBgPreview || envLoginBgUrl" alt="Fundo do login" class="w-24 h-14 rounded-lg object-cover border border-gray-200 dark:border-gray-600">
                    </template>
                    <template x-if="!envLoginBgPreview && !envLoginBgUrl">
                        <span class="w-24 h-14 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400 text-[10px] text-center">Sem imagem</span>
                    </template>
                    <div>
                        <label class="inline-block text-xs font-semibold text-indigo-600 hover:text-indigo-700 cursor-pointer">
                            Alterar fundo do login
                            <input type="file" accept="image/*" class="hidden" @change="onEnvLoginBgFile">
                        </label>
                        <p class="text-[11px] text-gray-400 mt-0.5">Imagem de fundo da tela de login.</p>
                    </div>
                </div>

                <p class="text-[11px] text-gray-400 -mt-2">O tema da tela de login acompanha automaticamente a última escolha de claro/escuro feita por alguém no sistema.</p>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Nome do sistema</label>
                        <input type="text" x-model="envForm.footer_name" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Versão</label>
                        <input type="text" x-model="envForm.footer_version" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Empresa (copyright)</label>
                        <input type="text" x-model="envForm.footer_company" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 uppercase mb-1">Ano (copyright)</label>
                        <input type="text" maxlength="4" x-model="envForm.footer_year" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-[11px] text-gray-500 dark:text-gray-400 text-center">
                    Pré-visualização: <span x-text="envForm.footer_name"></span> <span x-text="envForm.footer_version"></span> &middot; &copy; <span x-text="envForm.footer_year"></span> <span x-text="envForm.footer_company"></span>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-4 pt-3 border-t border-gray-200 dark:border-gray-700 shrink-0">
                <button type="button" @click="envModalOpen = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-bold rounded-lg transition">Cancelar</button>
                <button type="button" @click="saveEnvironment()" :disabled="envLoading" :class="envLoading ? 'opacity-50 cursor-not-allowed' : ''" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition">
                    <span x-text="envLoading ? 'Salvando...' : 'Salvar'"></span>
                </button>
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
