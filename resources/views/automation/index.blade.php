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

            <div class="flex-1 min-h-0 overflow-y-auto custom-scroll pr-1 pb-8">

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-6">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <h2 class="text-sm font-bold text-gray-800">Retomada de atendimento parado</h2>
                        <p class="text-xs text-gray-500 mt-1 max-w-xl">
                            Quando o cliente para de responder depois de uma mensagem da IA, o sistema espera o
                            intervalo abaixo e manda a próxima mensagem de retomada da lista. Depois da última
                            tentativa, se o cliente continuar em silêncio, o atendimento é encerrado automaticamente
                            (a própria IA gera a mensagem de encerramento configurada no prompt dela, disparando o
                            fechamento do chamado no Omni).
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" name="automation_enabled" value="1" x-ref="enabledToggle" class="sr-only peer" {{ $automationEnabled === '1' ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                    </label>
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Intervalo entre tentativas (minutos)</label>
                    <input type="number" name="automation_interval_minutes" min="1" required value="{{ $automationIntervalMinutes }}"
                           class="w-40 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500">
                    <p class="text-xs text-gray-400 mt-1">Tempo de silêncio do cliente antes de cada nova tentativa (e antes do encerramento, após a última).</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-2">Mensagens de retomada (uma por tentativa, nesta ordem)</label>
                    <p x-show="isMeta" x-cloak class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-2 leading-tight">
                        Esse assistente usa a API oficial da Meta - mensagens proativas de retomada só podem ser enviadas como <strong>Modelo de Mensagem (Template)</strong> já aprovado, não texto livre.
                    </p>

                    <template x-for="(msg, index) in messages" :key="index">
                        <div class="flex items-start gap-2 mb-2">
                            <span class="mt-2.5 text-xs font-bold text-gray-400 w-16 shrink-0" x-text="'Tentativa ' + (index + 1)"></span>
                            <template x-if="!isMeta">
                                <textarea :name="'messages[' + index + ']'" x-model="messages[index]" rows="2" maxlength="1000"
                                          placeholder="Ex: Oi, ainda está por aí? Posso te ajudar com mais alguma coisa?"
                                          class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500"></textarea>
                            </template>
                            <template x-if="isMeta">
                                <select :name="'messages[' + index + ']'" x-model="messages[index]" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500 bg-white">
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

                    <button type="button" @click="messages.push('')"
                            class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold mt-1 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Adicionar tentativa
                    </button>

                    <template x-if="isMeta">
                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <button type="button" @click="metaTemplateCreateOpen = !metaTemplateCreateOpen"
                                    class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                Criar novo template
                            </button>
                            <template x-if="metaTemplateCreateOpen">
                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mt-2 space-y-2 max-w-md">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Nome (minúsculas, sem espaço - use _)</label>
                                        <input type="text" x-model="metaTemplateForm.name" placeholder="retomada_atendimento" class="w-full border border-gray-300 rounded-md p-1.5 text-xs font-mono">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-700 mb-0.5">Idioma</label>
                                        <select x-model="metaTemplateForm.language" class="w-full border border-gray-300 rounded-md p-1.5 text-xs bg-white">
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
                                        <textarea x-model="metaTemplateForm.body" rows="3" maxlength="1024" placeholder="Ex: Olá @{{1}}! Notamos que nossa conversa ficou parada. Posso ajudar em mais alguma coisa?" class="w-full border border-gray-300 rounded-md p-1.5 text-xs"></textarea>
                                        <p class="text-[10px] text-gray-400 mt-0.5 leading-tight">Usa <code>@{{1}}</code> no texto pra inserir o nome do cliente automaticamente na hora do envio.</p>
                                    </div>
                                    <p x-show="metaTemplateError" x-text="metaTemplateError" class="text-[11px] text-red-600"></p>
                                    <button type="button" @click="createMetaTemplateSubmit()" :disabled="metaTemplateSaving"
                                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-1.5 px-4 rounded-lg text-[11px] transition">
                                        <span x-text="metaTemplateSaving ? 'Criando...' : 'Criar Template'"></span>
                                    </button>
                                    <p class="text-[10px] text-gray-400 leading-tight">O template passa por aprovação da Meta (minutos a horas) antes de poder ser usado de verdade - enquanto estiver PENDING, evite selecioná-lo pra uma tentativa ainda.</p>
                                </div>
                            </template>

                            <template x-if="metaTemplates.length > 0">
                                <div class="mt-3 space-y-1 max-w-md">
                                    <p class="text-[11px] font-semibold text-gray-600 flex items-center gap-1.5">
                                        Templates cadastrados
                                        <span x-show="metaTemplatesRefreshing" x-cloak class="flex items-center gap-1 text-gray-400 font-normal">
                                            <span class="inline-block animate-spin rounded-full h-2.5 w-2.5 border-2 border-gray-300 border-t-indigo-600"></span>
                                            Atualizando lista
                                        </span>
                                    </p>
                                    <template x-for="tpl in metaTemplates" :key="tpl.name + tpl.language">
                                        <div class="flex items-center justify-between bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5">
                                            <span class="text-[11px] text-gray-700 flex items-center gap-1.5">
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
                                            </span>
                                            <button type="button" @click="deleteMetaTemplateConfirm(tpl)" class="text-gray-400 hover:text-red-600 p-1 rounded transition" title="Remover template">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            </div>
        </form>
@endsection
