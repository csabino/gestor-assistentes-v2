@extends('layouts.app')

@section('title', 'Gestor de Assistentes IA')

@push('scripts')
    <script>
        function openChatPopup(id) {
            const width = 720;
            const height = 480;
            const left = (window.top.outerWidth / 2) + window.top.screenX - (width / 2);
            const top = (window.top.outerHeight / 2) + window.top.screenY - (height / 2);
            window.open(
                '/chat/' + id,
                'chat_' + id,
                `toolbar=no, location=no, status=no, menubar=no, scrollbars=no, resizable=yes, width=${width}, height=${height}, top=${top}, left=${left}`
            );
        }

        function copyChatLink(id) {
            const url = window.location.origin + '/chat/' + id;
            navigator.clipboard.writeText(url).then(() => {
                alert('Link copiado!');
            }).catch(() => {
                alert('Não foi possível copiar o link.');
            });
        }
    </script>

    @if($configuring)
        <script>
            function saveScrollPosition() {
                const mainContent = document.getElementById('mainContent');
                if (mainContent) {
                    sessionStorage.setItem('scrollpos_config_{{ $configuring->id }}', mainContent.scrollTop);
                }
            }

            document.addEventListener("DOMContentLoaded", function() {
                const key = 'scrollpos_config_{{ $configuring->id }}';
                const scrollpos = sessionStorage.getItem(key);
                if (scrollpos !== null) {
                    setTimeout(() => {
                        const mainContent = document.getElementById('mainContent');
                        if (mainContent) {
                            mainContent.scrollTop = parseInt(scrollpos);
                        }
                    }, 50);
                    sessionStorage.removeItem(key);
                }
            });
        </script>
    @endif
@endpush

@section('content')
        <div class="container mx-auto px-6 max-w-6xl py-8">

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

            @if($currentView === 'equipe')
                <div class="space-y-6">
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                        <div class="border-b border-gray-100 pb-3 mb-6">
                            <h1 class="text-xl font-bold text-gray-800">Gestão de Departamentos & Agentes</h1>
                            <p class="text-xs text-gray-500 mt-1">Gerencie os setores de atendimento e a lista de agentes associados.</p>
                        </div>

                        <form action="/" method="POST" class="mb-8 bg-gray-50 p-4 rounded-xl border border-gray-200">
                            @csrf
                            <input type="hidden" name="action" value="store_department">
                            <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wide mb-2">Novo Departamento</label>
                            <div class="flex gap-3">
                                <input type="text" name="name" required placeholder="Ex: Suporte Técnico, Vendas..." class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-2.5 rounded-lg text-xs transition shadow-sm">Criar Departamento</button>
                            </div>
                        </form>

                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                            @forelse($departments as $dept)
                                <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm space-y-4">
                                    <div class="flex justify-between items-center border-b border-gray-100 pb-2">
                                        <h3 class="font-bold text-gray-800 text-base flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 bg-indigo-500 rounded-full"></span>
                                            {{ $dept->name }}
                                        </h3>
                                    </div>

                                    <form action="/" method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-slate-50 p-3.5 rounded-lg border border-slate-200">
                                        @csrf
                                        <input type="hidden" name="action" value="store_agent">
                                        <input type="hidden" name="department_id" value="{{ $dept->id }}">
                                        <div class="sm:col-span-2">
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Nome do Agente</label>
                                            <input type="text" name="name" required placeholder="Ex: Carlos Silva" class="w-full border border-slate-300 rounded-md px-3 py-2 text-xs outline-none focus:border-indigo-500">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">E-mail Corporativo</label>
                                            <input type="email" name="email" required placeholder="carlos@empresa.com" class="w-full border border-slate-300 rounded-md px-3 py-2 text-xs outline-none focus:border-indigo-500">
                                        </div>
                                        <div class="flex items-end">
                                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 rounded-md text-xs transition shadow-sm">
                                                Adicionar Agente
                                            </button>
                                        </div>
                                    </form>

                                    <div class="overflow-x-auto border border-gray-100 rounded-lg">
                                        <table class="w-full text-left text-xs border-collapse">
                                            <thead>
                                                <tr class="bg-gray-50 text-gray-500 border-b border-gray-100 uppercase text-[10px] tracking-wider">
                                                    <th class="py-2.5 px-4 font-semibold">Agente</th>
                                                    <th class="py-2.5 px-4 font-semibold">E-mail</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-50">
                                                @forelse($agents->where('department_id', $dept->id) as $agent)
                                                    <tr class="hover:bg-gray-50">
                                                        <td class="py-3 px-4 font-bold text-gray-800">{{ $agent->name }}</td>
                                                        <td class="py-3 px-4 text-gray-600 font-mono">{{ $agent->email }}</td>
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="2" class="py-5 text-center text-gray-400 italic">Nenhum agente cadastrado neste departamento.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @empty
                                <div class="col-span-full text-center py-12 text-gray-400">Nenhum departamento cadastrado ainda.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

            @elseif($currentView === 'agenda')
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <div class="border-b border-gray-100 pb-3 mb-6">
                        <h1 class="text-xl font-bold text-gray-800">Calendário e Agendamentos</h1>
                        <p class="text-xs text-gray-500 mt-1">Visualize e gerencie os compromissos agendados pelos assistentes virtuais.</p>
                    </div>
                    <div class="p-12 text-center border-2 border-dashed border-gray-200 rounded-xl bg-gray-50">
                        <svg class="w-12 h-12 text-indigo-400 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008z" /></svg>
                        <h3 class="font-bold text-gray-700 text-sm">Integração de Agendamentos Ativa</h3>
                        <p class="text-xs text-gray-400 mt-1">A grade horária está sincronizada com os assistentes virtuais.</p>
                    </div>
                </div>

            @elseif($configuring)
                
                <div class="sticky top-0 z-40 bg-gray-50/90 backdrop-blur-md py-4 mb-6 border-b border-gray-200 flex flex-col md:flex-row items-center justify-between gap-4 shadow-sm -mx-6 px-6">
                    <div class="flex items-center gap-4">
                        <a href="/" class="text-indigo-600 hover:text-indigo-800 font-semibold flex items-center gap-1.5 text-sm transition bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg border border-indigo-100">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg> Voltar
                        </a>
                        <div class="h-6 w-px bg-gray-300 hidden md:block"></div>
                        <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">{{ $configuring->name }}</h1>
                    </div>
                    
                    <div class="flex items-center gap-3 w-full md:w-auto">
                        <a href="/?view=settings&assistant_id={{ $configuring->id }}" class="text-slate-700 bg-white hover:bg-indigo-50 hover:text-indigo-600 border border-gray-300 text-xs px-3.5 py-2 rounded-lg font-semibold transition shadow-sm flex justify-center items-center gap-2 shrink-0" title="Configurações Avançadas (Fuso Horário & Webhook)">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.27 1.06-.12 1.451l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.27-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.149-.894z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="hidden sm:inline">Configurações Avançadas</span>
                        </a>

                        <form action="/" method="POST" class="flex-1 md:flex-none">
                            @csrf @method('PATCH')
                            <input type="hidden" name="assistant_id" value="{{ $configuring->id }}">
                            <input type="hidden" name="from_config" value="1">
                            <button type="submit" onclick="saveScrollPosition()" class="w-full md:w-auto text-sm px-4 py-2 rounded-lg font-semibold transition border flex justify-center items-center gap-2 {{ $configuring->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-gray-100 text-gray-600 border-gray-300 hover:bg-gray-200' }}">
                                @if($configuring->is_active)
                                    <svg class="w-2.5 h-2.5 fill-emerald-500" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg> Ativo
                                @else
                                    <svg class="w-2.5 h-2.5 fill-gray-400" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg> Inativo
                                @endif
                            </button>
                        </form>
                        <button type="submit" form="configForm" onclick="saveScrollPosition()" class="flex-1 md:flex-none bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg shadow-md transition text-sm flex justify-center items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg> Salvar Configurações
                        </button>
                    </div>
                </div>

                <form id="configForm" action="/" method="POST" enctype="multipart/form-data" onsubmit="saveScrollPosition()"
                    x-data="{ 
                        provider: '{{ $configuring->provider ?? 'openai' }}',
                        wa_provider: '{{ $configuring->whatsapp_provider ?? '' }}',
                        wa_url: '{{ $configuring->whatsapp_url ?? '' }}',
                        wa_instance: '{{ $configuring->whatsapp_instance ?? '' }}',
                        wa_token: '{{ $configuring->whatsapp_token ?? '' }}',
                        
                        testing: false,
                        testResult: null,
                        showWaModal: false,
                        showWebhookModal: new URLSearchParams(window.location.search).get('modal') === 'webhook',
                        showLeadModal: false,
                        leadFields: {{ json_encode($configuring->lead_fields) }},
                        
                        waLoading: false,
                        waResult: null,
                        pollAttempts: 0,
                        waStatus: 'checking', 
                        
                        websiteToCrawl: '',
                        crawling: false,
                        crawlProgressText: '',
                        crawlPercent: 0,

                        getApiKey() {
                            if (this.provider === 'openai') return document.querySelector('input[name=\'openai_api_key\']').value;
                            if (this.provider === 'gemini') return document.querySelector('input[name=\'gemini_api_key\']').value;
                            if (this.provider === 'anthropic') return document.querySelector('input[name=\'anthropic_api_key\']').value;
                            if (this.provider === 'grok') return document.querySelector('input[name=\'grok_api_key\']').value;
                            return '';
                        },
                        
                        getWaParams() {
                            return { 
                                assistant_id: '{{ $configuring->id ?? '' }}',
                                provider: this.wa_provider, 
                                url: this.wa_url, 
                                instance: this.wa_instance, 
                                token: this.wa_token 
                            };
                        },

                        async startCrawler() {
                            if (!this.websiteToCrawl) return alert('Por favor, digite a URL do site para importar.');
                            
                            this.crawling = true;
                            this.crawlPercent = 5;
                            this.crawlProgressText = 'Mapeando as páginas internas do site...';

                            try {
                                const mapRes = await fetch('/', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                    body: JSON.stringify({ action: 'map_site', website_url: this.websiteToCrawl })
                                });
                                const mapData = await mapRes.json();
                                
                                if (!mapData.success || !mapData.urls || mapData.urls.length === 0) {
                                    alert(mapData.message || 'Não foi possível mapear o site. Tente colocar uma URL específica.');
                                    this.crawling = false;
                                    return;
                                }

                                const urls = mapData.urls;
                                let count = 0;
                                
                                for (const url of urls) {
                                    count++;
                                    this.crawlPercent = Math.round((count / urls.length) * 100);
                                    this.crawlProgressText = `Salvando ${count} de ${urls.length}: ${url}`;
                                    
                                    await fetch('/', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                        body: JSON.stringify({ 
                                            action: 'scrape_single_url', 
                                            assistant_id: '{{ $configuring->id ?? '' }}', 
                                            website_url: url 
                                        })
                                    });
                                }

                                this.crawlProgressText = 'Extração concluída! Recarregando...';
                                saveScrollPosition();
                                window.location.reload();

                            } catch (e) {
                                alert('Erro de conexão durante a extração.');
                                this.crawling = false;
                            }
                        },

                        async testConnection() {
                            this.testing = true;
                            this.testResult = null;
                            try {
                                const response = await fetch('/', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                    body: JSON.stringify({ action: 'test_ai', provider: this.provider, api_key: this.getApiKey() })
                                });
                                this.testResult = await response.json();
                            } catch (e) {
                                this.testResult = { success: false, message: 'Erro ao processar requisição.' };
                            } finally {
                                this.testing = false;
                            }
                        },

                        async checkWaStatusSilent() {
                            if(!this.wa_provider || !this.wa_url || !this.wa_token) {
                                this.waStatus = 'disconnected';
                                return;
                            }
                            this.waStatus = 'checking';
                            try {
                                const response = await fetch('/', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                    body: JSON.stringify({ action: 'status_whatsapp', ...this.getWaParams() })
                                });
                                const data = await response.json();
                                this.waStatus = data.connected ? 'connected' : 'disconnected';
                            } catch(e) {
                                this.waStatus = 'disconnected';
                            }
                        },

                        async disconnectWa() {
                            if(!confirm('Tem certeza que deseja desconectar a sessão do WhatsApp?')) return;
                            this.waStatus = 'checking';
                            try {
                                const response = await fetch('/', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                    body: JSON.stringify({ action: 'disconnect_whatsapp', ...this.getWaParams() })
                                });
                                const data = await response.json();
                                
                                if (data.success) {
                                    this.waStatus = 'disconnected';
                                    saveScrollPosition();
                                    window.location.reload(); 
                                } else {
                                    alert('A API não conseguiu desconectar:\n\n' + (data.message || 'Erro desconhecido.'));
                                    this.checkWaStatusSilent(); 
                                }
                            } catch(e) {
                                alert('Erro na requisição.');
                                this.checkWaStatusSilent();
                            }
                        },

                        async startWaConnection() {
                            this.showWaModal = true;
                            this.pollAttempts = 0;
                            this.waResult = null;
                            await this.runWaPoll();
                        },

                        async runWaPoll() {
                            if (!this.showWaModal) return;
                            if (!this.waResult || (!this.waResult.qr && !this.waResult.connected)) this.waLoading = true;
                            
                            try {
                                const response = await fetch('/', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                    body: JSON.stringify({ action: 'test_whatsapp', ...this.getWaParams() })
                                });
                                const data = await response.json();
                                this.waResult = data;
                                
                                if (data.connected) {
                                    this.waStatus = 'connected';
                                } else if (data.success && this.pollAttempts < 20 && this.showWaModal) {
                                    this.pollAttempts++;
                                    setTimeout(() => {
                                        if (this.showWaModal) this.runWaPoll();
                                    }, 3000);
                                }
                            } catch (e) {
                            } finally {
                                this.waLoading = false;
                            }
                        },

                        closeWebhookModal() {
                            this.showWebhookModal = false;
                            window.history.replaceState(null, '', '/?configure={{ $configuring->id }}');
                        }
                    }"
                    x-init="checkWaStatusSilent()">
                    
                    @csrf @method('PUT')
                    <input type="hidden" name="assistant_id" value="{{ $configuring->id }}">

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        
                        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-gray-200 h-full">
                            <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-4">
                                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-indigo-500"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" /></svg>
                                    Personalidade e Prompt
                                </h2>
                                <button type="button" x-on:click="showLeadModal = true" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold py-1.5 px-3 rounded-lg border border-indigo-200 flex items-center gap-1.5 transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                                    Campos de Qualificação
                                </button>
                            </div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Instruções do Sistema</label>
                            <textarea name="system_prompt" rows="12" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Ex: Você é um vendedor especializado na loja X...">{{ $configuring->system_prompt }}</textarea>
                        </div>

                        <div class="lg:col-span-1 bg-white p-6 rounded-xl shadow-sm border border-gray-200 h-full">
                            <div class="border-b border-gray-100 pb-3 mb-4 flex items-center justify-between gap-2">
                                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-indigo-500"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z" /></svg>
                                    Conexão IA
                                </h2>
                                <button type="button" x-on:click="testConnection()" :disabled="testing" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold py-1.5 px-3 rounded-lg border border-indigo-200 flex items-center shrink-0">
                                    <span x-text="testing ? '⌛ Testando...' : '⚡ Testar'"></span>
                                </button>
                            </div>

                            <div x-show="testResult !== null" x-transition class="mb-4 p-3 rounded-lg text-xs border" :class="testResult?.success ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-red-50 text-red-800 border-red-200'">
                                <p class="font-bold mb-0.5" x-text="testResult?.success ? '✅ Sucesso!' : '❌ Falha'"></p>
                                <p x-text="testResult?.message"></p>
                            </div>

                            <div class="flex gap-3 mb-4">
                                <div class="flex-1">
                                    <label class="block text-[11px] font-semibold text-gray-700 mb-1">Provedor</label>
                                    <select name="provider" x-model="provider" class="w-full border border-gray-300 rounded-lg p-2.5 text-xs focus:ring-2 focus:ring-indigo-500">
                                        <option value="openai">OpenAI</option>
                                        <option value="gemini">Google</option>
                                        <option value="anthropic">Anthropic</option>
                                        <option value="grok">xAI</option>
                                    </select>
                                </div>
                                <div class="w-24">
                                    <label class="block text-[11px] font-semibold text-gray-700 mb-1" title="Mensagens lembradas">Contexto</label>
                                    <input type="number" name="context_limit" value="{{ $configuring->context_limit ?? 12 }}" min="2" max="50" class="w-full border border-gray-300 rounded-lg p-2 text-xs text-center focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>

                            <div x-show="provider === 'openai'" x-transition>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Modelo da OpenAI</label>
                                <select name="model" :disabled="provider !== 'openai'" class="w-full border border-gray-300 rounded-lg p-2.5 text-xs mb-4">
                                    <option value="gpt-4o-mini" {{ $configuring->model == 'gpt-4o-mini' ? 'selected' : '' }}>GPT-4o Mini (Rápido/Barato)</option>
                                    <option value="gpt-4o" {{ $configuring->model == 'gpt-4o' ? 'selected' : '' }}>GPT-4o (Avançado)</option>
                                </select>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">OpenAI API Key</label>
                                <input type="password" name="openai_api_key" value="{{ $configuring->openai_api_key }}" placeholder="sk-..." class="w-full border border-gray-300 rounded-lg p-2.5 text-xs">
                            </div>

                            <div x-show="provider === 'gemini'" x-transition style="display: none;">
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Modelo do Google</label>
                                <select name="model" :disabled="provider !== 'gemini'" class="w-full border border-gray-300 rounded-lg p-2.5 text-xs mb-4">
                                    <option value="gemini-1.5-flash" {{ $configuring->model == 'gemini-1.5-flash' ? 'selected' : '' }}>Gemini 1.5 Flash</option>
                                    <option value="gemini-1.5-pro" {{ $configuring->model == 'gemini-1.5-pro' ? 'selected' : '' }}>Gemini 1.5 Pro</option>
                                </select>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Gemini API Key</label>
                                <input type="password" name="gemini_api_key" value="{{ $configuring->gemini_api_key }}" placeholder="AIza..." class="w-full border border-gray-300 rounded-lg p-2.5 text-xs">
                            </div>

                            <div x-show="provider === 'anthropic'" x-transition style="display: none;">
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Modelo do Claude</label>
                                <select name="model" :disabled="provider !== 'anthropic'" class="w-full border border-gray-300 rounded-lg p-2.5 text-xs mb-4">
                                    <option value="claude-3-haiku" {{ $configuring->model == 'claude-3-haiku' ? 'selected' : '' }}>Claude 3 Haiku</option>
                                    <option value="claude-3.5-sonnet" {{ $configuring->model == 'claude-3.5-sonnet' ? 'selected' : '' }}>Claude 3.5 Sonnet</option>
                                </select>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Anthropic API Key</label>
                                <input type="password" name="anthropic_api_key" value="{{ $configuring->anthropic_api_key }}" placeholder="sk-ant-..." class="w-full border border-gray-300 rounded-lg p-2.5 text-xs">
                            </div>

                            <div x-show="provider === 'grok'" x-transition style="display: none;">
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Modelo do Grok</label>
                                <select name="model" :disabled="provider !== 'grok'" class="w-full border border-gray-300 rounded-lg p-2.5 text-xs mb-4">
                                    <option value="grok-2-mini" {{ $configuring->model == 'grok-2-mini' ? 'selected' : '' }}>Grok 2 Mini</option>
                                    <option value="grok-2" {{ $configuring->model == 'grok-2' ? 'selected' : '' }}>Grok 2</option>
                                </select>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">xAI API Key</label>
                                <input type="password" name="grok_api_key" value="{{ $configuring->grok_api_key }}" placeholder="xai-..." class="w-full border border-gray-300 rounded-lg p-2.5 text-xs">
                            </div>
                        </div>

                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                        
                        <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-gray-200 h-full flex flex-col">
                            <h2 class="text-lg font-bold text-gray-800 border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-indigo-500"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776" /></svg>
                                Base de Conhecimento
                            </h2>
                            
                            <div class="flex-1" x-data="{ 
                                selectedFiles: [], 
                                totalFiles: {{ count($configuring->knowledge_files ?? []) }},
                                get allSelected() { return this.selectedFiles.length === this.totalFiles && this.totalFiles > 0; },
                                toggleAll() { 
                                    if (this.selectedFiles.length === this.totalFiles) {
                                        this.selectedFiles = [];
                                    } else {
                                        this.selectedFiles = Array.from({length: this.totalFiles}, (_, i) => String(i));
                                    }
                                },
                                submitBulkDelete() {
                                    if(!confirm('Tem certeza que deseja apagar os ' + this.selectedFiles.length + ' itens selecionados?')) return;
                                    saveScrollPosition();
                                    const form = document.getElementById('bulkDeleteForm');
                                    form.innerHTML = '<input type=\'hidden\' name=\'_token\' value=\'{{ csrf_token() }}\'><input type=\'hidden\' name=\'_method\' value=\'DELETE\'><input type=\'hidden\' name=\'assistant_id\' value=\'{{ $configuring->id }}\'>';
                                    this.selectedFiles.forEach(idx => {
                                        form.innerHTML += '<input type=\'hidden\' name=\'file_indexes[]\' value=\'' + idx + '\'>';
                                    });
                                    form.submit();
                                }
                            }">
                                @if($configuring->knowledge_files && count($configuring->knowledge_files) > 0)
                                    <div class="mb-5 bg-gray-50 p-4 rounded-lg border border-gray-200">
                                        <div class="flex justify-between items-center mb-3 border-b border-gray-200 pb-2">
                                            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Fontes ({{ count($configuring->knowledge_files) }})</h4>
                                            
                                            <div class="flex items-center gap-3">
                                                <label class="flex items-center gap-1.5 text-[11px] text-slate-600 cursor-pointer hover:text-indigo-600 transition">
                                                    <input type="checkbox" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" 
                                                           :checked="allSelected" @click="toggleAll()">
                                                    <span class="font-bold">Selecionar Todos</span>
                                                </label>

                                                <button type="button" x-show="selectedFiles.length > 0" x-transition @click="submitBulkDelete()" class="text-[10px] bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 px-2 py-1 rounded font-bold flex items-center gap-1 transition shadow-sm">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                                    Apagar (<span x-text="selectedFiles.length"></span>)
                                                </button>
                                            </div>
                                        </div>

                                        <div class="max-h-[115px] overflow-y-auto pr-1 custom-scroll">
                                            <ul class="space-y-1.5 text-sm text-gray-700">
                                                @foreach($configuring->knowledge_files as $index => $file)
                                                    <li class="flex items-center justify-between bg-white px-3 py-1.5 border rounded-md shadow-sm transition"
                                                        :class="selectedFiles.includes('{{ $index }}') ? 'border-indigo-300 bg-indigo-50/50' : 'border-gray-200'">
                                                        
                                                        <div class="flex items-center gap-3 truncate w-full">
                                                            <input type="checkbox" value="{{ $index }}" x-model="selectedFiles" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer shrink-0">
                                                            
                                                            <div class="flex items-center gap-2 truncate">
                                                                @if(str_starts_with($file['name'], '🌐'))
                                                                    <span class="text-blue-500 shrink-0 text-[14px]">🌐</span>
                                                                @else
                                                                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                                                @endif
                                                                <span class="truncate font-medium text-xs">{{ str_replace('🌐 ', '', $file['name']) }}</span>
                                                            </div>
                                                        </div>
                                                        
                                                        <button type="button" onclick="if(confirm('Remover esta fonte de conhecimento?')) { saveScrollPosition(); document.getElementById('deleteFileForm_{{ $index }}').submit(); }" class="text-gray-400 hover:text-red-600 hover:bg-red-50 p-1 rounded transition shrink-0 ml-2">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="mt-auto border-t border-gray-100 pt-4 space-y-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1">Anexar Arquivos (PDF, Word, TXT)</label>
                                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                        <input type="file" name="documents[]" multiple accept=".pdf,.doc,.docx,.txt" class="block w-full text-sm text-gray-500 border border-gray-200 rounded-lg p-1">
                                        <button type="submit" form="configForm" onclick="saveScrollPosition(); this.innerHTML='Salvando...'" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold py-2.5 px-4 rounded-lg flex items-center justify-center gap-1.5 shrink-0 transition shadow-sm">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg> Anexar
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-1">🌐 Importar Site (Extração de Conteúdo)</label>
                                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                        <input type="url" x-model="websiteToCrawl" placeholder="https://www.site.com" class="block w-full text-sm border border-slate-300 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-indigo-500">
                                        
                                        <button type="button" @click="startCrawler()" :disabled="crawling" :class="crawling ? 'bg-indigo-400 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700'" class="text-white text-xs font-bold py-2.5 px-4 rounded-lg flex items-center justify-center gap-1.5 shrink-0 transition shadow-sm whitespace-nowrap">
                                            <span x-show="!crawling" class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" /></svg> Extrair Site</span>
                                            <span x-show="crawling" class="flex items-center gap-1.5"><span class="inline-block animate-spin rounded-full h-3 w-3 border-2 border-white border-t-transparent"></span> Extraindo...</span>
                                        </button>
                                    </div>

                                    <div x-show="crawling" class="mt-3" x-cloak>
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="text-[10px] font-bold text-indigo-600 truncate max-w-[85%]" x-text="crawlProgressText"></span>
                                            <span class="text-[10px] font-bold text-slate-500" x-text="crawlPercent + '%'"></span>
                                        </div>
                                        <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-300" :style="'width: ' + crawlPercent + '%'"></div>
                                        </div>
                                    </div>
                                    
                                    <p x-show="!crawling" class="text-[10px] text-slate-400 mt-1.5 leading-tight">O sistema irá varrer a URL, identificar as páginas internas e extrair o texto útil automaticamente.</p>
                                </div>

                            </div>
                        </div>

                        <div class="lg:col-span-1 bg-white p-4 rounded-xl shadow-sm border border-gray-200 h-full flex flex-col">
                            
                            <h2 class="text-base font-bold text-gray-800 border-b border-gray-100 pb-2 mb-3 flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-green-500"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" /></svg>
                                    Canal: WhatsApp
                                    
                                    <button type="button" x-show="wa_provider !== ''" x-cloak x-on:click="showWebhookModal = true" class="ml-1 bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 border border-slate-200 hover:border-indigo-200 px-2 py-1 rounded-md transition text-xs font-medium flex items-center gap-1 shadow-sm" title="Diagnóstico e Webhook">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.27 1.06-.12 1.451l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.27-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.149-.894z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </button>
                                </div>
                                
                                <div class="flex items-center gap-1.5" x-show="wa_provider !== ''" x-cloak>
                                    <span x-show="waStatus === 'checking'" class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500 animate-pulse border border-gray-200">Verificando...</span>
                                    <span x-show="waStatus === 'connected'" class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded-full bg-emerald-50 text-emerald-600 flex items-center gap-1 border border-emerald-200 shadow-sm">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>Conectado
                                    </span>
                                    <span x-show="waStatus === 'disconnected'" class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded-full bg-red-50 text-red-600 flex items-center gap-1 border border-red-200 shadow-sm">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>Desconectado
                                    </span>
                                    
                                    <button type="button" title="Desconectar" x-show="waStatus === 'connected'" x-on:click="disconnectWa()" class="w-6 h-6 rounded-full bg-red-50 hover:bg-red-100 text-red-600 transition border border-red-200 flex items-center justify-center cursor-pointer shadow-sm shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" /></svg>
                                    </button>
                                </div>
                            </h2>

                            <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Plataforma</label>
                            <select name="whatsapp_provider" x-model="wa_provider" x-on:change="checkWaStatusSilent()" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px] mb-3 focus:ring-2 focus:ring-indigo-500">
                                <option value="">Desativado</option>
                                <option value="uazapi">UaZapi</option>
                                <option value="evolution">Evolution API</option>
                                <option value="meta">API Oficial (Meta)</option>
                                <option value="zapi">Z-API</option>
                                <option value="chatpro">ChatPro</option>
                            </select>

                            <div x-show="wa_provider !== ''" x-transition class="space-y-3 flex-1">
                                <template x-if="wa_provider === 'uazapi'">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">URL (UaZapi)</label>
                                        <input type="url" name="whatsapp_url" x-model="wa_url" x-on:change="checkWaStatusSilent()" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px] mb-2" placeholder="https://api.uazapi.dev">
                                        <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Nome da Instância</label>
                                        <input type="text" name="whatsapp_instance" x-model="wa_instance" x-on:change="checkWaStatusSilent()" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px] mb-2" placeholder="Ex: suporte">
                                        <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Instance Token</label>
                                        <input type="password" name="whatsapp_token" x-model="wa_token" x-on:change="checkWaStatusSilent()" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px]" placeholder="Ex: T0K3N...">
                                    </div>
                                </template>
                                <template x-if="wa_provider === 'evolution'">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">URL (Evolution)</label>
                                        <input type="url" name="whatsapp_url" x-model="wa_url" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px] mb-2" placeholder="https://api...">
                                        <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Nome da Instância</label>
                                        <input type="text" name="whatsapp_instance" x-model="wa_instance" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px] mb-2">
                                        <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Global API Key</label>
                                        <input type="password" name="whatsapp_token" x-model="wa_token" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px]">
                                    </div>
                                </template>
                                <template x-if="wa_provider === 'meta'">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Phone Number ID</label>
                                        <input type="text" name="whatsapp_instance" x-model="wa_instance" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px] mb-2">
                                        <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Access Token</label>
                                        <input type="password" name="whatsapp_token" x-model="wa_token" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px] mb-2">
                                        <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Verify Token</label>
                                        <input type="text" name="whatsapp_verify_token" value="{{ $configuring->whatsapp_verify_token }}" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px]">
                                    </div>
                                </template>
                                <template x-if="wa_provider === 'zapi'">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">ID da Instância</label>
                                        <input type="text" name="whatsapp_instance" x-model="wa_instance" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px] mb-2">
                                        <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Token da Instância</label>
                                        <input type="password" name="whatsapp_token" x-model="wa_token" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px] mb-2">
                                        <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Client-Token</label>
                                        <input type="text" name="whatsapp_verify_token" value="{{ $configuring->whatsapp_verify_token }}" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px]">
                                    </div>
                                </template>
                                <template x-if="wa_provider === 'chatpro'">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Endpoint URL</label>
                                        <input type="url" name="whatsapp_url" x-model="wa_url" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px] mb-2">
                                        <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Token</label>
                                        <input type="password" name="whatsapp_token" x-model="wa_token" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px]">
                                    </div>
                                </template>
                            </div>

                            <div x-show="wa_provider !== ''" x-transition class="mt-auto border-t border-gray-100 pt-3 mt-4 space-y-3">
                                <button type="button" x-on:click="startWaConnection()" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 rounded-lg text-xs transition flex items-center justify-center gap-1.5 shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c0 .621.504 1.125 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c0 .621.504 1.125 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c0 .621.504 1.125 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z" /></svg>
                                    Conectar / QR Code
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL DE CAMPOS DE QUALIFICAÇÃO / LEAD -->
                    <div x-show="showLeadModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4" x-transition>
                        <div x-on:click.away="showLeadModal = false" class="bg-white rounded-xl shadow-2xl max-w-3xl w-full flex flex-col relative border border-slate-200 max-h-[85vh]">
                            
                            <div class="flex justify-between items-center border-b border-slate-100 p-5 shrink-0">
                                <div class="flex items-center gap-3">
                                    <span class="p-2.5 bg-indigo-50 text-indigo-600 rounded-lg">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                                    </span>
                                    <div>
                                        <h3 class="text-base font-bold text-slate-800">Campos de Qualificação (Variáveis)</h3>
                                        <p class="text-xs text-slate-500 font-medium mt-0.5">Defina os dados que a IA deve capturar para abrir chamados (InSoft Omni).</p>
                                    </div>
                                </div>
                                <button type="button" x-on:click="showLeadModal = false" class="text-slate-400 hover:text-slate-600 p-2 rounded-lg hover:bg-slate-100 transition">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>

                            <div class="p-5 overflow-y-auto bg-slate-50 flex-1">
                                <div class="space-y-3">
                                    <template x-for="(field, index) in leadFields" :key="index">
                                        <div class="bg-white p-3.5 rounded-lg border border-slate-200 shadow-sm flex items-start gap-3 relative group">
                                            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-[10px] uppercase tracking-wide font-bold text-slate-500 mb-1">Nome de Exibição (Label)</label>
                                                    <input type="text" x-model="field.label" :name="'lead_fields['+index+'][label]'" placeholder="Ex: Nome Completo" class="w-full border border-slate-200 rounded-md px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] uppercase tracking-wide font-bold text-slate-500 mb-1">Variável Interna (InSoft Omni)</label>
                                                    <input type="text" x-model="field.name" :name="'lead_fields['+index+'][name]'" placeholder="Ex: client_name" class="w-full border border-slate-200 bg-slate-50 rounded-md px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                                                </div>
                                            </div>
                                            <button type="button" @click="leadFields.splice(index, 1)" class="mt-5 p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-md transition" title="Remover Campo">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                            </button>
                                        </div>
                                    </template>

                                    <div x-show="leadFields.length === 0" class="text-center py-8 border-2 border-dashed border-slate-200 rounded-xl bg-white">
                                        <svg class="w-8 h-8 text-slate-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                        <p class="text-xs text-slate-500 font-medium">Nenhum campo dinâmico cadastrado.</p>
                                    </div>
                                </div>
                                
                                <button type="button" @click="leadFields.push({label: '', name: ''})" class="mt-4 w-full border-2 border-dashed border-indigo-200 text-indigo-600 hover:bg-indigo-50 hover:border-indigo-300 font-semibold py-3 rounded-xl text-xs transition flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                    Adicionar Novo Campo
                                </button>
                            </div>

                            <div class="flex justify-between items-center p-5 border-t border-slate-100 bg-white rounded-b-xl shrink-0">
                                <p class="text-[10px] text-slate-400 font-medium w-2/3 leading-tight">Dica: O campo "Variável Interna" deve ser o mesmo nome esperado pela API do InSoft Omni (sem espaços, ex: cpf_cliente).</p>
                                <button type="button" x-on:click="showLeadModal = false" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2.5 rounded-lg text-xs transition shadow-sm">
                                    Concluído
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL DIAGNÓSTICO DO WEBHOOK -->
                    <div x-show="showWebhookModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4" x-transition>
                        <div x-on:click.away="closeWebhookModal()" class="bg-white rounded-xl shadow-2xl max-w-2xl w-full p-6 relative border border-slate-200 space-y-5">
                            
                            <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.27 1.06-.12 1.451l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.27-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.149-.894z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </span>
                                    <h3 class="text-sm font-bold text-slate-800">Webhook & Diagnóstico do Sistema</h3>
                                </div>
                                <button type="button" x-on:click="closeWebhookModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">URL do Webhook do Assistente</label>
                                <div class="flex items-center gap-2">
                                    <input type="text" readonly id="modalWebhookUrl" value="{{ request()->schemeAndHttpHost() }}/webhook/whatsapp/{{ $configuring->id }}" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700 font-mono outline-none">
                                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('modalWebhookUrl').value); alert('URL copiada!');" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg text-xs transition shrink-0">Copiar</button>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                                        <span>Raio-X da Última Requisição</span>
                                    </label>
                                    <button type="button" onclick="window.location.href='/?configure={{ $configuring->id }}&modal=webhook'" class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                                        Atualizar
                                    </button>
                                </div>

                                <div class="bg-slate-900 text-slate-100 p-4 rounded-xl text-[11px] font-mono border border-slate-800 max-h-72 overflow-y-auto space-y-2">
                                    @if($lastWebhook)
                                        @php
                                            $whDate = \Carbon\Carbon::parse($lastWebhook['timestamp'] ?? now())->setTimezone($assistantTz ?? 'America/Sao_Paulo')->format('d/m/Y H:i:s');
                                        @endphp
                                        <div class="flex justify-between border-b border-slate-800 pb-2 mb-2">
                                            <span class="text-slate-400">Data/Hora: {{ $whDate }}</span>
                                            <span class="text-slate-400">Remetente: <strong class="text-slate-200">{{ $lastWebhook['sender'] ?? '-' }}</strong></span>
                                        </div>
                                        <p><span class="text-slate-400">Mensagem:</span> <span class="text-slate-100 font-sans font-medium">"{{ $lastWebhook['user_message'] ?? '-' }}"</span></p>
                                        
                                        @if(isset($lastWebhook['ai_reply']))
                                            <p class="truncate"><span class="text-slate-400">Resposta IA:</span> <span class="text-emerald-400 font-sans font-medium">{{ $lastWebhook['ai_reply'] }}</span></p>
                                        @endif

                                        <div class="pt-2 border-t border-slate-800 flex items-center justify-between mt-2">
                                            <span class="text-slate-400">Status do Disparo:</span>
                                            @if(($lastWebhook['wa_send_result']['success'] ?? false) === true)
                                                <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-2 py-0.5 rounded text-[10px] font-semibold">ENTREGUE</span>
                                            @else
                                                <span class="bg-rose-500/10 text-rose-400 border border-rose-500/20 px-2 py-0.5 rounded text-[10px] font-semibold">FALHOU</span>
                                            @endif
                                        </div>

                                        @if(isset($lastWebhook['error']))
                                            <div class="bg-rose-950/40 border border-rose-800/40 p-2 rounded text-rose-300 mt-2">
                                                Motivo: {{ $lastWebhook['error'] }}
                                            </div>
                                        @endif

                                        @if(isset($lastWebhook['wa_send_result']['error']))
                                            <div class="bg-rose-950/40 border border-rose-800/40 p-2 rounded text-rose-300 mt-2">
                                                Resposta API: {{ $lastWebhook['wa_send_result']['error'] }}
                                            </div>
                                        @endif

                                        @if(isset($lastWebhook['raw_snippet']))
                                            <div class="mt-3 pt-2 border-t border-slate-800 text-[10px]">
                                                <span class="text-slate-500 block mb-1">Payload Recebido:</span>
                                                <div class="bg-slate-950 p-2 rounded text-slate-400 font-mono break-all text-[9px]">
                                                    {{ $lastWebhook['raw_snippet'] }}
                                                </div>
                                            </div>
                                        @endif
                                    @else
                                        <p class="text-slate-500 italic py-4 text-center font-sans">Nenhum evento registrado. Mande uma mensagem para gerar o diagnóstico.</p>
                                    @endif
                                </div>
                            </div>

                            <div class="flex justify-end pt-2 border-t border-slate-100">
                                <button type="button" x-on:click="closeWebhookModal()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium px-4 py-2 rounded-lg text-xs transition">
                                    Fechar
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- MODAL DO QR CODE -->
                    <div x-show="showWaModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4" x-transition>
                        <div x-on:click.away="showWaModal = false" class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 text-center relative border border-gray-100">
                            
                            <button type="button" x-on:click="showWaModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition p-1 rounded-lg">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>

                            <h3 class="text-lg font-bold text-gray-800 mb-1 flex items-center justify-center gap-2">
                                <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" /></svg>
                                Conexão WhatsApp
                            </h3>
                            <p class="text-xs text-gray-500 mb-6">{{ $configuring->name }}</p>

                            <div x-show="waLoading && !waResult" class="py-8 space-y-3">
                                <div class="inline-block animate-spin rounded-full h-10 w-10 border-4 border-emerald-500 border-t-transparent"></div>
                                <p class="text-sm font-semibold text-gray-600">Acessando API...</p>
                            </div>

                            <div x-show="waResult !== null">
                                <template x-if="waResult?.connected">
                                    <div class="py-6 space-y-3">
                                        <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto">
                                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                        </div>
                                        <h4 class="text-base font-bold text-gray-800">WhatsApp Conectado!</h4>
                                        <p class="text-xs text-gray-500" x-text="waResult?.message"></p>
                                    </div>
                                </template>

                                <template x-if="waResult?.qr && !waResult?.connected">
                                    <div class="space-y-4">
                                        <p class="text-xs text-gray-600 font-medium" x-text="waResult?.message"></p>
                                        <div class="bg-gray-50 p-4 rounded-xl inline-block border border-gray-200 shadow-inner">
                                            <img :src="waResult.qr" alt="QR Code" class="w-56 h-56 object-contain mx-auto rounded-lg">
                                        </div>
                                        <p class="text-[11px] text-gray-400">1. Abra o WhatsApp no celular<br>2. Toque em <b>Aparelhos Conectados</b> > <b>Conectar um Aparelho</b></p>
                                        
                                        <div class="text-[10px] text-gray-400 mt-2 flex items-center justify-center gap-1.5 bg-gray-50 py-1.5 rounded border border-gray-100">
                                            <span class="inline-block animate-spin rounded-full h-3 w-3 border-2 border-emerald-500 border-t-transparent"></span>
                                            Aguardando leitura... (Tentativa <span x-text="pollAttempts"></span>/20)
                                        </div>
                                    </div>
                                </template>

                                <template x-if="!waResult?.qr && !waResult?.connected && waResult?.success">
                                    <div class="py-6 space-y-3">
                                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-3 border-emerald-500 border-t-transparent"></div>
                                        <p class="text-xs text-gray-600 font-semibold">Instância acordou! Obtendo imagem do QR Code...</p>
                                        <p class="text-[11px] text-gray-400" x-text="`Tentativa ${pollAttempts} de 20 (Aguarde 3s...)`"></p>
                                    </div>
                                </template>

                                <template x-if="!waResult?.success">
                                    <div class="py-4 space-y-2">
                                        <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                                        </div>
                                        <p class="text-xs font-semibold text-red-600" x-text="waResult?.message"></p>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-6 pt-4 border-t border-gray-100 flex gap-2">
                                <button type="button" x-on:click="runWaPoll()" :disabled="waLoading" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 rounded-lg text-xs transition">
                                    Atualizar / Tentar Novamente
                                </button>
                                <button type="button" x-on:click="showWaModal = false" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-2 rounded-lg text-xs transition">
                                    Fechar
                                </button>
                            </div>

                        </div>
                    </div>

                </form>

                @if($configuring->knowledge_files && count($configuring->knowledge_files) > 0)
                    @foreach($configuring->knowledge_files as $index => $file)
                        <form id="deleteFileForm_{{ $index }}" action="/" method="POST" class="hidden">
                            @csrf @method('DELETE')
                            <input type="hidden" name="assistant_id" value="{{ $configuring->id }}">
                            <input type="hidden" name="file_index" value="{{ $index }}">
                        </form>
                    @endforeach
                    <form id="bulkDeleteForm" action="/" method="POST" class="hidden"></form>
                @endif

            @else
                <div x-data="{
                    view: localStorage.getItem('assistant_view') || 'card',
                    filter: localStorage.getItem('assistant_filter') || 'active',
                    total: {{ $assistants->count() }},
                    activeCount: {{ $assistants->where('is_active', true)->count() }},
                    inactiveCount: {{ $assistants->where('is_active', false)->count() }},
                    get currentCount() {
                        if (this.filter === 'active') return this.activeCount;
                        if (this.filter === 'inactive') return this.inactiveCount;
                        return this.total;
                    }
                }" x-init="
                    $watch('filter', value => localStorage.setItem('assistant_filter', value));
                    $watch('view', value => localStorage.setItem('assistant_view', value));
                " class="flex flex-col h-[calc(100vh-8rem)]">
                <div class="mb-3 shrink-0">
                    <h1 class="text-xl font-bold text-gray-800">Gestão de Assistentes IA</h1>
                    <p class="text-xs text-gray-500 mt-1">Crie, configure e monitore seus assistentes virtuais multimodais integrados ao WhatsApp e canais de atendimento.</p>
                </div>

                <div class="bg-white p-3 rounded-xl shadow-sm border border-gray-200 mb-3 flex flex-col md:flex-row justify-between items-center gap-4 shrink-0">
                    <form action="/" method="POST" class="flex items-center gap-2 w-full md:w-auto flex-1 max-w-lg">
                        @csrf
                        <input type="text" name="name" placeholder="Nome do assistente (ex: Vânia - Vendas)" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-5 rounded-lg transition shadow-sm flex items-center gap-1.5 whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg> Criar
                        </button>
                    </form>
                </div>

                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-3 shrink-0">
                    <h2 class="text-base font-bold text-gray-700">Assistentes Cadastrados (<span x-text="currentCount"></span>/<span x-text="total"></span>)</h2>
                    
                    <div class="flex items-center gap-3">
                        <div class="flex items-center bg-gray-200/80 p-0.5 rounded-lg border border-gray-200">
                            <button type="button" @click="view = 'card'" :class="view === 'card' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'" class="px-2.5 py-1 rounded-md text-xs font-bold transition flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                                Cards
                            </button>
                            <button type="button" @click="view = 'list'" :class="view === 'list' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'" class="px-2.5 py-1 rounded-md text-xs font-bold transition flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M3.75 4.5h16.5" /></svg>
                                Lista
                            </button>
                        </div>

                        <div class="flex items-center gap-2 bg-white px-3 py-1.5 rounded-lg border border-gray-200 shadow-sm">
                            <label class="text-xs text-gray-500 font-medium">Status:</label>
                            <select x-model="filter" class="text-xs font-bold text-gray-700 bg-transparent focus:outline-none cursor-pointer">
                                <option value="active">🟢 Ativos</option>
                                <option value="inactive">⚪ Inativos</option>
                                <option value="all">Todos</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="border border-gray-200 rounded-xl bg-gray-50/40 p-4 flex-1 min-h-0 overflow-y-auto custom-scroll">
                <div x-show="view === 'card'" x-transition class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @forelse($assistants as $assistant)
                        <div x-show="(filter === 'all') || (filter === 'active' && {{ $assistant->is_active ? 'true' : 'false' }}) || (filter === 'inactive' && {{ !$assistant->is_active ? 'true' : 'false' }})"
                            class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:border-indigo-300 hover:shadow-md transition duration-200 flex flex-col justify-between gap-4">
                            
                            <div class="flex justify-between items-start gap-2">
                                <div class="flex items-center gap-1.5 truncate">
                                    <h3 class="font-bold text-gray-800 truncate text-lg">{{ $assistant->name }}</h3>
                                    
                                    <a href="/?configure={{ $assistant->id }}" title="Configurar" onclick="sessionStorage.removeItem('scrollpos_config_{{ $assistant->id }}');" class="text-gray-500 hover:text-indigo-600 bg-gray-50 hover:bg-indigo-50 p-1.5 rounded-full transition border border-gray-200 hover:border-indigo-200 flex items-center justify-center shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" /></svg>
                                    </a>

                                    <button type="button" title="Testar Chat Público" onclick="openChatPopup({{ $assistant->id }})" class="text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 p-1.5 rounded-full transition border border-indigo-100 flex items-center justify-center shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.522 1.522 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
                                    </button>

                                    <button type="button" title="Copiar link do chat" onclick="copyChatLink({{ $assistant->id }})" class="text-gray-400 hover:text-indigo-600 p-1.5 rounded-full transition hover:bg-gray-100 shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 011.927-.184" /></svg>
                                    </button>
                                </div>

                                <form action="/" method="POST">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">
                                    <button type="submit" class="text-xs px-3 py-1 rounded-full font-semibold transition border flex items-center gap-1.5 shrink-0 {{ $assistant->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-600 border-gray-300' }}">
                                        @if($assistant->is_active) <svg class="w-2 h-2 fill-emerald-500" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg> Ativo @else <svg class="w-2 h-2 fill-gray-400" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg> Inativo @endif
                                    </button>
                                </form>
                            </div>
                            
                            <div class="flex items-center justify-between border-t border-gray-100 pt-5 gap-2 mt-2">
                                <a href="/?conversations_id={{ $assistant->id }}" class="bg-white border border-gray-200 hover:border-indigo-300 hover:text-indigo-700 text-gray-600 font-bold py-2 px-2 rounded-lg text-xs transition flex-1 flex items-center justify-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-indigo-500"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.573 16.49 16.638 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    Conversas
                                </a>
                                
                                <a href="/?view=agenda&assistant_id={{ $assistant->id }}" class="bg-white border border-gray-200 hover:border-indigo-300 hover:text-indigo-700 text-gray-600 font-bold py-2 px-2 rounded-lg text-xs transition flex-1 flex items-center justify-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008z" /></svg>
                                    Agenda
                                </a>

                                <form action="/" method="POST" onsubmit="return confirm('Tem certeza?');">
                                    @csrf @method('DELETE')
                                    <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">
                                    <button type="submit" class="text-gray-400 hover:text-red-500 hover:bg-red-50 p-2 rounded-lg transition text-xs flex items-center justify-center" title="Excluir">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full text-center py-12 text-gray-400">Nenhum assistente cadastrado.</div>
                    @endforelse
                </div>
                
                <div x-show="view === 'list'" x-transition class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider">
                                <th class="py-4 px-5 font-semibold">Nome do Assistente</th>
                                <th class="py-4 px-5 font-semibold">Status</th>
                                <th class="py-4 px-5 font-semibold text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($assistants as $assistant)
                                <tr x-show="(filter === 'all') || (filter === 'active' && {{ $assistant->is_active ? 'true' : 'false' }}) || (filter === 'inactive' && {{ !$assistant->is_active ? 'true' : 'false' }})"
                                    class="hover:bg-gray-50 transition duration-150 group">
                                    
                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-gray-800 text-base">{{ $assistant->name }}</span>
                                            
                                            <a href="/?configure={{ $assistant->id }}" title="Configurar" onclick="sessionStorage.removeItem('scrollpos_config_{{ $assistant->id }}');" class="text-gray-500 hover:text-indigo-600 bg-gray-50 hover:bg-indigo-50 p-1.5 rounded-full transition border border-gray-200 hover:border-indigo-200 flex items-center justify-center shrink-0">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" /></svg>
                                            </a>

                                            <button type="button" title="Testar Chat Público" onclick="openChatPopup({{ $assistant->id }})" class="text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 p-1.5 rounded-full transition border border-indigo-100 flex items-center justify-center shrink-0">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.522 1.522 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
                                            </button>

                                            <button type="button" title="Copiar link do chat" onclick="copyChatLink({{ $assistant->id }})" class="text-gray-400 hover:text-indigo-600 p-1.5 rounded-full transition hover:bg-gray-100 shrink-0">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 011.927-.184" /></svg>
                                            </button>
                                        </div>
                                    </td>

                                    <td class="py-4 px-5">
                                        <form action="/" method="POST">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">
                                            <button type="submit" class="text-xs px-3 py-1 rounded-full font-semibold transition border flex items-center gap-1.5 w-max {{ $assistant->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-600 border-gray-300' }}">
                                                @if($assistant->is_active) <svg class="w-1.5 h-1.5 fill-emerald-500" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg> Ativo @else <svg class="w-1.5 h-1.5 fill-gray-400" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg> Inativo @endif
                                            </button>
                                        </form>
                                    </td>

                                    <td class="py-4 px-5 text-right flex justify-end items-center gap-2 opacity-90 group-hover:opacity-100 transition">
                                        <a href="/?conversations_id={{ $assistant->id }}" class="bg-white border border-gray-200 hover:border-indigo-300 hover:text-indigo-700 text-gray-600 font-bold py-1.5 px-3 rounded-lg text-xs transition flex items-center justify-center gap-1.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-indigo-500"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.573 16.49 16.638 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                            Conversas
                                        </a>

                                        <a href="/?view=agenda&assistant_id={{ $assistant->id }}" class="bg-white border border-gray-200 hover:border-indigo-300 hover:text-indigo-700 text-gray-600 font-bold py-1.5 px-3 rounded-lg text-xs transition flex items-center justify-center gap-1.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008z" /></svg> Agenda
                                        </a>
                                        
                                        <form action="/" method="POST" onsubmit="return confirm('Tem certeza?');">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">
                                            <button type="submit" class="text-gray-400 hover:text-red-500 hover:bg-red-50 p-1.5 rounded-md transition text-xs flex items-center justify-center" title="Excluir">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center py-8 text-gray-400">Nenhum assistente cadastrado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                </div>
                </div>
            @endif

            @if(isset($conversationsAssistant) && $conversationsAssistant)
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4">
                    <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full flex flex-col relative border border-slate-200 h-[85vh] overflow-hidden">
                        
                        <div class="flex justify-between items-center border-b border-slate-100 p-4 px-6 bg-slate-50 shrink-0">
                            <div class="flex items-center gap-3">
                                <span class="p-2 bg-indigo-100 text-indigo-600 rounded-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.573 16.49 16.638 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                </span>
                                <div>
                                    <h3 class="text-base font-bold text-slate-800">Conversas & Histórico — {{ $conversationsAssistant->name }}</h3>
                                    <p class="text-xs text-slate-500 font-medium">Veja os atendimentos e interações capturados pelo WhatsApp.</p>
                                </div>
                            </div>
                            <a href="/" class="text-slate-400 hover:text-slate-600 p-2 rounded-lg hover:bg-slate-200 transition">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </a>
                        </div>

                        <div class="flex flex-1 overflow-hidden">
                            
                            <div class="w-1/3 border-r border-slate-200 bg-slate-50/50 overflow-y-auto">
                                <div class="p-3 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                                    Atendimentos ({{ count($conversationThreads ?? []) }})
                                </div>
                                <div class="divide-y divide-slate-100">
                                    @forelse($conversationThreads ?? [] as $thread)
                                        @php
                                            $threadDateObj = \Carbon\Carbon::parse($thread->last_activity)->setTimezone($assistantTz ?? 'America/Sao_Paulo')->locale('pt_BR');
                                            $threadDisplayDate = $threadDateObj->format('d/m/Y H:i');
                                        @endphp
                                        <a href="/?conversations_id={{ $conversationsAssistant->id }}&phone={{ $thread->phone_number }}" 
                                            class="p-4 block transition hover:bg-white flex items-center justify-between {{ ($activePhone ?? '') === $thread->phone_number ? 'bg-white border-l-4 border-indigo-600 shadow-sm' : '' }}">
                                            <div>
                                                <p class="font-bold text-slate-800 text-sm flex items-center gap-1.5">
                                                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12.012 2c-5.508 0-9.988 4.479-9.988 9.988 0 2.228.734 4.285 1.973 5.947l-1.997 5.992 6.136-1.954c1.558.91 3.376 1.42 5.302 1.42 5.508 0 9.988-4.479 9.988-9.988s-4.48-9.988-9.988-9.988z"/></svg>
                                                    +{{ $thread->phone_number }}
                                                </p>
                                                <span class="text-[10px] text-slate-400 font-medium block mt-1">Última fala: {{ $threadDisplayDate }}</span>
                                            </div>
                                            <span class="text-xs bg-slate-100 text-slate-600 px-2 py-1 rounded-full font-bold border border-slate-200">{{ $thread->total_messages }} msgs</span>
                                        </a>
                                    @empty
                                        <div class="p-8 text-center text-xs text-slate-400">Nenhuma conversa gravada no banco.</div>
                                    @endforelse
                                </div>
                            </div>

                            <div class="w-2/3 bg-slate-100 flex flex-col justify-between overflow-hidden">
                                @if(isset($activePhone) && $activePhone && isset($activeThreadMessages) && count($activeThreadMessages) > 0)
                                    <div class="p-3 bg-white border-b border-slate-200 flex justify-between items-center text-xs font-semibold text-slate-700 shrink-0 shadow-sm z-10 relative">
                                        <span>Conversa com <strong>+{{ $activePhone }}</strong></span>
                                        <span class="text-slate-400 text-[11px]">{{ count($activeThreadMessages) }} interações</span>
                                    </div>

                                    <div x-data x-ref="chatBox" x-init="$nextTick(() => { $refs.chatBox.scrollTop = $refs.chatBox.scrollHeight; })" class="p-4 overflow-y-auto space-y-3 flex-1 scroll-smooth">
                                        
                                        @php $lastDate = null; @endphp

                                        @foreach($activeThreadMessages as $msg)
                                            @php
                                                $carbonDate = \Carbon\Carbon::parse($msg->created_at)->setTimezone($assistantTz ?? 'America/Sao_Paulo')->locale('pt_BR');
                                                $currentDate = $carbonDate->format('Y-m-d');
                                                $displayDate = ucfirst($carbonDate->translatedFormat('l, d \d\e F \d\e Y'));
                                                $displayTime = $carbonDate->format('H:i:s');
                                            @endphp

                                            @if($currentDate !== $lastDate)
                                                <div class="flex justify-center my-5">
                                                    <span class="bg-slate-200 text-slate-600 text-[10px] font-bold tracking-wide px-3 py-1 rounded-lg border border-slate-300 shadow-sm">
                                                        {{ $displayDate }}
                                                    </span>
                                                </div>
                                                @php $lastDate = $currentDate; @endphp
                                            @endif

                                            @if($msg->role === 'user')
                                                <div class="flex justify-end">
                                                    <div class="bg-indigo-600 text-white rounded-2xl rounded-tr-none p-3 max-w-md text-xs shadow-sm relative group">
                                                        <p class="whitespace-pre-line leading-relaxed">{{ $msg->content }}</p>
                                                        <span class="text-[9.5px] text-indigo-200 block text-right mt-1.5 font-medium tracking-wide">{{ $displayTime }}</span>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="flex justify-start">
                                                    <div class="bg-white text-slate-800 border border-slate-200 rounded-2xl rounded-tl-none p-3 max-w-md text-xs shadow-sm relative group">
                                                        <p class="whitespace-pre-line leading-relaxed">{{ $msg->content }}</p>
                                                        <span class="text-[9.5px] text-slate-400 block text-left mt-1.5 font-medium tracking-wide">{{ $displayTime }}</span>
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach

                                    </div>
                                @else
                                    <div class="flex-1 flex flex-col items-center justify-center text-slate-400 text-xs p-8">
                                        <svg class="w-12 h-12 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" /></svg>
                                        Selecione um contato na lista à esquerda para carregar o histórico.
                                    </div>
                                @endif

                            </div>
                        </div>

                        <div class="p-3 border-t border-slate-100 bg-white flex justify-end shrink-0 z-10 relative">
                            <a href="/" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium px-5 py-2 rounded-lg text-xs transition">
                                Fechar
                            </a>
                        </div>
                    </div>
                </div>
            @endif

        </div>
@endsection