@extends('layouts.app')

@section('title', 'Calendário e Horários')

@push('head')
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales-all.global.min.js"></script>
    <style>
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
@endpush

@section('content')
        <div id="calendarRoot" x-data="{ showModal: false, modalData: {} }">
        <div class="container mx-auto px-6 max-w-6xl py-6 flex flex-col h-[calc(100vh-4rem)]">

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4 shrink-0">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800">Calendário e Horários</h1>
                    <p class="text-xs text-slate-500 mt-1">Gerencie a disponibilidade e as reuniões agendadas de cada membro.</p>
                </div>

                @if($lockedAgent)
                    <div class="bg-white border border-slate-200 rounded-lg px-4 py-2 text-xs flex items-center gap-2 shadow-sm">
                        <span class="dark-force-white font-bold text-indigo-600 uppercase text-[10px]">Sua Agenda:</span>
                        <span class="font-bold text-slate-700">{{ $agents->first()->name ?? auth()->user()->name }}</span>
                        @if($agents->first())
                            <span class="text-slate-400">({{ $agents->first()->department_name }})</span>
                        @endif
                    </div>
                @else
                <form id="calendarFilter" method="GET" action="/" class="flex flex-wrap items-center gap-3">
                    <input type="hidden" name="view" value="agenda">

                    <div class="flex items-center gap-2 border-r border-slate-200 pr-3">
                        <span class="dark-force-white font-bold text-indigo-600 uppercase text-[10px] tracking-wide">Status:</span>
                        <select name="status" onchange="document.querySelector('[name=assistant_id]').value=''; document.querySelector('[name=agent_id]').value='all'; this.form.submit()" class="font-bold text-slate-700 bg-transparent focus:outline-none cursor-pointer text-xs">
                            <option value="ativo" {{ $statusFilter == 'ativo' ? 'selected' : '' }}>Ativos</option>
                            <option value="inativo" {{ $statusFilter == 'inativo' ? 'selected' : '' }}>Inativos</option>
                            <option value="todos" {{ $statusFilter == 'todos' ? 'selected' : '' }}>Todos</option>
                        </select>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-lg px-3 py-1.5 text-xs flex items-center gap-2 shadow-sm">
                        <span class="dark-force-white font-bold text-indigo-600 uppercase text-[10px]">Assistente IA:</span>
                        <select name="assistant_id" onchange="document.querySelector('[name=agent_id]').value='all'; this.form.submit()" class="font-bold text-slate-700 bg-transparent focus:outline-none cursor-pointer">
                            @forelse($assistants as $ast)
                                <option value="{{ $ast->id }}" {{ $currentAssistantId == $ast->id ? 'selected' : '' }}>{{ $ast->name }}</option>
                            @empty
                                <option value="">Nenhum assistente</option>
                            @endforelse
                        </select>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-lg px-3 py-1.5 text-xs flex items-center gap-2 shadow-sm">
                        <span class="dark-force-white font-bold text-indigo-600 uppercase text-[10px]">AGENTE:</span>
                        <select name="agent_id" onchange="this.form.submit()" class="font-bold text-slate-700 bg-transparent focus:outline-none cursor-pointer max-w-[200px] truncate">
                            <option value="all" {{ $currentAgentId === 'all' ? 'selected' : '' }}>Todos os Agentes</option>
                            @foreach($agents as $ag)
                                <option value="{{ $ag->id }}" {{ (string)$currentAgentId === (string)$ag->id ? 'selected' : '' }}>{{ $ag->name }} ({{ $ag->department_name }})</option>
                            @endforeach
                        </select>
                    </div>
                </form>
                @endif
            </div>

            <div class="light-surface bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col flex-1 min-h-0">

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

                <div class="pt-3 border-t border-slate-100 flex items-center {{ $readOnly ? 'justify-end' : 'justify-between' }} gap-3">
                    @if(!$readOnly)
                        <button type="button" @click="deleteCurrentEvent(modalData.id)" class="px-3.5 py-2 text-xs font-bold text-red-600 hover:bg-red-50 rounded-xl transition border border-red-200">
                            Excluir Registro
                        </button>
                    @endif
                    <button type="button" @click="showModal = false" class="px-4 py-2 text-xs font-bold bg-slate-800 text-white hover:bg-slate-900 rounded-xl transition shadow-sm">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
        </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            const agentId = '{{ $currentAgentId }}';
            const assistantId = '{{ $currentAssistantId }}';
            const csrfToken = '{{ csrf_token() }}';
            const readOnly = {{ $readOnly ? 'true' : 'false' }};

            const calendar = new FullCalendar.Calendar(calendarEl, {
                locale: 'pt-br',
                height: '100%',
                initialView: 'timeGridWeek',
                headerToolbar: false,
                allDaySlot: false,
                slotMinTime: '00:00:00',
                slotMaxTime: '24:00:00',
                editable: !readOnly,
                selectable: !readOnly,
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
                                user_id: agentId,
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
                    const alpineData = Alpine.$data(document.getElementById('calendarRoot'));
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
                            Alpine.$data(document.getElementById('calendarRoot')).showModal = false;
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
@endpush