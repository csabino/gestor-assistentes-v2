<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Equipe & Agentes</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%234F46E5' stroke-width='2'><path stroke-linecap='round' stroke-linejoin='round' d='M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z'/></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-50 font-sans text-gray-900 min-h-screen flex overflow-hidden" 
    x-data="{ 
        sidebarOpen: localStorage.getItem('sidebar_open') !== 'false',
        editDeptModal: false,
        editDeptData: { id: null, name: '' },
        editAgentModal: false,
        editAgentData: { id: null, department_id: null, name: '', email: '' }
    }"
>

    <aside class="bg-indigo-700 text-white min-h-screen transition-all duration-300 flex flex-col justify-between shrink-0 shadow-xl relative z-50" :class="sidebarOpen ? 'w-64' : 'w-20'">
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
                    <div x-show="!sidebarOpen" class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-slate-900 text-white text-[11px] font-bold rounded-md shadow-xl opacity-0 group-hover:opacity-100 pointer-events-none whitespace-nowrap z-50">Assistentes IA</div>
                </div>

                <div class="relative group">
                    <a href="/?view=equipe" class="flex items-center rounded-xl transition font-semibold bg-indigo-900/90 text-white shadow-sm border border-indigo-500/30" :class="sidebarOpen ? 'gap-3.5 px-4 py-3' : 'justify-center p-3'">
                        <svg class="w-5 h-5 shrink-0 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>
                        <span x-show="sidebarOpen" class="truncate">Equipe & Agendas</span>
                    </a>
                    <div x-show="!sidebarOpen" class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-slate-900 text-white text-[11px] font-bold rounded-md shadow-xl opacity-0 group-hover:opacity-100 pointer-events-none whitespace-nowrap z-50">Equipe & Agendas</div>
                </div>

                <div class="relative group">
                    <a href="/?view=agenda" class="flex items-center rounded-xl transition font-semibold text-indigo-100 hover:bg-indigo-600" :class="sidebarOpen ? 'gap-3.5 px-4 py-3' : 'justify-center p-3'">
                        <svg class="w-5 h-5 shrink-0 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008z" /></svg>
                        <span x-show="sidebarOpen" class="truncate">Calendário</span>
                    </a>
                    <div x-show="!sidebarOpen" class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-slate-900 text-white text-[11px] font-bold rounded-md shadow-xl opacity-0 group-hover:opacity-100 pointer-events-none whitespace-nowrap z-50">Calendário</div>
                </div>

                <div class="relative group">
                    <a href="/?view=settings" class="flex items-center rounded-xl transition font-semibold text-indigo-100 hover:bg-indigo-600" :class="sidebarOpen ? 'gap-3.5 px-4 py-3' : 'justify-center p-3'">
                        <svg class="w-5 h-5 shrink-0 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.27 1.06-.12 1.451l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.27-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.149-.894z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span x-show="sidebarOpen" class="truncate">Settings</span>
                    </a>
                    <div x-show="!sidebarOpen" class="absolute left-full top-1/2 -translate-y-1/2 ml-3 px-3 py-1.5 bg-slate-900 text-white text-[11px] font-bold rounded-md shadow-xl opacity-0 group-hover:opacity-100 pointer-events-none whitespace-nowrap z-50">Settings</div>
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

    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <div class="container mx-auto px-6 max-w-7xl py-8">
            
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

            <div class="space-y-6">
                <!-- CABEÇALHO COM O FILTRO GLOBAL (STATUS E ASSISTENTE) -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6 border-b border-gray-100 pb-4">
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">Gestão de Departamentos & Agentes</h1>
                            <p class="text-xs text-gray-500 mt-1">Organize os setores corporativos e os agentes humanos de suporte.</p>
                        </div>
                        
                        <!-- FILTRO GLOBAL DE ASSISTENTE + STATUS -->
                        <form id="assistantFilter" method="GET" action="/" class="flex flex-wrap items-center gap-3 bg-indigo-50 px-4 py-2 rounded-xl border border-indigo-100 shadow-sm">
                            <input type="hidden" name="view" value="equipe">
                            
                            <!-- COMBO: STATUS -->
                            <div class="flex items-center gap-2 border-r border-indigo-200 pr-3">
                                <span class="font-bold text-indigo-700 uppercase text-[10px] tracking-wide">Status:</span>
                                <select name="status" onchange="document.getElementById('assistantFilter').submit()" class="font-bold text-slate-800 bg-transparent focus:outline-none cursor-pointer text-sm">
                                    <option value="ativo" {{ $statusFilter == 'ativo' ? 'selected' : '' }}>Ativos</option>
                                    <option value="inativo" {{ $statusFilter == 'inativo' ? 'selected' : '' }}>Inativos</option>
                                    <option value="todos" {{ $statusFilter == 'todos' ? 'selected' : '' }}>Todos</option>
                                </select>
                            </div>

                            <!-- COMBO: ASSISTENTE -->
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-indigo-700 uppercase text-[10px] tracking-wide">Assistente IA:</span>
                                <select name="assistant_id" onchange="document.getElementById('assistantFilter').submit()" class="font-bold text-slate-800 bg-transparent focus:outline-none cursor-pointer text-sm">
                                    @foreach($assistants as $ast)
                                        <option value="{{ $ast->id }}" {{ $selectedAssistantId == $ast->id ? 'selected' : '' }}>{{ $ast->name }}</option>
                                    @endforeach
                                    @if($assistants->isEmpty())
                                        <option value="">Nenhum assistente encontrado</option>
                                    @endif
                                </select>
                            </div>
                        </form>
                    </div>

                    @if(!$selectedAssistantId)
                        <div class="text-center py-10 text-gray-500 font-medium">Nenhum Assistente IA disponível neste filtro. Crie um assistente ou altere o status.</div>
                    @else
                        <!-- CRIAR DEPARTAMENTO -->
                        <form action="/?view=equipe" method="POST" class="mb-8 bg-gray-50 p-4 rounded-xl border border-gray-200">
                            @csrf
                            <input type="hidden" name="action" value="store_department">
                            <input type="hidden" name="assistant_id" value="{{ $selectedAssistantId }}">
                            <input type="hidden" name="status" value="{{ $statusFilter }}">
                            <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wide mb-2">Novo Departamento</label>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <!-- INPUT ONINPUT FORÇANDO MAIÚSCULA NA VIEW -->
                                <input type="text" name="name" required placeholder="Ex: COMERCIAL, TECNOLOGIA..." oninput="this.value = this.value.toUpperCase()" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none uppercase">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-2.5 rounded-lg text-xs transition shadow-sm whitespace-nowrap">+ Adicionar Departamento</button>
                            </div>
                        </form>

                        <!-- LISTA DE CARDS DE DEPARTAMENTOS -->
                        <div class="space-y-6">
                            @forelse($departments as $dept)
                                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col md:flex-row transition hover:border-indigo-200">
                                    
                                    <!-- ESQUERDA: DEPARTAMENTO E CONTROLES (AGORA COM ÍCONES) -->
                                    <div class="md:w-1/4 bg-gray-50/80 p-5 border-b md:border-b-0 md:border-r border-gray-200 flex flex-col justify-between shrink-0">
                                        <div>
                                            <h3 class="font-bold text-gray-800 text-base flex items-center gap-2 uppercase tracking-wide">
                                                <span class="w-2.5 h-2.5 bg-indigo-500 rounded-full"></span>
                                                {{ $dept->name }}
                                            </h3>
                                        </div>
                                        <div class="flex items-center gap-2 mt-5">
                                            <!-- BOTÃO EDITAR DEPARTAMENTO COM ÍCONE -->
                                            <button @click="editDeptData = { id: {{ $dept->id }}, name: '{{ $dept->name }}' }; editDeptModal = true" class="flex-1 flex items-center justify-center gap-1.5 text-[10px] font-bold text-indigo-700 bg-indigo-100 hover:bg-indigo-200 px-2.5 py-2 rounded transition">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                                                Editar
                                            </button>
                                            <!-- BOTÃO EXCLUIR DEPARTAMENTO COM ÍCONE -->
                                            <form action="/?view=equipe" method="POST" class="flex-1" onsubmit="return confirm('Excluir departamento e TODOS os agentes vinculados?');">
                                                @csrf 
                                                <input type="hidden" name="action" value="delete_department">
                                                <input type="hidden" name="department_id" value="{{ $dept->id }}">
                                                <input type="hidden" name="assistant_id" value="{{ $selectedAssistantId }}">
                                                <input type="hidden" name="status" value="{{ $statusFilter }}">
                                                <button type="submit" class="w-full flex items-center justify-center gap-1.5 text-[10px] font-bold text-red-600 bg-red-100 hover:bg-red-200 px-2.5 py-2 rounded transition">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                                    Excluir
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- DIREITA: CARDS DOS AGENTES -->
                                    <div class="md:w-3/4 p-5 flex flex-wrap gap-4 items-start bg-slate-50/30">
                                        
                                        <!-- CARD 1: ADICIONAR NOVO AGENTE (Sempre na esquerda) -->
                                        <div class="bg-white border border-slate-200 border-dashed hover:border-indigo-400 rounded-lg p-3 w-auto min-w-[240px] shadow-sm relative transition group">
                                            <form action="/?view=equipe" method="POST" class="flex flex-col gap-2">
                                                @csrf
                                                <input type="hidden" name="action" value="store_agent">
                                                <input type="hidden" name="department_id" value="{{ $dept->id }}">
                                                <input type="hidden" name="assistant_id" value="{{ $selectedAssistantId }}">
                                                <input type="hidden" name="status" value="{{ $statusFilter }}">
                                                <input type="text" name="name" required placeholder="Nome do Agente" class="w-full border border-slate-300 rounded text-[11px] px-2.5 py-1.5 outline-none focus:border-indigo-500 font-medium text-gray-800">
                                                <input type="email" name="email" required placeholder="E-mail corporativo" class="w-full border border-slate-300 rounded text-[11px] px-2.5 py-1.5 outline-none focus:border-indigo-500 font-mono text-gray-600">
                                                <button type="submit" class="w-full bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold py-1.5 rounded border border-indigo-200 text-[10px] transition uppercase tracking-wide mt-1">+ Salvar</button>
                                            </form>
                                        </div>

                                        <!-- CARDS 2...N: AGENTES EXISTENTES -->
                                        @foreach($agents as $agent)
                                            @if($agent->department_id == $dept->id)
                                                <div class="bg-white border border-gray-200 rounded-lg p-3 w-auto max-w-sm shadow-sm relative group hover:border-indigo-300 transition flex flex-col justify-between">
                                                    
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 font-bold text-sm flex items-center justify-center shrink-0 border border-indigo-200 uppercase">
                                                            {{ substr($agent->name, 0, 1) }}
                                                        </div>
                                                        <div class="pr-2">
                                                            <h4 class="font-bold text-gray-800 text-sm whitespace-nowrap">{{ $agent->name }}</h4>
                                                            <p class="text-[11px] text-gray-500 font-mono whitespace-nowrap mt-0.5">{{ $agent->email }}</p>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- FOOTER DO CARD: Controles aparecem no hover -->
                                                    <div class="mt-3 pt-2 border-t border-gray-50 flex justify-end gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                                        <button @click="editAgentData = { id: {{ $agent->id }}, department_id: {{ $dept->id }}, name: '{{ $agent->name }}', email: '{{ $agent->email }}' }; editAgentModal = true" class="text-indigo-600 hover:bg-indigo-50 px-2 py-1 rounded text-[10px] font-bold flex items-center gap-1 transition">
                                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg> Editar
                                                        </button>
                                                        <form action="/?view=equipe" method="POST" onsubmit="return confirm('Remover este agente?');">
                                                            @csrf 
                                                            <input type="hidden" name="action" value="delete_agent">
                                                            <input type="hidden" name="agent_id" value="{{ $agent->id }}">
                                                            <input type="hidden" name="assistant_id" value="{{ $selectedAssistantId }}">
                                                            <input type="hidden" name="status" value="{{ $statusFilter }}">
                                                            <button type="submit" class="text-red-600 hover:bg-red-50 px-2 py-1 rounded text-[10px] font-bold flex items-center gap-1 transition">
                                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg> Excluir
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-12 text-gray-400 font-medium">Nenhum departamento cadastrado para este Assistente.</div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>

            <!-- MODAL EDITAR DEPARTAMENTO -->
            <div x-show="editDeptModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4" x-transition>
                <div @click.away="editDeptModal = false" class="bg-white rounded-xl shadow-2xl max-w-sm w-full p-6 relative border border-slate-200">
                    <h3 class="text-base font-bold text-gray-800 mb-4">Editar Departamento</h3>
                    <form action="/?view=equipe" method="POST">
                        @csrf
                        <input type="hidden" name="action" value="update_department">
                        <input type="hidden" name="assistant_id" value="{{ $selectedAssistantId }}">
                        <input type="hidden" name="status" value="{{ $statusFilter }}">
                        <input type="hidden" name="department_id" :value="editDeptData.id">
                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Nome do Departamento</label>
                        <input type="text" name="name" x-model="editDeptData.name" oninput="this.value = this.value.toUpperCase()" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-xs mb-5 outline-none focus:border-indigo-500 uppercase">
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="editDeptModal = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition">Cancelar</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition">Atualizar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL EDITAR AGENTE -->
            <div x-show="editAgentModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4" x-transition>
                <div @click.away="editAgentModal = false" class="bg-white rounded-xl shadow-2xl max-w-sm w-full p-6 relative border border-slate-200">
                    <h3 class="text-base font-bold text-gray-800 mb-4">Editar Agente</h3>
                    <form action="/?view=equipe" method="POST">
                        @csrf
                        <input type="hidden" name="action" value="update_agent">
                        <input type="hidden" name="assistant_id" value="{{ $selectedAssistantId }}">
                        <input type="hidden" name="status" value="{{ $statusFilter }}">
                        <input type="hidden" name="agent_id" :value="editAgentData.id">
                        <input type="hidden" name="department_id" :value="editAgentData.department_id">
                        
                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Nome</label>
                        <input type="text" name="name" x-model="editAgentData.name" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-xs mb-3 outline-none focus:border-indigo-500">

                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">E-mail</label>
                        <input type="email" name="email" x-model="editAgentData.email" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-xs mb-5 outline-none focus:border-indigo-500">

                        <div class="flex justify-end gap-2">
                            <button type="button" @click="editAgentModal = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition">Cancelar</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>
</body>
</html>