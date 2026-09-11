<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendário e Horários</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%234F46E5' stroke-width='2'><path stroke-linecap='round' stroke-linejoin='round' d='M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008z'/></svg>">
    
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales-all.global.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        .fc-theme-standard td, .fc-theme-standard th { border-color: #e2e8f0; }
        .fc-col-header-cell { background-color: #f8fafc; padding: 0.5rem 0; font-weight: 700; color: #334155; text-transform: uppercase; font-size: 0.75rem; }
        .fc-timegrid-slot-label { font-size: 0.75rem; color: #94a3b8; font-weight: 600; }
        .fc .fc-timegrid-slot-minor { border-top-style: dashed; }
        .fc-event { cursor: pointer; border: none !important; }
        .fc .fc-day-today { background-color: #eef2ff !important; }
        .fc-theme-standard .fc-timegrid-now-indicator-line { border-color: #ef4444; border-width: 2px; }
        .fc-theme-standard .fc-timegrid-now-indicator-arrow { border-color: #ef4444; border-width: 6px; margin-top: -6px; }

        /* ESTILIZAÇÃO FORÇADA DE CONTRASTE E DESDOBRAMENTO DOS CARDS */
        .fc-daygrid-event, .fc-timegrid-event {
            background-color: #4f46e5 !important;
            border-color: #4338ca !important;
            color: #ffffff !important;
            border-radius: 6px !important;
            opacity: 1 !important;
        }

        .fc-daygrid-event-hmain,
        .fc-daygrid-event {
            padding: 3px 6px !important;
            margin-top: 2px !important;
            margin-bottom: 2px !important;
        }

        .fc-timegrid-event .fc-event-main {
            padding: 4px 6px !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: flex-start !important;
            overflow: hidden !important;
        }

        .fc-event * {
            color: #ffffff !important;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans text-gray-900 min-h-screen flex overflow-hidden" 
    x-data="{ 
        sidebarOpen: localStorage.getItem('sidebar_open') !== 'false',
        showModal: false,
        modalData: {}
    }"
>
    <!-- SIDEBAR LATERAL GLOBAL -->
    <aside class="bg-indigo-700 text-white min-h-screen transition-all duration-300 flex flex-col justify-between shrink-0 shadow-xl relative z-40" :class="sidebarOpen ? 'w-64' : 'w-20'">
        <div>
            <div class="h-16 flex items-center border-b border-indigo-600/80 transition-all px-3" :class="sidebarOpen ? 'justify-between' : 'justify-center'">
                <a href="/" class="font-bold text-lg flex items-center gap-3 truncate hover:text-indigo-200 transition" x-show="sidebarOpen">
                    <svg class="w-7 h-7 text-indigo-200 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z" />
                    </svg>
                    <span class="truncate font-bold tracking-wide">Painel IA</span>
                </a>

                <button type="button" @click="sidebarOpen = !sidebarOpen; localStorage.setItem('sidebar_open', sidebarOpen)" class="p-2 rounded-lg hover:bg-indigo-600 text-indigo-200 hover:text-white transition cursor-pointer flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                </button>
            </div>

            <nav class="p-3 space-y-2 font-medium text-sm">
                <div class="relative group">
                    <a href="/" class="flex items-center rounded-xl transition font-semibold text-indigo-100 hover:bg-indigo-600" :class="sidebarOpen ? 'gap-3.5 px-4 py-3' : 'justify-center p-3'">
                        <svg class="w-5 h-5 shrink-0 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v1.242c0 .289.23.523.518.523h3.726c.288 0 .518-.234.518-.523V3.104M12 21v-3.75m9-4.5h-1.5M4.5 12.75H3m16.5 0a2.25 2.25 0 002.25-2.25V8.25a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 8.25v2.25a2.25 2.25 0 002.25 2.25h13.5z" /></svg>
                        <span x-show="sidebarOpen" class="truncate">Assistentes IA</span>
                    </a>
                </div>

                <div class="relative group">
                    <a href="/?view=equipe" class="flex items-center rounded-xl transition font-semibold text-indigo-100 hover:bg-indigo-600" :class="sidebarOpen ? 'gap-3.5 px-4 py-3' : 'justify-center p-3'">
                        <svg class="w-5 h-5 shrink-0 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>
                        <span x-show="sidebarOpen" class="truncate">Equipe & Agendas</span>
                    </a>
                </div>

                <div class="relative group">
                    <a href="/?view=agenda" class="flex items-center rounded-xl transition font-semibold bg-indigo-900/90 text-white shadow-sm border border-indigo-500/30" :class="sidebarOpen ? 'gap-3.5 px-4 py-3' : 'justify-center p-3'">
                        <svg class="w-5 h-5 shrink-0 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008z" /></svg>
                        <span x-show="sidebarOpen" class="truncate">Calendário</span>
                    </a>
                </div>

                <div class="relative group">
                    <a href="/?view=settings" class="flex items-center rounded-xl transition font-semibold text-indigo-100 hover:bg-indigo-600" :class="sidebarOpen ? 'gap-3.5 px-4 py-3' : 'justify-center p-3'">
                        <svg class="w-5 h-5 shrink-0 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.27 1.06-.12 1.451l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.27-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.149-.894z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span x-show="sidebarOpen" class="truncate">Settings</span>
                    </a>
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

    <main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
        <div class="container mx-auto px-6 max-w-6xl py-6 flex flex-col h-full">
            
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4 shrink-0">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Calendário e Horários</h1>
                    <p class="text-xs text-slate-500 mt-1">Gerencie a disponibilidade e as reuniões agendadas de cada membro.</p>
                </div>

                <form id="calendarFilter" method="GET" action="/" class="flex flex-wrap items-center gap-3">
                    <input type="hidden" name="view" value="agenda">
                    
                    <div class="flex items-center gap-2 border-r border-slate-200 pr-3">
                        <span class="font-bold text-indigo-600 uppercase text-[10px] tracking-wide">Status:</span>
                        <select name="status" onchange="document.querySelector('[name=assistant_id]').value=''; document.querySelector('[name=agent_id]').value='all'; this.form.submit()" class="font-bold text-slate-700 bg-transparent focus:outline-none cursor-pointer text-xs">
                            <option value="ativo" {{ $statusFilter == 'ativo' ? 'selected' : '' }}>Ativos</option>
                            <option value="inativo" {{ $statusFilter == 'inativo' ? 'selected' : '' }}>Inativos</option>
                            <option value="todos" {{ $statusFilter == 'todos' ? 'selected' : '' }}>Todos</option>
                        </select>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-lg px-3 py-1.5 text-xs flex items-center gap-2 shadow-sm">
                        <span class="font-bold text-indigo-600 uppercase text-[10px]">Assistente IA:</span>
                        <select name="assistant_id" onchange="document.querySelector('[name=agent_id]').value='all'; this.form.submit()" class="font-bold text-slate-700 bg-transparent focus:outline-none cursor-pointer">
                            @forelse($assistants as $ast)
                                <option value="{{ $ast->id }}" {{ $currentAssistantId == $ast->id ? 'selected' : '' }}>{{ $ast->name }}</option>
                            @empty
                                <option value="">Nenhum assistente</option>
                            @endforelse
                        </select>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-lg px-3 py-1.5 text-xs flex items-center gap-2 shadow-sm">
                        <span class="font-bold text-indigo-600 uppercase text-[10px]">AGENTE:</span>
                        <select name="agent_id" onchange="this.form.submit()" class="font-bold text-slate-700 bg-transparent focus:outline-none cursor-pointer max-w-[200px] truncate">
                            <option value="all" {{ $currentAgentId === 'all' ? 'selected' : '' }}>Todos os Agentes</option>
                            @foreach($agents as $ag)
                                <option value="{{ $ag->id }}" {{ (string)$currentAgentId === (string)$ag->id ? 'selected' : '' }}>{{ $ag->name }} ({{ $ag->department_name }})</option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col flex-1 min-h-0">

                <div class="flex items-center justify-between border-b border-slate-100 pb-4 shrink-0 mb-4">
                    <div class="flex items-center gap-2">
                        <div class="inline-flex rounded-lg border border-indigo-200 shadow-sm">
                            <button id="btn-prev" class="bg-indigo-600 text-white px-3 py-1.5 rounded-l-lg hover:bg-indigo-700 transition">&lt;</button>
                            <button id="btn-next" class="bg-indigo-600 text-white px-3 py-1.5 rounded-r-lg hover:bg-indigo-700 transition">&gt;</button>
                        </div>
                        <button id="btn-today" class="bg-indigo-300 hover:bg-indigo-400 text-indigo-900 font-semibold px-4 py-1.5 rounded-lg text-xs transition shadow-sm">Hoje</button>
                    </div>

                    <h2 id="cal-title" class="text-xl font-bold text-slate-800 tracking-wide capitalize">Carregando...</h2>

                    <div class="inline-flex rounded-lg border border-indigo-200 shadow-sm text-xs font-semibold">
                        <button id="btn-month" class="bg-indigo-600 text-white px-3 py-1.5 rounded-l-lg transition">Mês</button>
                        <button id="btn-week" class="bg-indigo-600 text-white px-3 py-1.5 transition">Semana</button>
                        <button id="btn-day" class="bg-indigo-600 text-white px-3 py-1.5 rounded-r-lg transition">Dia</button>
                    </div>
                </div>

                <div class="flex-1 min-h-0 relative">
                    <div id="calendar" class="h-full"></div>
                </div>

            </div>
        </div>

        <!-- MODAL DE DETALHES DA REUNIÃO -->
        <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" x-cloak x-transition>
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 relative border border-slate-100" @click.away="showModal = false">
                
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <span x-text="modalData.type === 'block' ? '🚫 Bloqueio Manual' : '📅 Detalhes da Reunião'"></span>
                    </h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 font-bold p-1 rounded-lg hover:bg-slate-100">✕</button>
                </div>

                <template x-if="modalData.type === 'appointment'">
                    <div class="py-4 space-y-3.5 text-sm">
                        <div class="flex items-start gap-3 bg-indigo-50/60 p-3 rounded-xl border border-indigo-100">
                            <span class="text-lg">👤</span>
                            <div>
                                <p class="text-[11px] font-bold text-indigo-600 uppercase tracking-wider">Cliente</p>
                                <p class="font-bold text-slate-800" x-text="modalData.client_name"></p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">WhatsApp</p>
                                <p class="font-semibold text-slate-700 text-xs truncate" x-text="modalData.client_phone"></p>
                            </div>
                            <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">E-mail</p>
                                <p class="font-semibold text-slate-700 text-xs truncate" x-text="modalData.client_email"></p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Atendente</p>
                                <p class="font-semibold text-slate-700 text-xs truncate" x-text="modalData.agent_name"></p>
                            </div>
                            <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Setor</p>
                                <p class="font-semibold text-slate-700 text-xs truncate" x-text="modalData.department_name"></p>
                            </div>
                        </div>

                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Data e Horário</p>
                                <p class="font-bold text-slate-800 text-xs" x-text="modalData.start_formatted + ' às ' + modalData.end_formatted"></p>
                            </div>
                            <span class="px-2.5 py-1 text-[11px] font-bold rounded-full bg-emerald-100 text-emerald-700 border border-emerald-200" x-text="modalData.status"></span>
                        </div>
                    </div>
                </template>

                <template x-if="modalData.type === 'block'">
                    <div class="py-4 text-sm text-slate-600">
                        <p class="bg-red-50 p-3 rounded-xl border border-red-100 text-red-700 font-medium">Este horário está marcado como <strong>Indisponível</strong> na agenda do agente.</p>
                    </div>
                </template>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-3">
                    <button type="button" @click="deleteCurrentEvent(modalData.id)" class="px-3.5 py-2 text-xs font-bold text-red-600 hover:bg-red-50 rounded-xl transition border border-red-200">
                        Excluir Registro
                    </button>
                    <button type="button" @click="showModal = false" class="px-4 py-2 text-xs font-bold bg-slate-800 text-white hover:bg-slate-900 rounded-xl transition shadow-sm">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            const agentId = '{{ $currentAgentId }}';
            const assistantId = '{{ $currentAssistantId }}';
            const csrfToken = '{{ csrf_token() }}';

            const calendar = new FullCalendar.Calendar(calendarEl, {
                locale: 'pt-br',
                height: '100%',
                initialView: 'timeGridWeek',
                headerToolbar: false,
                allDaySlot: false,
                slotMinTime: '00:00:00',
                slotMaxTime: '24:00:00',
                editable: true,
                selectable: true,
                nowIndicator: true, 
                scrollTimeReset: false,
                eventDisplay: 'block', // Força renderização em bloco sólido na visão mensal

                // BUSCA DOS EVENTOS VIA API
                events: '/?view=agenda&action=get_events&agent_id=' + agentId + '&assistant_id=' + assistantId,

                // RENDERIZAÇÃO PERSONALIZADA E ROBUSTA DOS CARDS
                eventContent: function(arg) {
                    if (arg.event.extendedProps.type === 'block') {
                        return { html: '<div class="p-1 font-bold text-xs truncate text-white">🚫 Indisponível</div>' };
                    }
                    const client = arg.event.extendedProps.client_name || 'Cliente';
                    const agent = arg.event.extendedProps.agent_name || 'Atendente';
                    return {
                        html: `<div class="p-1 leading-snug overflow-hidden text-xs text-white h-full flex flex-col justify-start">
                                <div class="font-bold truncate text-white">📅 Reunião com ${client}</div>
                                <div class="text-[11px] opacity-95 truncate text-white font-medium">👤 Atendente: ${agent}</div>
                               </div>`
                    };
                },

                datesSet: function(info) {
                    document.getElementById('cal-title').innerText = info.view.title;
                    
                    document.querySelectorAll('#btn-month, #btn-week, #btn-day').forEach(b => {
                        b.classList.remove('bg-indigo-800');
                        b.classList.add('bg-indigo-600');
                    });
                    
                    if (info.view.type === 'dayGridMonth') document.getElementById('btn-month').classList.add('bg-indigo-800');
                    if (info.view.type === 'timeGridWeek') document.getElementById('btn-week').classList.add('bg-indigo-800');
                    if (info.view.type === 'timeGridDay') document.getElementById('btn-day').classList.add('bg-indigo-800');

                    setTimeout(() => {
                        if (info.view.type === 'dayGridMonth') {
                            const todayEl = document.querySelector('.fc-day-today');
                            if (todayEl) {
                                todayEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                        } else {
                            const now = new Date();
                            now.setHours(now.getHours() - 1);
                            const timeStr = now.getHours().toString().padStart(2, '0') + ':00:00';
                            calendar.scrollToTime(timeStr);
                        }
                    }, 50);
                },

                select: function(info) {
                    if (agentId === 'all') {
                        alert('Selecione um agente específico no filtro acima para poder criar um bloqueio.');
                        calendar.unselect();
                        return;
                    }
                    if (confirm('Criar BLOQUEIO neste horário?')) {
                        fetch('/?view=agenda', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                            body: JSON.stringify({
                                action: 'store_event',
                                human_agent_id: agentId,
                                start_time: info.startStr,
                                end_time: info.endStr,
                                type: 'block'
                            })
                        }).then(r => r.json()).then(data => {
                            if(data.success) calendar.refetchEvents();
                            else alert(data.message || 'Erro ao criar bloqueio.');
                        });
                    }
                },

                eventDrop: function(info) {
                    fetch('/?view=agenda', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({
                            action: 'update_event',
                            id: info.event.id,
                            start_time: info.event.startStr,
                            end_time: info.event.endStr
                        })
                    }).then(r => r.json()).then(data => {
                        if(!data.success) info.revert();
                    });
                },

                eventResize: function(info) {
                    fetch('/?view=agenda', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({
                            action: 'update_event',
                            id: info.event.id,
                            start_time: info.event.startStr,
                            end_time: info.event.endStr
                        })
                    }).then(r => r.json()).then(data => {
                        if(!data.success) info.revert();
                    });
                },

                // ABRIR O MODAL DE DETALHES
                eventClick: function(info) {
                    const alpineData = Alpine.$data(document.body);
                    alpineData.modalData = {
                        id: info.event.id,
                        ...info.event.extendedProps
                    };
                    alpineData.showModal = true;
                }
            });

            calendar.render();

            // FUNÇÃO GLOBAL DE EXCLUSÃO DO EVENTO VIA MODAL
            window.deleteCurrentEvent = function(id) {
                if (confirm('Deseja realmente excluir este registro?')) {
                    fetch('/?view=agenda', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ action: 'delete_event', id: id })
                    }).then(r => r.json()).then(data => {
                        if(data.success) {
                            const eventObj = calendar.getEventById(id);
                            if (eventObj) eventObj.remove();
                            Alpine.$data(document.body).showModal = false;
                        } else {
                            alert('Erro ao excluir evento.');
                        }
                    });
                }
            };

            document.getElementById('btn-prev').addEventListener('click', () => calendar.prev());
            document.getElementById('btn-next').addEventListener('click', () => calendar.next());
            document.getElementById('btn-today').addEventListener('click', () => calendar.today());
            document.getElementById('btn-month').addEventListener('click', () => calendar.changeView('dayGridMonth'));
            document.getElementById('btn-week').addEventListener('click', () => calendar.changeView('timeGridWeek'));
            document.getElementById('btn-day').addEventListener('click', () => calendar.changeView('timeGridDay'));
        });
    </script>
</body>
</html>