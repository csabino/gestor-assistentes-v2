@extends('layouts.app')

@section('title', 'Automação - ' . $assistant->name)

@section('content')
        <form action="/" method="POST" class="container mx-auto px-6 max-w-4xl flex flex-col h-[calc(100vh-8rem)] pt-4"
              x-data="{
                  messages: @js(count($automationMessages) ? $automationMessages : ['']),
                  async confirmSave(event) {
                      event.preventDefault();
                      const filled = this.messages.map(m => m.trim()).filter(m => m !== '');
                      const hasBlank = this.messages.some(m => m.trim() === '');

                      if (this.$refs.enabledToggle.checked && filled.length === 0) {
                          alert('Pra ativar a automação, cadastre pelo menos uma mensagem de retomada preenchida.');
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
                        <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">Automação — {{ $assistant->name }}</h1>
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

                    <template x-for="(msg, index) in messages" :key="index">
                        <div class="flex items-start gap-2 mb-2">
                            <span class="mt-2.5 text-xs font-bold text-gray-400 w-16 shrink-0" x-text="'Tentativa ' + (index + 1)"></span>
                            <textarea :name="'messages[' + index + ']'" x-model="messages[index]" rows="2" maxlength="1000"
                                      placeholder="Ex: Oi, ainda está por aí? Posso te ajudar com mais alguma coisa?"
                                      class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500"></textarea>
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
                </div>
            </div>

            </div>
        </form>
@endsection
