@extends('layouts.app')

@section('title', 'Automação - ' . $assistant->name)

@section('content')
        <form action="/" method="POST" class="container mx-auto px-6 max-w-4xl flex flex-col h-[calc(100vh-8rem)] pt-4"
              x-data="{
                  messages: @js(count($automationMessages) ? $automationMessages : ['']),
                  isMeta: @js($assistant->whatsapp_provider === 'meta'),
                  metaTemplates: [],
                  metaTemplatesRefreshing: false,
                  metaTemplateCreateOpen: false,
                  metaTemplateSaving: false,
                  metaTemplateError: null,
                  metaTemplateForm: { name: '', language: 'pt_BR', body: '' },
                  metaTemplatesModalOpen: false,
                  templateHasVariable(tpl) {
                      if (typeof tpl.hasVariable === 'boolean') return tpl.hasVariable;
                      const bodyComp = (tpl.components || []).find(c => (c.type || '').toUpperCase() === 'BODY');
                      return !!(bodyComp && bodyComp.text && bodyComp.text.includes('@{{1}}'));
                  },
                  async loadMetaTemplates() {
                      this.metaTemplatesRefreshing = true;
                      try {
                          const res = await fetch('/', {
                              method: 'POST',
                              headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                              body: JSON.stringify({ action: 'meta_list_templates', assistant_id: {{ $assistant->id }} }),
                          });
                          const data = await res.json();
                          const list = (res.ok && data.success) ? (data.templates || []) : [];
                          this.metaTemplates = list.map(tpl => ({ ...tpl, hasVariable: this.templateHasVariable(tpl) }));
                      } catch (e) {
                          this.metaTemplates = [];
                      } finally {
                          this.metaTemplatesRefreshing = false;
                      }
                  },
                  async deleteMetaTemplateConfirm(tpl) {
                      if (!(await confirmModal('Remover o template ' + tpl.name + '? Isso apaga todos os idiomas desse template na Meta.'))) return;
                      try {
                          const res = await fetch('/', {
                              method: 'POST',
                              headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                              body: JSON.stringify({ action: 'meta_delete_template', assistant_id: {{ $assistant->id }}, name: tpl.name }),
                          });
                          const data = await res.json();
                          if (!res.ok || !data.success) {
                              Alpine.store('toast').show(data.message || 'Não foi possível deletar o template.', 'error');
                              return;
                          }
                          this.metaTemplates = this.metaTemplates.filter(t => t.name !== tpl.name);
                          Alpine.store('toast').show('Template removido!', 'success');
                      } catch (e) {
                          Alpine.store('toast').show('Erro de conexão ao deletar.', 'error');
                      }
                  },
                  async createMetaTemplateSubmit() {
                      if (!this.metaTemplateForm.name || !this.metaTemplateForm.body) {
                          this.metaTemplateError = 'Preencha o nome e o corpo do template.';
                          return;
                      }
                      this.metaTemplateSaving = true;
                      this.metaTemplateError = null;
                      try {
                          const res = await fetch('/', {
                              method: 'POST',
                              headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                              body: JSON.stringify({
                                  action: 'meta_create_template',
                                  assistant_id: {{ $assistant->id }},
                                  name: this.metaTemplateForm.name,
                                  language: this.metaTemplateForm.language,
                                  body: this.metaTemplateForm.body,
                              }),
                          });
                          const data = await res.json();
                          if (!res.ok || !data.success) {
                              this.metaTemplateError = data.message || 'Não foi possível criar o template.';
                              return;
                          }
                          this.metaTemplates.push(data.template);
                          this.metaTemplateForm = { name: '', language: 'pt_BR', body: '' };
                          this.metaTemplateCreateOpen = false;
                          Alpine.store('toast').show('Template criado! Aguardando aprovação da Meta.', 'success');
                      } catch (e) {
                          this.metaTemplateError = 'Erro de conexão. Tente novamente.';
                      } finally {
                          this.metaTemplateSaving = false;
                      }
                  },
                  async confirmSave(event) {
                      event.preventDefault();
                      const filled = this.messages.map(m => m.trim()).filter(m => m !== '');
                      const hasBlank = this.messages.some(m => m.trim() === '');

                      if (this.$refs.enabledToggle.checked && filled.length === 0) {
                          alertModal('Pra ativar a automação, cadastre pelo menos uma mensagem de retomada preenchida.');
                          return;
                      }
                      if (hasBlank) {
                          if (!(await confirmModal('Uma ou mais mensagens de tentativa estão vazias. Clique OK pra remover essas tentativas vazias e salvar assim mesmo, ou Cancelar pra voltar e preenchê-las.'))) {
                              return;
                          }
                          this.messages = filled.length ? filled : [''];
                      }
                      this.$nextTick(() => this.$el.submit());
                  }
              }"
              x-init="if (isMeta) { loadMetaTemplates(); setInterval(() => loadMetaTemplates(), 60000); }"
              @submit="confirmSave($event)">
            @csrf

            <input type="hidden" name="view" value="automation">
            <input type="hidden" name="action" value="update_automation">
            <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">

            <!-- CABEÇALHO FIXO -->
            <div class="shrink-0 bg-gray-50 py-4 mb-4 border-b border-gray-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <a href="/?configure={{ $assistant->id }}" class="dark-btn-fix text-indigo-600 hover:text-indigo-800 font-semibold flex items-center gap-1.5 text-sm transition bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg border border-indigo-100 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg> Voltar
                    </a>
                    <div class="h-6 w-px bg-gray-300 hidden md:block"></div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-indigo-500 shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                            </svg>
                            Automação — {{ $assistant->name }}
                        </h1>
                        <p class="text-xs text-gray-500 mt-0.5">Retomada automática de atendimento quando o cliente para de responder.</p>
                    </div>
                </div>

                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-lg text-xs transition shadow-sm flex items-center gap-2 shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    Salvar Automação
                </button>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden custom-scroll pr-1 pb-8">

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-6">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                    <h2 class="text-sm font-bold text-gray-800 flex items-center gap-1.5">
                        Retomada de atendimento parado
                        <svg class="w-4 h-4 text-gray-400 cursor-help shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <title>Quando o cliente para de responder depois de uma mensagem da IA, o sistema espera o intervalo ao lado e manda a próxima mensagem de retomada da lista. Depois da última tentativa, se o cliente continuar em silêncio, o atendimento é encerrado automaticamente (a própria IA gera a mensagem de encerramento configurada no prompt dela, disparando o fechamento do chamado no Omni).</title>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                    </h2>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-1.5">
                            <label class="text-xs font-semibold text-gray-700 whitespace-nowrap" title="Tempo de silêncio do cliente antes de cada nova tentativa (e antes do encerramento, após a última).">Intervalo (min)</label>
                            <input type="number" name="automation_interval_minutes" min="1" required value="{{ $automationIntervalMinutes }}"
                                   class="w-16 border border-gray-300 rounded-lg px-2 py-1.5 text-sm text-center outline-none focus:border-indigo-500">
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <input type="checkbox" name="automation_enabled" value="1" x-ref="enabledToggle" class="sr-only peer" {{ $automationEnabled === '1' ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-700 flex items-center gap-1.5 mb-2">
                        Mensagens de retomada
                        <svg class="w-3.5 h-3.5 text-gray-400 cursor-help shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <title>@if($assistant->whatsapp_provider === 'meta')Uma mensagem por tentativa, nesta ordem. Esse assistente usa a API oficial da Meta - mensagens proativas de retomada só podem ser enviadas como Modelo de Mensagem (Template) já aprovado, não texto livre.@else Uma mensagem por tentativa, nesta ordem.@endif</title>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                        </svg>
                    </label>

                    <div class="flex items-center gap-4 mb-3 flex-wrap">
                        <template x-if="isMeta">
                            <button type="button" @click="metaTemplateCreateOpen = true"
                                    class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-xs font-semibold flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                Criar novo template
                            </button>
                        </template>
                        <template x-if="isMeta">
                            <button type="button" @click="metaTemplatesModalOpen = true"
                                    class="text-gray-600 hover:text-gray-800 dark:text-gray-300 dark:hover:text-white text-xs font-semibold flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H5.25a2.25 2.25 0 01-2.25-2.25V6.75a2.25 2.25 0 012.25-2.25h5.379a1.5 1.5 0 011.06.44l2.122 2.122a1.5 1.5 0 001.06.44H18.75a2.25 2.25 0 012.25 2.25v9a2.25 2.25 0 01-2.25 2.25z" /></svg>
                                Templates cadastrados
                                <span x-show="metaTemplates.length > 0" x-text="'(' + metaTemplates.length + ')'"></span>
                                <span x-show="metaTemplatesRefreshing" x-cloak class="inline-block animate-spin rounded-full h-2.5 w-2.5 border-2 border-gray-300 border-t-indigo-600"></span>
                            </button>
                        </template>
                        <button type="button" @click="messages.push('')"
                                class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold flex items-center gap-1 ml-auto">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            Adicionar tentativa
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-3 min-w-0">
                    <template x-for="(msg, index) in messages" :key="index">
                        <div class="flex items-start gap-2 min-w-0" :class="index % 2 === 1 ? 'sm:border-l sm:border-gray-200 sm:pl-6' : 'sm:pr-6'">
                            <span class="mt-2.5 text-xs font-bold text-gray-400 w-16 shrink-0" x-text="'Tentativa ' + (index + 1)"></span>
                            <template x-if="!isMeta">
                                <textarea :name="'messages[' + index + ']'" x-model="messages[index]" rows="2" maxlength="1000"
                                          placeholder="Ex: Oi, ainda está por aí? Posso te ajudar com mais alguma coisa?"
                                          class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500"></textarea>
                            </template>
                            <template x-if="isMeta">
                                <select :name="'messages[' + index + ']'" x-model="messages[index]" class="flex-1 min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500 bg-white">
                                    <option value="">Selecione um template...</option>
                                    <template x-for="tpl in metaTemplates" :key="tpl.name + tpl.language">
                                        <option :value="'tpl:' + tpl.name + ':' + tpl.language + ':' + (tpl.hasVariable ? '1' : '0')" x-text="tpl.name + ' (' + tpl.language + ') - ' + tpl.status + (tpl.hasVariable ? ' [usa nome]' : '')"></option>
                                    </template>
                                </select>
                            </template>
                            <button type="button" @click="if (messages.length > 1) messages.splice(index, 1)"
                                    class="mt-2 text-gray-400 hover:text-red-600 transition shrink-0" title="Remover tentativa">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    </template>
                    </div>
                </div>
            </div>

            <!-- MODAL TEMPLATES CADASTRADOS -->
            <div x-show="metaTemplatesModalOpen" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div @click.away="metaTemplatesModalOpen = false" class="bg-white rounded-xl shadow-2xl max-w-md w-full max-h-[80vh] flex flex-col relative border border-slate-200">
                    <div class="flex items-center justify-between p-5 border-b border-gray-100 shrink-0">
                        <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                            Templates cadastrados
                            <span x-show="metaTemplatesRefreshing" x-cloak class="flex items-center gap-1 text-[11px] text-gray-400 font-normal">
                                <span class="inline-block animate-spin rounded-full h-2.5 w-2.5 border-2 border-gray-300 border-t-indigo-600"></span>
                                Atualizando
                            </span>
                        </h3>
                        <button type="button" @click="metaTemplatesModalOpen = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="flex-1 min-h-0 overflow-y-auto p-5 space-y-1.5">
                        <p x-show="metaTemplates.length === 0" class="text-xs text-gray-400 text-center py-4">Nenhum template cadastrado ainda.</p>
                        <template x-for="tpl in metaTemplates" :key="tpl.name + tpl.language">
                            <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-[11px] text-gray-700 flex items-center gap-1.5 flex-wrap">
                                        <span class="font-bold" x-text="tpl.name"></span>
                                        <span class="text-gray-400" x-text="'(' + tpl.language + ')'"></span>
                                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                              :class="{
                                                  'bg-amber-50 text-amber-700 border border-amber-200': tpl.status === 'PENDING',
                                                  'bg-emerald-50 text-emerald-700 border border-emerald-200': tpl.status === 'APPROVED',
                                                  'bg-red-50 text-red-700 border border-red-200': tpl.status === 'REJECTED',
                                                  'bg-gray-100 text-gray-500 border border-gray-200': !['PENDING', 'APPROVED', 'REJECTED'].includes(tpl.status)
                                              }"
                                              x-text="tpl.status"></span>
                                        <span x-show="tpl.category" class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-500 border border-gray-200" x-text="tpl.category"></span>
                                    </span>
                                    <button type="button" @click="deleteMetaTemplateConfirm(tpl)" class="text-gray-400 hover:text-red-600 p-1 rounded transition shrink-0" title="Remover template">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                                <template x-if="tpl.rejected_reason && tpl.rejected_reason !== 'NONE'">
                                    <p class="text-[10px] text-red-600 mt-1 leading-tight">Motivo (Meta): <span x-text="tpl.rejected_reason"></span></p>
                                </template>
                                <template x-if="tpl.status !== 'APPROVED'">
                                    <p class="text-[10px] text-gray-400 mt-1 leading-tight">Se a categoria mudou ou foi rejeitado, apague e crie de novo com o texto ajustado - a Meta não permite reenviar o mesmo template pra revisão.</p>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- MODAL CRIAR NOVO TEMPLATE -->
            <div x-show="metaTemplateCreateOpen" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div @click.away="metaTemplateCreateOpen = false" class="bg-white rounded-xl shadow-2xl max-w-md w-full max-h-[85vh] flex flex-col relative border border-slate-200">
                    <div class="flex items-center justify-between p-5 border-b border-gray-100 shrink-0">
                        <h3 class="text-base font-bold text-gray-800">Criar novo template</h3>
                        <button type="button" @click="metaTemplateCreateOpen = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="flex-1 min-h-0 overflow-y-auto p-5 space-y-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Nome (minúsculas, sem espaço - use _)</label>
                            <input type="text" x-model="metaTemplateForm.name" placeholder="retomada_atendimento" class="w-full border border-gray-300 rounded-md p-2 text-xs font-mono">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Idioma</label>
                            <select x-model="metaTemplateForm.language" class="w-full border border-gray-300 rounded-md p-2 text-xs bg-white">
                                <option value="pt_BR">Português (Brasil)</option>
                                <option value="en_US">Inglês (EUA)</option>
                                <option value="es_ES">Espanhol</option>
                            </select>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-0.5">
                                <label class="block text-[11px] font-semibold text-gray-700">Corpo da mensagem</label>
                                <button type="button" @click="metaTemplateForm.body += '@{{1}}'" class="text-[10px] text-indigo-600 hover:text-indigo-800 font-semibold">+ Inserir nome do cliente</button>
                            </div>
                            <textarea x-model="metaTemplateForm.body" rows="4" maxlength="1024" placeholder="Ex: Olá @{{1}}! Notamos que nossa conversa ficou parada. Posso ajudar em mais alguma coisa?" class="w-full border border-gray-300 rounded-md p-2 text-xs"></textarea>
                            <p class="text-[10px] text-gray-400 mt-0.5 leading-tight">Usa <code>@{{1}}</code> no texto pra inserir o nome do cliente automaticamente na hora do envio.</p>
                        </div>
                        <p x-show="metaTemplateError" x-text="metaTemplateError" class="text-[11px] text-red-600"></p>
                        <p class="text-[10px] text-gray-400 leading-tight">O template passa por aprovação da Meta (minutos a horas) antes de poder ser usado de verdade - enquanto estiver PENDING, evite selecioná-lo pra uma tentativa ainda.</p>
                    </div>
                    <div class="p-5 border-t border-gray-100 shrink-0">
                        <button type="button" @click="createMetaTemplateSubmit()" :disabled="metaTemplateSaving"
                                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg text-xs transition disabled:opacity-60">
                            <span x-text="metaTemplateSaving ? 'Criando...' : 'Criar Template'"></span>
                        </button>
                    </div>
                </div>
            </div>

            </div>
        </form>
@endsection
