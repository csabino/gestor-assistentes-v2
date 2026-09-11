<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações Avançadas - {{ $assistant->name }}</title>
    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%234F46E5' stroke-width='2'><path stroke-linecap='round' stroke-linejoin='round' d='M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.27 1.06-.12 1.451l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.27-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.149-.894z'/><path stroke-linecap='round' stroke-linejoin='round' d='M15 12a3 3 0 11-6 0 3 3 0 016 0z'/></svg>">

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-gray-50 font-sans text-gray-900 min-h-screen flex overflow-hidden" 
    x-data="{ 
        sidebarOpen: localStorage.getItem('sidebar_open') !== 'false'
    }"
>

    <aside :class="sidebarOpen ? 'w-64' : 'w-20'" class="bg-indigo-700 text-white min-h-screen transition-all duration-300 flex flex-col justify-between shrink-0 shadow-xl relative z-50">
        <div>
            <div class="h-16 flex items-center border-b border-indigo-600/80 transition-all px-3" :class="sidebarOpen ? 'justify-between' : 'justify-center'">
                <a href="/" class="font-bold text-lg flex items-center gap-3 truncate hover:text-indigo-200 transition" x-show="sidebarOpen">
                    <svg class="w-7 h-7 text-indigo-200 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z" />
                    </svg>
                    <span class="truncate font-bold tracking-wide">Painel IA</span>
                </a>
                <button type="button" @click="sidebarOpen = !sidebarOpen; localStorage.setItem('sidebar_open', sidebarOpen)" class="p-2 rounded-lg hover:bg-indigo-600 text-indigo-200 hover:text-white transition cursor-pointer flex items-center justify-center shrink-0" title="Alternar menu">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                </button>
            </div>

            <nav class="p-3 space-y-2 font-medium text-sm">
                <div class="relative group">
                    <a href="/" class="flex items-center rounded-xl transition font-semibold text-indigo-100 hover:bg-indigo-600" :class="sidebarOpen ? 'gap-3.5 px-4 py-3' : 'justify-center p-3'">
                        <svg class="w-5 h-5 shrink-0 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v1.242c0 .289.23.523.518.523h3.726c.288 0 .518-.234.518-.523V3.104M12 21v-3.75m9-4.5h-1.5M4.5 12.75H3m16.5 0a2.25 2.25 0 002.25-2.25V8.25a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 8.25v2.25a2.25 2.25 0 002.25 2.25h13.5z" /></svg>
                        <span x-show="sidebarOpen" class="truncate">Assistentes IA</span>
                    </a>
                    <div x-show="!sidebarOpen" class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-slate-900 text-white text-[11px] font-bold rounded-md shadow-xl opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-50">Assistentes IA</div>
                </div>

                <div class="relative group">
                    <a href="/?view=equipe" class="flex items-center rounded-xl transition font-semibold text-indigo-100 hover:bg-indigo-600" :class="sidebarOpen ? 'gap-3.5 px-4 py-3' : 'justify-center p-3'">
                        <svg class="w-5 h-5 shrink-0 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>
                        <span x-show="sidebarOpen" class="truncate">Equipe & Agendas</span>
                    </a>
                    <div x-show="!sidebarOpen" class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-slate-900 text-white text-[11px] font-bold rounded-md shadow-xl opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-50">Equipe & Agendas</div>
                </div>

                <div class="relative group">
                    <a href="/?view=agenda" class="flex items-center rounded-xl transition font-semibold text-indigo-100 hover:bg-indigo-600" :class="sidebarOpen ? 'gap-3.5 px-4 py-3' : 'justify-center p-3'">
                        <svg class="w-5 h-5 shrink-0 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008z" /></svg>
                        <span x-show="sidebarOpen" class="truncate">Calendário</span>
                    </a>
                    <div x-show="!sidebarOpen" class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-slate-900 text-white text-[11px] font-bold rounded-md shadow-xl opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-50">Calendário</div>
                </div>
            </nav>
        </div>

        <div class="p-4 border-t border-indigo-600/80 mt-auto space-y-2">
            <form action="/logout" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center rounded-lg text-indigo-100 hover:bg-indigo-600 transition text-sm font-semibold" :class="sidebarOpen ? 'gap-2.5 px-3 py-2 justify-start' : 'justify-center p-2'" title="Sair">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg>
                    <span x-show="sidebarOpen">Sair</span>
                </button>
            </form>
            <span x-show="sidebarOpen" class="text-[11px] bg-indigo-800 text-indigo-200 px-3 py-1.5 rounded-full font-bold border border-indigo-500 block text-center shadow-inner tracking-wider">Multiagents v6.0</span>
            <span x-show="!sidebarOpen" class="text-[10px] text-indigo-300 font-bold block text-center tracking-widest">v2.0</span>
        </div>
    </aside>

    <main id="mainContent" class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto pt-8">
        <form id="settingsForm" action="/?view=settings" method="POST" class="container mx-auto px-6 max-w-6xl pb-12">
            @csrf
            
            <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">

            <!-- CABEÇALHO FIXO -->
            <div class="sticky top-0 z-40 bg-gray-50/90 backdrop-blur-md py-4 mb-6 border-b border-gray-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-sm -mx-6 px-6">
                <div class="flex items-center gap-4">
                    <a href="/?configure={{ $assistant->id }}" class="text-indigo-600 hover:text-indigo-800 font-semibold flex items-center gap-1.5 text-sm transition bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg border border-indigo-100 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg> Voltar
                    </a>
                    <div class="h-6 w-px bg-gray-300 hidden md:block"></div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">Configurações Avançadas — {{ $assistant->name }}</h1>
                        <p class="text-xs text-gray-500 mt-0.5">Gerencie fuso horário, agendamento, integrações e anexos para este assistente.</p>
                    </div>
                </div>
                
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-lg text-xs transition shadow-sm flex items-center gap-2 shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    Salvar Configurações
                </button>
            </div>

            @if(session('success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2 shadow-sm">
                    <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
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

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- Card 1: Fuso Horário -->
                <div class="md:col-span-1 bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-between">
                    <div>
                        <h2 class="text-base font-bold text-gray-800 border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Fuso Horário
                        </h2>
                        
                        <div x-data="{
                            open: false,
                            search: '',
                            selected: '{{ $currentTz }}',
                            allTimezones: {{ json_encode($timezones) }},
                            get filteredTimezones() {
                                if (this.search.length < 3) return this.allTimezones;
                                return this.allTimezones.filter(tz => tz.toLowerCase().includes(this.search.toLowerCase()));
                            }
                        }" class="relative">
                            <input type="hidden" name="timezone" :value="selected">

                            <label class="block text-xs font-semibold text-gray-700 mb-1">Fuso Padrão</label>

                            <button type="button" @click="open = !open" class="w-full bg-white border border-gray-300 rounded-lg p-2.5 text-xs font-medium text-gray-800 flex justify-between items-center focus:ring-2 focus:ring-indigo-500 outline-none shadow-sm">
                                <span x-text="selected" class="truncate font-semibold text-indigo-600"></span>
                                <svg class="w-4 h-4 text-gray-400 shrink-0 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>

                            <div x-show="open" @click.away="open = false" x-cloak class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl z-50 p-2 max-h-60 overflow-hidden flex flex-col">
                                <input type="text" x-model="search" placeholder="Digite 3+ letras..." class="w-full border border-gray-300 rounded-md p-2 text-xs mb-2 outline-none focus:border-indigo-500">
                                <div class="overflow-y-auto max-h-40 custom-scroll">
                                    <template x-for="tz in filteredTimezones" :key="tz">
                                        <button type="button" @click="selected = tz; open = false; search = ''" class="w-full text-left px-2.5 py-1.5 text-xs text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 rounded transition font-medium truncate block" :class="selected === tz ? 'bg-indigo-50 text-indigo-600 font-bold' : ''" x-text="tz"></button>
                                    </template>
                                    <div x-show="filteredTimezones.length === 0" class="text-xs text-gray-400 p-2 text-center">Nenhum fuso encontrado.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-gray-400 mt-4 leading-relaxed border-t border-gray-50 pt-3">
                        Agendamentos, logs e mensagens utilizarão este fuso horário oficial.
                    </p>
                </div>

                <!-- Card 2: Webhook Multiagentes -->
                <div class="md:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-between">
                    <div>
                        <h2 class="text-base font-bold text-gray-800 border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
                            Webhook Multiagentes
                        </h2>
                        <div class="space-y-2">
                            <label for="omni_webhook_url" class="block text-xs font-semibold text-gray-700">URL / Path do Webhook (`webhook_multiagents.php`)</label>
                            <input type="text" name="omni_webhook_url" id="omni_webhook_url" value="{{ $webhookUrl }}" placeholder="https://seu-dominio.com/caminho/" class="w-full border border-gray-300 rounded-lg p-2.5 text-xs font-mono text-gray-800 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-4 leading-relaxed border-t border-gray-50 pt-3">
                        A barra final `/` será garantida automaticamente. Deixe em branco para desativar.
                    </p>
                </div>

                <!-- CARD NOVO: Regras & Diretrizes de Agendamento de Reuniões -->
                <div class="md:col-span-3 bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h2 class="text-base font-bold text-gray-800 border-b border-gray-100 pb-3 mb-5 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008z" />
                        </svg>
                        Regras & Diretrizes de Agendamento
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Ativar/Desativar Agendamento -->
                        <div class="md:col-span-1">
                            <label for="scheduling_enabled" class="block text-xs font-semibold text-gray-700 mb-1">Módulo de Agendamento</label>
                            <select name="scheduling_enabled" id="scheduling_enabled" class="w-full border border-gray-300 rounded-lg p-2.5 text-xs font-medium text-gray-800 focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                                <option value="1" {{ ($schedulingEnabled ?? '1') == '1' ? 'selected' : '' }}>Habilitado</option>
                                <option value="0" {{ ($schedulingEnabled ?? '1') == '0' ? 'selected' : '' }}>Desabilitado</option>
                            </select>
                            <p class="text-[11px] text-gray-400 mt-1">Define se o assistente deve propor reuniões ativamente.</p>
                        </div>

                        <!-- Setor Padrão / Prioritário -->
                        <div class="md:col-span-2">
                            <label for="default_department_id" class="block text-xs font-semibold text-gray-700 mb-1">Setor Padrão / Prioritário para Agendamentos</label>
                            <select name="default_department_id" id="default_department_id" class="w-full border border-gray-300 rounded-lg p-2.5 text-xs font-medium text-gray-800 focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                                <option value="">Nenhum (Deixar a IA perguntar sem assumir padrão)</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ ($defaultDepartmentId ?? '') == $dept->id ? 'selected' : '' }}>
                                        {{ $dept->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-gray-400 mt-1">O setor selecionado será assumido como padrão pela IA durante o fluxo de agendamento.</p>
                        </div>

                        <!-- Prompt/Diretriz Customizada do Agendamento -->
                        <div class="md:col-span-3">
                            <label for="scheduling_custom_prompt" class="block text-xs font-semibold text-gray-700 mb-1">Instruções Customizadas para Agendamento (Opcional)</label>
                            <textarea 
                                name="scheduling_custom_prompt" 
                                id="scheduling_custom_prompt" 
                                x-data="{ 
                                    resize() { 
                                        $el.style.height = 'auto'; 
                                        $el.style.height = Math.max($el.scrollHeight, 90) + 'px'; 
                                    } 
                                }"
                                x-init="$nextTick(() => resize())"
                                @input="resize()"
                                rows="3" 
                                placeholder="Ex: Sempre reforce que nossas reuniões duram 30 minutos. Se o cliente for do setor de TI, informe que a equipe de Produtos também pode participar." 
                                class="w-full border border-gray-300 rounded-lg p-2.5 text-xs font-sans text-gray-800 focus:ring-2 focus:ring-indigo-500 outline-none resize-y transition-all"
                            >{{ $schedulingCustomPrompt ?? '' }}</textarea>
                            <p class="text-[11px] text-gray-400 mt-1">Orientações específicas que serão injetadas dinamicamente no prompt do assistente para conduzir os agendamentos.</p>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Integração Google Calendar & Meet -->
                <div class="md:col-span-3 bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-gray-100 pb-3 mb-5 gap-3">
                        <h2 class="text-base font-bold text-gray-800 flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008z" />
                            </svg>
                            Integração Google Calendar & Meet
                        </h2>

                        <a href="/?view=settings&action=google_redirect&assistant_id={{ $assistant->id }}" class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-700 font-bold py-2 px-4 rounded-lg text-xs transition shadow-sm flex items-center justify-center gap-2 shrink-0">
                            <svg class="w-4 h-4" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                            </svg>
                            @if(!empty($googleRefreshToken))
                                <span class="text-emerald-600 font-bold flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    Conta Conectada (Reconectar)
                                </span>
                            @else
                                <span>Conectar Conta do Google</span>
                            @endif
                        </a>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="google_client_id" class="block text-xs font-semibold text-gray-700 mb-1">Google Client ID</label>
                            <input type="text" name="google_client_id" id="google_client_id" value="{{ $googleClientId ?? '' }}" placeholder="SEU_CLIENT_ID.apps.googleusercontent.com" class="w-full border border-gray-300 rounded-lg p-2.5 text-xs font-mono text-gray-800 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>

                        <div>
                            <label for="google_client_secret" class="block text-xs font-semibold text-gray-700 mb-1">Google Client Secret</label>
                            <input type="password" name="google_client_secret" id="google_client_secret" value="{{ $googleClientSecret ?? '' }}" placeholder="GOCSPX-xxxxxxxxxxxxxxxx" class="w-full border border-gray-300 rounded-lg p-2.5 text-xs font-mono text-gray-800 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>

                        <div>
                            <label for="google_calendar_id" class="block text-xs font-semibold text-gray-700 mb-1">ID do Calendário (padrão: primary)</label>
                            <input type="text" name="google_calendar_id" id="google_calendar_id" value="{{ $googleCalendarId ?? 'primary' }}" placeholder="primary ou seu-email@empresa.com" class="w-full border border-gray-300 rounded-lg p-2.5 text-xs font-mono text-gray-800 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>

                        <div>
                            <label for="google_refresh_token" class="block text-xs font-semibold text-gray-700 mb-1">Google Refresh Token</label>
                            <input type="password" name="google_refresh_token" id="google_refresh_token" value="{{ $googleRefreshToken ?? '' }}" placeholder="Obtido automaticamente ao Conectar Conta" class="w-full border border-gray-300 rounded-lg p-2.5 text-xs font-mono text-gray-800 focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                    </div>

                    <p class="text-xs text-gray-400 mt-4 leading-relaxed border-t border-gray-50 pt-3">
                        O assistente utilizará esta conta organizadora do Google para gerar automaticamente as reuniões no Google Meet e enviar os convites aos clientes e agentes humanos alocados.
                    </p>
                </div>

                <!-- Card 4: Controle de Anexos -->
                <div class="md:col-span-3 bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h2 class="text-base font-bold text-gray-800 border-b border-gray-100 pb-3 mb-5 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" /></svg>
                        Controle de Anexos
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="md:col-span-1 border-r border-gray-100 pr-4">
                            <label class="block text-xs font-semibold text-gray-700 mb-2">Tamanho Máximo por Anexo</label>
                            <select name="max_file_size_mb" class="w-full border border-gray-300 rounded-lg p-2.5 text-xs font-medium text-gray-800 focus:ring-2 focus:ring-indigo-500 outline-none shadow-sm cursor-pointer bg-white">
                                <option value="1" {{ ($maxFileSize ?? '4') == '1' ? 'selected' : '' }}>1 MB</option>
                                <option value="2" {{ ($maxFileSize ?? '4') == '2' ? 'selected' : '' }}>2 MB</option>
                                <option value="4" {{ ($maxFileSize ?? '4') == '4' ? 'selected' : '' }}>4 MB</option>
                                <option value="6" {{ ($maxFileSize ?? '4') == '6' ? 'selected' : '' }}>6 MB</option>
                                <option value="8" {{ ($maxFileSize ?? '4') == '8' ? 'selected' : '' }}>8 MB</option>
                            </select>
                            <p class="text-[10px] text-gray-400 mt-2 leading-relaxed">
                                Arquivos maiores que o limite selecionado serão recusados automaticamente.
                            </p>
                        </div>

                        <div class="md:col-span-3">
                            <label class="block text-xs font-semibold text-gray-700 mb-3">Extensões Permitidas (Recebimento / Envio)</label>
                            
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                                    <h4 class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2 border-b border-gray-200 pb-1">Imagens</h4>
                                    <div class="space-y-1.5">
                                        @foreach(['jpg', 'jpeg', 'png', 'webp'] as $ext)
                                        <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer hover:text-indigo-600 transition">
                                            <input type="checkbox" name="allowed_extensions[]" value="{{ $ext }}" {{ in_array($ext, $allowedExtensions ?? []) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-3.5 h-3.5 cursor-pointer">
                                            <span class="font-medium uppercase">{{ $ext }}</span>
                                        </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                                    <h4 class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2 border-b border-gray-200 pb-1">Documentos</h4>
                                    <div class="space-y-1.5">
                                        @foreach(['pdf', 'doc', 'docx', 'txt'] as $ext)
                                        <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer hover:text-indigo-600 transition">
                                            <input type="checkbox" name="allowed_extensions[]" value="{{ $ext }}" {{ in_array($ext, $allowedExtensions ?? []) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-3.5 h-3.5 cursor-pointer">
                                            <span class="font-medium uppercase">{{ $ext }}</span>
                                        </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                                    <h4 class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2 border-b border-gray-200 pb-1">Planilhas</h4>
                                    <div class="space-y-1.5">
                                        @foreach(['xls', 'xlsx', 'csv'] as $ext)
                                        <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer hover:text-indigo-600 transition">
                                            <input type="checkbox" name="allowed_extensions[]" value="{{ $ext }}" {{ in_array($ext, $allowedExtensions ?? []) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-3.5 h-3.5 cursor-pointer">
                                            <span class="font-medium uppercase">{{ $ext }}</span>
                                        </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                                    <h4 class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2 border-b border-gray-200 pb-1">Áudio / Vídeo</h4>
                                    <div class="space-y-1.5">
                                        @foreach(['mp3', 'ogg', 'wav', 'mp4'] as $ext)
                                        <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer hover:text-indigo-600 transition">
                                            <input type="checkbox" name="allowed_extensions[]" value="{{ $ext }}" {{ in_array($ext, $allowedExtensions ?? []) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-3.5 h-3.5 cursor-pointer">
                                            <span class="font-medium uppercase">{{ $ext }}</span>
                                        </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </main>
</body>
</html>