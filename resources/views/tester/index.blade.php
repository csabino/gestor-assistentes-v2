@extends('layouts.app')

@section('title', 'Validador')

@section('content')
        <div class="container mx-auto px-6 max-w-7xl flex flex-col h-[calc(100vh-8rem)] pt-4"
             x-data="{
                 targets: @js($targets->map(fn($t) => ['id' => $t->id, 'name' => $t->name, 'system_prompt' => $t->system_prompt, 'knowledge_files' => $t->knowledge_files ?? []])),
                 target_assistant_id: '',
                 target_label: '',
                 target_phone_number: '',
                 target_prompt_snapshot: '',
                 target_knowledge_snapshot: '',
                 uploadingKb: false,
                 showNewTestModal: false,
                 showPromptModal: false,
                 showKbModal: false,
                 showModal: false,
                 modalData: {},
                 showConnectionModal: false,
                 showWaModal: false,
                 wa_provider: @js($harnessAssistant->whatsapp_provider ?? ''),
                 wa_url: @js($harnessAssistant->whatsapp_url ?? ''),
                 wa_instance: @js($harnessAssistant->whatsapp_instance ?? ''),
                 wa_token: @js($harnessAssistant->whatsapp_token ?? ''),
                 ai_provider: @js($harnessAssistant->provider ?? 'openai'),
                 ai_model: @js($harnessAssistant->model ?? 'gpt-4o-mini'),
                 ai_api_key: '',
                 waStatus: 'checking',
                 waSaving: false,
                 waLoading: false,
                 waResult: null,
                 pollAttempts: 0,
                 getWaParams() {
                     return { assistant_id: {{ $harnessAssistant->id ?? 'null' }}, url: this.wa_url, instance: this.wa_instance, token: this.wa_token, provider: this.wa_provider };
                 },
                 async checkWaStatusSilent() {
                     if (!this.wa_provider || !this.wa_url || !this.wa_token) { this.waStatus = 'disconnected'; return; }
                     this.waStatus = 'checking';
                     try {
                         const res = await fetch('/', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ action: 'status_whatsapp', ...this.getWaParams() }) });
                         const data = await res.json();
                         this.waStatus = data.connected ? 'connected' : 'disconnected';
                     } catch (e) {
                         this.waStatus = 'disconnected';
                     }
                 },
                 async saveWaConnection() {
                     this.waSaving = true;
                     this.wa_provider = 'uazapi';
                     try {
                         const res = await fetch('/?view=tester', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({
                             action: 'save_harness_connection',
                             whatsapp_provider: this.wa_provider, whatsapp_url: this.wa_url, whatsapp_instance: this.wa_instance, whatsapp_token: this.wa_token,
                             ai_provider: this.ai_provider, ai_model: this.ai_model, ai_api_key: this.ai_api_key,
                         }) });
                         const data = await res.json();
                         if (data.success) {
                             Alpine.store('toast').show('Conexão salva!', 'success');
                             this.ai_api_key = '';
                             this.checkWaStatusSilent();
                         } else {
                             Alpine.store('toast').show(data.message || 'Não foi possível salvar.', 'error');
                         }
                     } catch (e) {
                         Alpine.store('toast').show('Erro de conexão.', 'error');
                     } finally {
                         this.waSaving = false;
                     }
                 },
                 async disconnectWa() {
                     if (!(await confirmModal('Tem certeza que deseja desconectar a sessão do WhatsApp?'))) return;
                     this.waStatus = 'checking';
                     try {
                         const res = await fetch('/', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ action: 'disconnect_whatsapp', ...this.getWaParams() }) });
                         const data = await res.json();
                         if (data.success) {
                             this.waStatus = 'disconnected';
                         } else {
                             alertModal('Não foi possível desconectar: ' + (data.message || 'Erro desconhecido.'));
                             this.checkWaStatusSilent();
                         }
                     } catch (e) {
                         alertModal('Erro na requisição.');
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
                     const hasQr = !!(this.waResult && this.waResult.qr);
                     try {
                         const res = await fetch('/', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ action: 'test_whatsapp', has_qr: hasQr, ...this.getWaParams() }) });
                         const data = await res.json();
                         if (hasQr && !data.connected) data.qr = this.waResult.qr;
                         this.waResult = data;
                         if (data.connected) {
                             this.waStatus = 'connected';
                         } else if (data.success && this.pollAttempts < 20 && this.showWaModal) {
                             this.pollAttempts++;
                             setTimeout(() => { if (this.showWaModal) this.runWaPoll(); }, 3000);
                         }
                     } catch (e) {
                     } finally {
                         this.waLoading = false;
                     }
                 },
                 selectTarget() {
                     const t = this.targets.find(x => x.id == this.target_assistant_id);
                     if (!t) return;
                     this.target_label = t.name;
                     this.target_prompt_snapshot = t.system_prompt || '';
                     this.target_knowledge_snapshot = (t.knowledge_files || [])
                         .map(f => '## ' + (f.name || 'Documento') + '\n' + (f.content || ''))
                         .join('\n\n');
                 },
                 async uploadKbFile(event) {
                     const file = event.target.files[0];
                     if (!file) return;
                     this.uploadingKb = true;
                     const fd = new FormData();
                     fd.append('file', file);
                     fd.append('action', 'upload_test_kb_file');
                     try {
                         const res = await fetch('/?view=tester', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: fd });
                         const data = await res.json();
                         if (data.success) {
                             this.target_knowledge_snapshot += (this.target_knowledge_snapshot ? '\n\n' : '') + '## ' + data.name + '\n' + data.text;
                         } else {
                             Alpine.store('toast').show(data.message || 'Não foi possível ler o arquivo.', 'error');
                         }
                     } catch (e) {
                         Alpine.store('toast').show('Erro de conexão ao enviar o arquivo.', 'error');
                     } finally {
                         this.uploadingKb = false;
                         event.target.value = '';
                     }
                 },
                 pollTimer: null,
                 async openRun(id) {
                     const res = await fetch('/?view=tester&action=get_test_run_detail&test_run_id=' + id);
                     this.modalData = await res.json();
                     this.showModal = true;
                     this.maybePoll();
                 },
                 maybePoll() {
                     if (this.pollTimer) { clearTimeout(this.pollTimer); this.pollTimer = null; }
                     if (this.showModal && this.modalData.status === 'running') {
                         this.pollTimer = setTimeout(async () => {
                             const res = await fetch('/?view=tester&action=get_test_run_detail&test_run_id=' + this.modalData.id);
                             this.modalData = await res.json();
                             this.maybePoll();
                         }, 5000);
                     }
                 },
                 closeDetailModal() {
                     this.showModal = false;
                     if (this.pollTimer) { clearTimeout(this.pollTimer); this.pollTimer = null; }
                 },
                 scenarioGroups() {
                     if (!this.modalData.scenarios) return [];
                     return this.modalData.scenarios.map((s, i) => ({
                         ...s,
                         number: i + 1,
                         current: i === Number(this.modalData.current_scenario_index),
                         messages: (this.modalData.transcript || []).filter(t => Number(t.scenario_index) === i),
                     }));
                 }
             }"
             x-init="
                 @if($harnessAssistant) checkWaStatusSilent(); @endif
                 @if(session('new_test_run_id')) openRun({{ session('new_test_run_id') }}); @endif
             ">
            <div class="mb-3 shrink-0">
                <h1 class="text-xl font-bold text-gray-800 flex items-center gap-1.5">
                    Validador
                    <svg class="w-4 h-4 text-gray-400 cursor-help shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <title>QA automatizado: conversa de verdade via WhatsApp entre um número de teste e o assistente que você quiser avaliar, com cenários positivos e negativos gerados por IA.</title>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                </h1>
            </div>

            <div class="flex flex-col lg:flex-row items-stretch gap-6 flex-1 min-h-0">
                <!-- COLUNA ESQUERDA: configuração e disparo -->
                <div class="w-full lg:w-[380px] shrink-0 flex flex-col gap-4 min-h-0">
                    <!-- Número de teste -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 shrink-0">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <h2 class="text-sm font-bold text-gray-800 flex items-center gap-1.5">
                                Número de teste
                                <svg class="w-3.5 h-3.5 text-gray-400 cursor-help shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <title>É um assistente normal nos bastidores, dedicado só pro módulo Validador - conecte o WhatsApp dele e configure a chave de IA aqui. Ele nunca atende cliente de verdade, só conversa com o assistente que você quiser avaliar durante um teste.</title>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                </svg>
                            </h2>
                            @if($harnessAssistant)
                                <button type="button" @click="showConnectionModal = true" class="shrink-0 text-indigo-600 hover:text-indigo-800 text-[11px] font-semibold px-2 py-1 rounded-md border border-indigo-200 hover:border-indigo-300 transition">Conectar</button>
                            @endif
                        </div>
                        @if(!$harnessAssistant)
                            <p class="text-xs text-gray-500 mb-3">Nenhum número de teste criado ainda - ele é o "cliente" que vai conversar de verdade com o assistente sendo avaliado.</p>
                            <form action="/?view=tester" method="POST">
                                @csrf
                                <input type="hidden" name="action" value="store_test_harness_assistant">
                                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 rounded-lg text-xs transition">Criar número de teste</button>
                            </form>
                        @else
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <span class="text-gray-600">{{ $harnessAssistant->name }}</span>
                                <span class="flex items-center gap-1">
                                    <span x-show="waStatus === 'checking'" class="px-2 py-0.5 rounded-full font-bold bg-gray-100 text-gray-500 border border-gray-200 animate-pulse">Verificando...</span>
                                    <span x-show="waStatus === 'connected'" class="px-2 py-0.5 rounded-full font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Conectado</span>
                                    <span x-show="waStatus === 'disconnected'" class="px-2 py-0.5 rounded-full font-bold bg-amber-50 text-amber-700 border border-amber-200">Não conectado</span>
                                </span>
                            </div>
                            @php $providerKeyField = ($harnessAssistant->provider ?? 'openai') . '_api_key'; @endphp
                            <div class="flex items-center justify-between gap-2 text-xs mt-1.5">
                                <span class="text-gray-600">Chave de IA ({{ $harnessAssistant->provider ?? 'openai' }})</span>
                                @if(!empty($harnessAssistant->{$providerKeyField}))
                                    <span class="px-2 py-0.5 rounded-full font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Configurada</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full font-bold bg-amber-50 text-amber-700 border border-amber-200">Faltando</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Novo teste -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex-1">
                        <h2 class="text-sm font-bold text-gray-800 mb-3">Novo teste</h2>
                        <form action="/?view=tester" method="POST" @submit="if (!target_label || !target_phone_number || !target_prompt_snapshot) { alertModal('Preencha o nome do agente, o número do WhatsApp (em \'Novo teste\') e o Prompt antes de iniciar o teste.'); $event.preventDefault(); }">
                            @csrf
                            <input type="hidden" name="action" value="start_test_run">
                            <input type="hidden" name="target_label" :value="target_label">
                            <input type="hidden" name="target_phone_number" :value="target_phone_number">
                            <input type="hidden" name="target_prompt_snapshot" :value="target_prompt_snapshot">
                            <input type="hidden" name="target_knowledge_snapshot" :value="target_knowledge_snapshot">

                            <div class="space-y-2">
                                <button type="button" @click="showNewTestModal = true" class="w-full flex items-center justify-between gap-2 border border-gray-200 hover:border-indigo-300 rounded-lg px-3 py-2 text-xs transition text-left">
                                    <span class="font-semibold text-gray-700">Novo teste</span>
                                    <span class="text-gray-400 truncate max-w-[160px]" x-text="target_label ? (target_label + ' · ' + target_phone_number) : 'Alvo e número ainda não definidos'"></span>
                                </button>
                                <button type="button" @click="showPromptModal = true" class="w-full flex items-center justify-between gap-2 border border-gray-200 hover:border-indigo-300 rounded-lg px-3 py-2 text-xs transition text-left">
                                    <span class="font-semibold text-gray-700">Prompt</span>
                                    <span class="text-gray-400" x-text="target_prompt_snapshot ? (target_prompt_snapshot.length + ' caracteres') : 'Vazio'"></span>
                                </button>
                                <button type="button" @click="showKbModal = true" class="w-full flex items-center justify-between gap-2 border border-gray-200 hover:border-indigo-300 rounded-lg px-3 py-2 text-xs transition text-left">
                                    <span class="font-semibold text-gray-700">Base de Conhecimento</span>
                                    <span class="text-gray-400" x-text="target_knowledge_snapshot ? (target_knowledge_snapshot.length + ' caracteres') : 'Vazia'"></span>
                                </button>
                            </div>

                            <div class="flex items-center gap-3 mt-3">
                                <button type="submit" class="w-1/3 shrink-0 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-lg text-xs transition">Iniciar Teste</button>
                                <p class="text-[10px] text-gray-400 leading-tight">Até 8 cenários (positivos e negativos) de até 12 mensagens cada, conversando de verdade pelo WhatsApp - pode levar um bom tempo. Acompanhe pelo grid ao lado.</p>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- COLUNA DIREITA: histórico de testes -->
                <div class="flex-1 min-w-0 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col min-h-0">
                    <div class="flex-1 min-h-0 overflow-y-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead class="sticky top-0 z-10">
                                <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider">
                                    <th class="py-3 px-4 font-semibold">Teste</th>
                                    <th class="py-3 px-4 font-semibold">Alvo</th>
                                    <th class="py-3 px-4 font-semibold">Status</th>
                                    <th class="py-3 px-4 font-semibold">Data</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($testRuns as $run)
                                    <tr @click="openRun({{ $run->id }})" class="hover:bg-gray-50 cursor-pointer transition">
                                        <td class="py-2.5 px-4 font-bold text-gray-800">{{ $run->name }}</td>
                                        <td class="py-2.5 px-4 text-gray-600">{{ $run->target_label }}</td>
                                        <td class="py-2.5 px-4">
                                            <span @class([
                                                'px-2 py-0.5 rounded-full text-[10px] font-bold',
                                                'bg-amber-50 text-amber-700 border border-amber-200' => in_array($run->status, ['draft', 'running']),
                                                'bg-emerald-50 text-emerald-700 border border-emerald-200' => $run->status === 'completed',
                                                'bg-red-50 text-red-700 border border-red-200' => in_array($run->status, ['error', 'stopped']),
                                            ])>{{ $run->status }}</span>
                                        </td>
                                        <td class="py-2.5 px-4 text-gray-500">{{ $run->created_at->format('d/m/Y H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center py-8 text-gray-400 text-xs">Nenhum teste rodado ainda.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- MODAL NOVO TESTE (alvo, nome e número) -->
            <div x-show="showNewTestModal" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div @click.away="showNewTestModal = false" class="bg-white rounded-xl shadow-2xl max-w-sm w-full flex flex-col relative border border-slate-200">
                    <div class="flex items-center justify-between p-5 border-b border-gray-100 shrink-0">
                        <h3 class="text-base font-bold text-gray-800">Novo teste</h3>
                        <button type="button" @click="showNewTestModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="p-5 space-y-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Pré-preencher a partir de um assistente</label>
                            <select x-model="target_assistant_id" @change="selectTarget()" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs bg-white">
                                <option value="">Selecione (opcional)...</option>
                                <template x-for="t in targets" :key="t.id">
                                    <option :value="t.id" x-text="t.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Nome do agente (pro nome do teste)</label>
                            <input type="text" x-model="target_label" placeholder="Ex: Ingrid" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Número do WhatsApp a testar</label>
                            <input type="text" x-model="target_phone_number" placeholder="Ex: 5511999999999" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-mono">
                        </div>
                    </div>
                    <div class="p-5 border-t border-gray-100 shrink-0">
                        <button type="button" @click="showNewTestModal = false" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 rounded-lg text-xs transition">Salvar</button>
                    </div>
                </div>
            </div>

            <!-- MODAL PROMPT -->
            <div x-show="showPromptModal" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div @click.away="showPromptModal = false" class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[85vh] flex flex-col relative border border-slate-200">
                    <div class="flex items-center justify-between p-5 border-b border-gray-100 shrink-0">
                        <h3 class="text-base font-bold text-gray-800">Prompt</h3>
                        <button type="button" @click="showPromptModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="flex-1 min-h-0 overflow-y-auto p-5">
                        <p class="text-[10px] text-gray-400 mb-1.5 leading-tight">Retrato editável - alterar aqui não afeta o assistente original.</p>
                        <textarea x-model="target_prompt_snapshot" rows="18" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-mono"></textarea>
                    </div>
                    <div class="p-5 border-t border-gray-100 shrink-0">
                        <button type="button" @click="showPromptModal = false" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 rounded-lg text-xs transition">Salvar</button>
                    </div>
                </div>
            </div>

            <!-- MODAL BASE DE CONHECIMENTO -->
            <div x-show="showKbModal" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div @click.away="showKbModal = false" class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[85vh] flex flex-col relative border border-slate-200">
                    <div class="flex items-center justify-between p-5 border-b border-gray-100 shrink-0">
                        <h3 class="text-base font-bold text-gray-800">Base de Conhecimento</h3>
                        <button type="button" @click="showKbModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="flex-1 min-h-0 overflow-y-auto p-5">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-[10px] text-gray-400 leading-tight">Retrato editável - alterar aqui não afeta o assistente original.</p>
                            <label class="text-[10px] text-indigo-600 hover:text-indigo-800 font-semibold cursor-pointer shrink-0 ml-2">
                                <span x-text="uploadingKb ? 'Enviando...' : '+ Anexar arquivo'"></span>
                                <input type="file" class="hidden" @change="uploadKbFile($event)" :disabled="uploadingKb">
                            </label>
                        </div>
                        <textarea x-model="target_knowledge_snapshot" rows="18" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-mono"></textarea>
                    </div>
                    <div class="p-5 border-t border-gray-100 shrink-0">
                        <button type="button" @click="showKbModal = false" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 rounded-lg text-xs transition">Salvar</button>
                    </div>
                </div>
            </div>

            <!-- MODAL CONECTAR NÚMERO DE TESTE -->
            <div x-show="showConnectionModal" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div @click.away="showConnectionModal = false" class="bg-white rounded-xl shadow-2xl max-w-sm w-full max-h-[85vh] flex flex-col relative border border-slate-200">
                    <div class="flex items-center justify-between p-5 border-b border-gray-100 shrink-0">
                        <h3 class="text-base font-bold text-gray-800">Conectar número de teste</h3>
                        <button type="button" @click="showConnectionModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="flex-1 min-h-0 overflow-y-auto p-5 space-y-4">
                        <div>
                            <div class="flex items-center gap-1.5 mb-2">
                                <span x-show="waStatus === 'checking'" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500 border border-gray-200 animate-pulse">Verificando...</span>
                                <span x-show="waStatus === 'connected'" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Conectado</span>
                                <span x-show="waStatus === 'disconnected'" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Não conectado</span>
                                <button type="button" x-show="waStatus === 'connected'" @click="disconnectWa()" class="text-[10px] text-red-600 hover:text-red-800 font-semibold ml-auto">Desconectar</button>
                            </div>
                            <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">URL (UazAPI)</label>
                            <input type="url" x-model="wa_url" placeholder="https://api.uazapi.dev" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px] mb-2">
                            <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Nome da Instância</label>
                            <input type="text" x-model="wa_instance" placeholder="Ex: teste" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px] mb-2">
                            <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Instance Token</label>
                            <input type="password" x-model="wa_token" placeholder="Ex: T0K3N..." class="w-full border border-gray-300 rounded-md p-1.5 text-[11px]">
                        </div>

                        @if($harnessAssistant)
                            <div class="border-t border-gray-100 pt-3">
                                <label class="block text-[11px] font-semibold text-gray-700 mb-1">URL do Webhook (cola na plataforma da UazAPI)</label>
                                <div class="flex items-center gap-1.5">
                                    <input type="text" readonly id="testerWebhookUrl" value="{{ request()->schemeAndHttpHost() }}/webhook/whatsapp/{{ $harnessAssistant->id }}" class="w-full bg-gray-50 border border-gray-300 rounded-md p-1.5 text-[10px] font-mono text-gray-600 outline-none">
                                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('testerWebhookUrl').value); Alpine.store('toast').show('URL copiada!', 'success');" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-1.5 px-3 rounded-md text-[11px] transition shrink-0">Copiar</button>
                                </div>
                            </div>
                        @endif

                        <div class="border-t border-gray-100 pt-3">
                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">IA usada pra conduzir os testes</label>
                            <select x-model="ai_provider" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px] bg-white mb-2">
                                <option value="openai">OpenAI</option>
                                <option value="gemini">Google Gemini</option>
                                <option value="anthropic">Anthropic</option>
                                <option value="grok">xAI Grok</option>
                            </select>
                            <input type="text" x-model="ai_model" placeholder="Ex: gpt-4o-mini" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px] mb-2">
                            <input type="password" x-model="ai_api_key" placeholder="Cole a chave de API aqui (deixe em branco pra manter a atual)" class="w-full border border-gray-300 rounded-md p-1.5 text-[11px]">
                        </div>
                    </div>
                    <div class="p-5 border-t border-gray-100 shrink-0 space-y-2">
                        <button type="button" @click="saveWaConnection()" :disabled="waSaving" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 rounded-lg text-xs transition">
                            <span x-text="waSaving ? 'Salvando...' : 'Salvar'"></span>
                        </button>
                        <button type="button" @click="startWaConnection()" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 rounded-lg text-xs transition flex items-center justify-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c0 .621.504 1.125 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c0 .621.504 1.125 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c0 .621.504 1.125 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z" /></svg>
                            Conectar / QR Code
                        </button>
                    </div>
                </div>
            </div>

            <!-- MODAL QR CODE -->
            <div x-show="showWaModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4" x-transition>
                <div x-on:click.away="showWaModal = false" class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 text-center relative border border-gray-100">
                    <button type="button" x-on:click.stop="showWaModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition p-1 rounded-lg">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                    <h3 class="text-lg font-bold text-gray-800 mb-1 flex items-center justify-center gap-2">
                        <svg class="w-5 h-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" /></svg>
                        Conexão WhatsApp
                    </h3>
                    <p class="text-xs text-gray-500 mb-6">Número de teste</p>

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
                                <p class="text-[11px] text-gray-400">1. Abra o WhatsApp no celular<br>2. Toque em <b>Aparelhos Conectados</b> &gt; <b>Conectar um Aparelho</b></p>
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
                        <button type="button" x-on:click.stop="showWaModal = false" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-2 rounded-lg text-xs transition">
                            Fechar
                        </button>
                    </div>
                </div>
            </div>

            <!-- MODAL DETALHE DO TESTE -->
            <div x-show="showModal" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div @click.away="closeDetailModal()" class="bg-white rounded-xl shadow-2xl max-w-3xl w-full max-h-[88vh] flex flex-col relative border border-slate-200">
                    <div class="flex items-center justify-between p-5 border-b border-gray-100 shrink-0">
                        <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                            <span x-text="modalData.name"></span>
                            <span x-show="modalData.status === 'running'" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 flex items-center gap-1">
                                <span class="inline-block animate-spin rounded-full h-2.5 w-2.5 border-2 border-amber-300 border-t-amber-600"></span>
                                Rodando
                            </span>
                            <span x-show="modalData.status === 'completed'" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Concluído</span>
                            <span x-show="modalData.status === 'error' || modalData.status === 'stopped'" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-700 border border-red-200" x-text="modalData.status"></span>
                        </h3>
                        <button type="button" @click="closeDetailModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="flex-1 min-h-0 overflow-y-auto p-5 space-y-5">
                        <template x-if="modalData.report">
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                                <p class="text-xs font-bold text-gray-700 mb-1">Resumo</p>
                                <p class="text-xs text-gray-600 mb-3" x-text="modalData.report.summary"></p>
                                <template x-for="(f, idx) in (modalData.report.findings || [])" :key="idx">
                                    <div class="border-t border-gray-200 pt-2 mt-2 first:border-t-0 first:pt-0 first:mt-0">
                                        <div class="flex items-center gap-1.5 flex-wrap mb-0.5">
                                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                                  :class="{
                                                      'bg-red-50 text-red-700 border border-red-200': f.severity === 'critico',
                                                      'bg-amber-50 text-amber-700 border border-amber-200': f.severity === 'alto',
                                                      'bg-yellow-50 text-yellow-700 border border-yellow-200': f.severity === 'medio',
                                                      'bg-gray-100 text-gray-500 border border-gray-200': f.severity === 'baixo',
                                                  }" x-text="f.severity"></span>
                                            <span class="text-[10px] text-gray-400" x-text="f.category"></span>
                                            <span class="text-[10px] text-gray-400" x-text="'· ' + f.scenario_title"></span>
                                        </div>
                                        <p class="text-xs text-gray-700" x-text="f.description"></p>
                                        <p class="text-[11px] text-gray-400 italic mt-0.5" x-text="'&quot;' + f.evidence_quote + '&quot;'"></p>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-for="scenario in scenarioGroups()" :key="scenario.number">
                            <div>
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200" x-text="'Cenário ' + scenario.number"></span>
                                    <span class="text-xs font-bold text-gray-700" x-text="scenario.title"></span>
                                    <span class="text-[10px] text-gray-400" x-text="'· ' + scenario.category"></span>
                                    <span x-show="scenario.current && modalData.status === 'running'" class="px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-amber-50 text-amber-700 border border-amber-200 animate-pulse">Em andamento</span>
                                </div>
                                <p class="text-[11px] text-gray-500 italic mb-1.5" x-text="scenario.objective"></p>
                                <div class="space-y-1.5">
                                    <template x-for="(msg, mIdx) in scenario.messages" :key="mIdx">
                                        <p class="text-[11px]"
                                           :class="msg.role === 'tester' ? 'text-right text-indigo-700' : (msg.role === 'target' ? 'text-left text-gray-700' : 'text-center text-gray-400 italic')"
                                           x-text="msg.content"></p>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
@endsection
