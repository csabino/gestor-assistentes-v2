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
                 async openRun(id) {
                     const res = await fetch('/?view=tester&action=get_test_run_detail&test_run_id=' + id);
                     this.modalData = await res.json();
                     this.showModal = true;
                 },
                 scenarioGroups() {
                     if (!this.modalData.scenarios) return [];
                     return this.modalData.scenarios.map((s, i) => ({
                         ...s,
                         messages: (this.modalData.transcript || []).filter(t => t.scenario_index === i),
                     }));
                 }
             }">
            <div class="mb-3 shrink-0">
                <h1 class="text-xl font-bold text-gray-800">Validador</h1>
                <p class="text-xs text-gray-500 mt-1">QA automatizado: conversa de verdade via WhatsApp entre um número de teste e o assistente que você quiser avaliar, com cenários positivos e negativos gerados por IA.</p>
            </div>

            @if(session('success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-lg px-4 py-2 mb-3 shrink-0">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-2 mb-3 shrink-0">{{ session('error') }}</div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-[380px_1fr] gap-6 flex-1 min-h-0">
                <!-- COLUNA ESQUERDA: configuração e disparo -->
                <div class="flex flex-col gap-4 min-h-0">
                    <!-- Número de teste -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 shrink-0">
                        <h2 class="text-sm font-bold text-gray-800 mb-2">Número de teste</h2>
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
                                @if($harnessAssistant->whatsapp_provider && $harnessAssistant->whatsapp_token)
                                    <span class="px-2 py-0.5 rounded-full font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Conectado ({{ $harnessAssistant->whatsapp_provider }})</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full font-bold bg-amber-50 text-amber-700 border border-amber-200">Não conectado</span>
                                @endif
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
                            <a href="/?configure={{ $harnessAssistant->id }}" class="mt-3 w-full text-indigo-600 hover:text-indigo-800 text-xs font-semibold flex items-center justify-center gap-1 border border-indigo-200 hover:border-indigo-300 rounded-lg py-2 transition">
                                Gerenciar conexão e chave de IA
                            </a>
                        @endif
                    </div>

                    <!-- Novo teste -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 shrink-0">
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

                            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-lg text-xs transition mt-3">Iniciar Teste</button>
                            <p class="text-[10px] text-gray-400 leading-tight mt-2">Até 8 cenários (positivos e negativos) de até 12 mensagens cada, conversando de verdade pelo WhatsApp - pode levar um bom tempo. Acompanhe pelo grid ao lado.</p>
                        </form>
                    </div>
                </div>

                <!-- COLUNA DIREITA: histórico de testes -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col min-h-0">
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

            <!-- MODAL DETALHE DO TESTE -->
            <div x-show="showModal" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div @click.away="showModal = false" class="bg-white rounded-xl shadow-2xl max-w-3xl w-full max-h-[88vh] flex flex-col relative border border-slate-200">
                    <div class="flex items-center justify-between p-5 border-b border-gray-100 shrink-0">
                        <h3 class="text-base font-bold text-gray-800" x-text="modalData.name"></h3>
                        <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-600">
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

                        <template x-for="scenario in scenarioGroups()" :key="scenario.title">
                            <div>
                                <p class="text-xs font-bold text-gray-700 mb-1.5" x-text="scenario.title + ' (' + scenario.category + ')'"></p>
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
