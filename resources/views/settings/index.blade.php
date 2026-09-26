@extends('layouts.app')

@section('title', 'Configurações Avançadas - ' . $assistant->name)

@section('content')
        <form id="settingsForm" action="/?view=settings" method="POST" class="container mx-auto px-6 max-w-6xl flex flex-col h-[calc(100vh-8rem)] pt-4" onsubmit="saveSettingsScrollPosition()">
            @csrf

            <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">

            <!-- CABEÇALHO FIXO -->
            <div class="shrink-0 bg-gray-50 py-4 mb-4 border-b border-gray-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <a href="/?configure={{ $assistant->id }}" class="dark-btn-fix text-indigo-600 hover:text-indigo-800 font-semibold flex items-center gap-1.5 text-sm transition bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg border border-indigo-100 shadow-sm">
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

            <div id="settingsScrollArea" class="flex-1 min-h-0 overflow-y-auto custom-scroll pr-1 pb-8">

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
                                <span x-text="selected" class="dark-force-white truncate font-semibold text-indigo-600"></span>
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

                        <!-- Duração da Reunião -->
                        <div class="md:col-span-1">
                            <label for="meeting_duration_minutes" class="block text-xs font-semibold text-gray-700 mb-1">Duração da Reunião (minutos)</label>
                            <input type="number" name="meeting_duration_minutes" id="meeting_duration_minutes" min="15" step="5" value="{{ $meetingDurationMinutes ?? 60 }}" class="w-full border border-gray-300 rounded-lg p-2.5 text-xs font-medium text-gray-800 focus:ring-2 focus:ring-indigo-500 outline-none">
                            <p class="text-[11px] text-gray-400 mt-1">Usada pra calcular o horário de término e verificar conflitos de agenda.</p>
                        </div>

                        <!-- Horário Comercial -->
                        <div class="md:col-span-1">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Horário de Atendimento</label>
                            <div class="flex items-center gap-2">
                                <input type="time" name="business_hours_start" value="{{ $businessHoursStart ?? '09:00' }}" required class="w-full border border-gray-300 rounded-lg p-2.5 text-xs font-medium text-gray-800 focus:ring-2 focus:ring-indigo-500 outline-none">
                                <span class="text-xs text-gray-400">às</span>
                                <input type="time" name="business_hours_end" value="{{ $businessHoursEnd ?? '17:00' }}" required class="w-full border border-gray-300 rounded-lg p-2.5 text-xs font-medium text-gray-800 focus:ring-2 focus:ring-indigo-500 outline-none">
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1">Reuniões fora dessa janela são recusadas automaticamente, mesmo que o horário esteja livre.</p>
                        </div>

                        <!-- Bloquear Fins de Semana -->
                        <div class="md:col-span-1 flex items-end pb-1">
                            <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-pointer">
                                <input type="checkbox" name="business_block_weekends" value="1" {{ ($businessBlockWeekends ?? '1') == '1' ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                Não agendar aos sábados e domingos
                            </label>
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
            </div>
        </form>

        <div class="container mx-auto px-6 max-w-6xl pb-8">
            <!-- Card: Feriados (dias sem agendamento) - fora do form principal porque tem os
                 próprios botões de adicionar/remover, que precisam submeter na hora. -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <h2 class="text-base font-bold text-gray-800 border-b border-gray-100 pb-3 mb-5 flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                    Feriados (dias sem agendamento)
                </h2>

                <form action="/" method="POST" class="grid grid-cols-1 md:grid-cols-[1fr_auto_auto_auto] gap-3 items-end mb-5">
                    @csrf
                    <input type="hidden" name="view" value="settings">
                    <input type="hidden" name="action" value="add_holiday">
                    <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Nome</label>
                        <input type="text" name="holiday_name" placeholder="Ex: Natal" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Data</label>
                        <input type="date" name="holiday_date" required class="border border-gray-300 rounded-lg px-3 py-2 text-xs outline-none focus:border-indigo-500">
                    </div>
                    <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 pb-2.5 cursor-pointer whitespace-nowrap">
                        <input type="checkbox" name="holiday_recurring" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                        Repete todo ano
                    </label>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold py-2.5 px-4 rounded-lg transition whitespace-nowrap">+ Adicionar</button>
                </form>
                <p class="text-[11px] text-gray-400 -mt-3 mb-4">"Repete todo ano" bloqueia sempre esse dia/mês (ex: 25/12), qualquer que seja o ano. Sem marcar, bloqueia só essa data exata (ex: Carnaval de um ano específico, que muda de data).</p>

                <div class="space-y-1.5">
                    @forelse($holidays as $holiday)
                        <div class="flex items-center justify-between bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                            <div class="text-xs text-gray-700">
                                <span class="font-bold">{{ $holiday->name }}</span>
                                <span class="text-gray-400 ml-2">{{ $holiday->date->format('d/m/Y') }}</span>
                                @if($holiday->is_recurring)
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 border border-indigo-100 ml-2">Todo ano</span>
                                @endif
                            </div>
                            <form action="/" method="POST" onsubmit="return confirm('Remover este feriado?');">
                                @csrf
                                <input type="hidden" name="view" value="settings">
                                <input type="hidden" name="action" value="delete_holiday">
                                <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">
                                <input type="hidden" name="holiday_id" value="{{ $holiday->id }}">
                                <button type="submit" class="text-gray-400 hover:text-red-500 hover:bg-red-50 p-1.5 rounded-lg transition" title="Remover">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </form>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 text-center py-4">Nenhum feriado cadastrado ainda.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <script>
            function saveSettingsScrollPosition() {
                const scrollArea = document.getElementById('settingsScrollArea');
                if (scrollArea) {
                    sessionStorage.setItem('scrollpos_settings_{{ $assistant->id }}', scrollArea.scrollTop);
                }
            }

            document.addEventListener("DOMContentLoaded", function() {
                const key = 'scrollpos_settings_{{ $assistant->id }}';
                const scrollpos = sessionStorage.getItem(key);
                if (scrollpos !== null) {
                    setTimeout(() => {
                        const scrollArea = document.getElementById('settingsScrollArea');
                        if (scrollArea) {
                            scrollArea.scrollTop = parseInt(scrollpos);
                        }
                    }, 50);
                    sessionStorage.removeItem(key);
                }
            });
        </script>
@endsection