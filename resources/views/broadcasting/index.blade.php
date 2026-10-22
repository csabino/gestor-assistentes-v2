@extends('layouts.app')

@section('title', 'Broadcasting')

@section('content')
        <div class="container mx-auto px-6 max-w-7xl flex flex-col h-[calc(100vh-8rem)] pt-4"
             x-data="{
                 campaigns: @js($campaigns->map(fn($c) => [
                     'id' => $c->id, 'name' => $c->name, 'status' => $c->status,
                     'assistant_id' => $c->assistant_id, 'assistant_name' => $c->assistant->name ?? '—',
                     'assistant_provider' => $c->assistant->whatsapp_provider ?? '',
                     'raw_contacts_count' => $c->raw_contacts_count, 'valid_contacts_count' => $c->valid_contacts_count,
                     'invalid_contacts_count' => $c->invalid_contacts_count, 'sent_count' => $c->sent_count,
                     'failed_count' => $c->failed_count, 'message_preview' => $c->message_preview,
                     'message_selector' => $c->message_selector, 'scheduled_at' => $c->scheduled_at,
                     'batch_size' => $c->batch_size,
                 ])),
                 assistants: @js($assistants->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'provider' => $a->whatsapp_provider])),
                 freeMessages: @js($messages->map(fn($m) => ['id' => $m->id, 'name' => $m->name, 'body' => $m->body])),

                 showCampaignModal: false,
                 campaignForm: { id: null, name: '', assistant_id: '', raw_contacts_count: 0, valid_contacts_count: 0, invalid_contacts_count: 0, uploading: false, sanitizing: false, message_selector: '', message_preview: '' },
                 metaTemplatesForCampaign: [],
                 metaTemplatesLoading: false,
                 metaTemplatesError: null,
                 inlineMessageText: '',

                 showMessageModal: false,
                 messageAssistantId: '',
                 metaTemplateForm: { name: '', language: 'pt_BR', category: 'UTILITY', body: '' },
                 metaTemplateSaving: false,
                 metaTemplateError: null,
                 freeMessageForm: { name: '', body: '' },
                 freeMessageSaving: false,

                 showDispatchModal: false,
                 dispatchCampaignId: null,
                 dispatchMode: 'now',
                 dispatchScheduledAt: '',
                 dispatchSaving: false,

                 showResultModal: false,
                 resultData: null,
                 resultLoading: false,

                 selectedAssistant(id) {
                     return this.assistants.find(a => a.id === Number(id)) || null;
                 },
                 isMetaAssistant(id) {
                     const a = this.selectedAssistant(id);
                     return !!(a && a.provider === 'meta');
                 },
                 statusLabel(status) {
                     const map = { draft: 'Rascunho', ready: 'Pronta', scheduled: 'Agendada', sending: 'Enviando', paused: 'Pausada', completed: 'Concluída', canceled: 'Cancelada' };
                     return map[status] || status;
                 },
                 statusClasses(status) {
                     const map = {
                         draft: 'bg-gray-100 text-gray-600 border-gray-200',
                         ready: 'bg-blue-50 text-blue-700 border-blue-200',
                         scheduled: 'bg-purple-50 text-purple-700 border-purple-200',
                         sending: 'bg-amber-50 text-amber-700 border-amber-200',
                         paused: 'bg-orange-50 text-orange-700 border-orange-200',
                         completed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                         canceled: 'bg-red-50 text-red-700 border-red-200',
                     };
                     return map[status] || 'bg-gray-100 text-gray-600 border-gray-200';
                 },

                 async postJson(url, body) {
                     const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify(body) });
                     let data = {};
                     try { data = await res.json(); } catch (e) { /* resposta sem corpo JSON */ }
                     return { ok: res.ok, data };
                 },

                 openNewCampaign() {
                     this.campaignForm = { id: null, name: '', assistant_id: '', raw_contacts_count: 0, valid_contacts_count: 0, invalid_contacts_count: 0, uploading: false, sanitizing: false, message_selector: '', message_preview: '' };
                     this.metaTemplatesForCampaign = [];
                     this.inlineMessageText = '';
                     this.showCampaignModal = true;
                 },
                 editCampaign(c) {
                     this.campaignForm = { id: c.id, name: c.name, assistant_id: c.assistant_id, raw_contacts_count: c.raw_contacts_count, valid_contacts_count: c.valid_contacts_count, invalid_contacts_count: c.invalid_contacts_count, uploading: false, sanitizing: false, message_selector: c.message_selector || '', message_preview: c.message_preview || '' };
                     this.metaTemplatesForCampaign = [];
                     this.inlineMessageText = '';
                     if (this.isMetaAssistant(c.assistant_id)) this.loadMetaTemplatesForCampaign();
                     this.showCampaignModal = true;
                 },
                 closeCampaignModal() {
                     this.showCampaignModal = false;
                     window.location.reload();
                 },
                 async saveCampaignBasics() {
                     if (!this.campaignForm.name || !this.campaignForm.assistant_id) {
                         alertModal('Preencha o nome e escolha o assistente.');
                         return;
                     }
                     const action = this.campaignForm.id ? 'update_campaign' : 'store_campaign';
                     const { ok, data } = await this.postJson('/?view=broadcasting', { action, campaign_id: this.campaignForm.id, name: this.campaignForm.name, assistant_id: this.campaignForm.assistant_id });
                     if (!ok || !data.success) {
                         Alpine.store('toast').show(data.message || 'Não foi possível salvar.', 'error');
                         return;
                     }
                     this.campaignForm.id = data.campaign.id;
                     this.metaTemplatesForCampaign = [];
                     if (this.isMetaAssistant(this.campaignForm.assistant_id)) this.loadMetaTemplatesForCampaign();
                     Alpine.store('toast').show('Campanha salva!', 'success');
                 },
                 async handleMailingUpload(event) {
                     const file = event.target.files[0];
                     if (!file) return;
                     if (!this.campaignForm.id) {
                         alertModal('Salve o nome e o assistente da campanha antes de subir o mailing.');
                         event.target.value = '';
                         return;
                     }
                     this.campaignForm.uploading = true;
                     try {
                         const fd = new FormData();
                         fd.append('action', 'upload_broadcast_mailing');
                         fd.append('campaign_id', this.campaignForm.id);
                         fd.append('file', file);
                         const res = await fetch('/?view=broadcasting', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: fd });
                         const data = await res.json();
                         if (!res.ok || !data.success) {
                             Alpine.store('toast').show(data.message || 'Não foi possível subir o mailing.', 'error');
                             return;
                         }
                         this.campaignForm.raw_contacts_count = data.raw_contacts_count;
                         this.campaignForm.valid_contacts_count = 0;
                         this.campaignForm.invalid_contacts_count = 0;
                         Alpine.store('toast').show('Mailing enviado! Agora clique em Sanear.', 'success');
                     } catch (e) {
                         Alpine.store('toast').show('Erro de conexão.', 'error');
                     } finally {
                         this.campaignForm.uploading = false;
                         event.target.value = '';
                     }
                 },
                 async sanitizeMailing() {
                     this.campaignForm.sanitizing = true;
                     try {
                         const { ok, data } = await this.postJson('/?view=broadcasting', { action: 'run_sanitization', campaign_id: this.campaignForm.id });
                         if (!ok || !data.success) {
                             Alpine.store('toast').show(data.message || 'Não foi possível sanear o mailing.', 'error');
                             return;
                         }
                         this.campaignForm.valid_contacts_count = data.valid_contacts_count;
                         this.campaignForm.invalid_contacts_count = data.invalid_contacts_count;
                         Alpine.store('toast').show('Mailing saneado!', 'success');
                     } finally {
                         this.campaignForm.sanitizing = false;
                     }
                 },
                 async loadMetaTemplatesForCampaign() {
                     this.metaTemplatesLoading = true;
                     try {
                         const { ok, data } = await this.postJson('/', { action: 'meta_list_templates', assistant_id: this.campaignForm.assistant_id });
                         if (ok && data.success) {
                             this.metaTemplatesForCampaign = (data.templates || []).map(tpl => {
                                 const bodyComp = (tpl.components || []).find(c => (c.type || '').toUpperCase() === 'BODY');
                                 return { ...tpl, bodyText: bodyComp?.text || '', hasVariable: !!(bodyComp && bodyComp.text && bodyComp.text.includes('@{{1}}')) };
                             });
                             this.metaTemplatesError = null;
                         } else {
                             this.metaTemplatesForCampaign = [];
                             this.metaTemplatesError = data.message || 'Não foi possível buscar os templates na Meta.';
                         }
                     } catch (e) {
                         this.metaTemplatesError = 'Erro de conexão ao buscar os templates.';
                     } finally {
                         this.metaTemplatesLoading = false;
                     }
                 },
                 async selectTemplate(selector, previewText) {
                     this.campaignForm.message_selector = selector;
                     this.campaignForm.message_preview = previewText;
                     const { ok, data } = await this.postJson('/?view=broadcasting', { action: 'update_campaign', campaign_id: this.campaignForm.id, message_selector: selector });
                     if (!ok || !data.success) {
                         Alpine.store('toast').show(data.message || 'Não foi possível salvar a mensagem escolhida.', 'error');
                         return;
                     }
                     Alpine.store('toast').show('Mensagem da campanha definida!', 'success');
                 },
                 async createAndSelectInlineMessage() {
                     const text = this.inlineMessageText.trim();
                     if (!text) {
                         alertModal('Digite a mensagem padrão da campanha.');
                         return;
                     }
                     const label = 'Campanha: ' + (this.campaignForm.name || ('#' + this.campaignForm.id));
                     const { ok, data } = await this.postJson('/?view=broadcasting', { action: 'store_broadcast_message', name: label, body: text });
                     if (!ok || !data.success) {
                         Alpine.store('toast').show(data.message || 'Não foi possível salvar a mensagem.', 'error');
                         return;
                     }
                     this.freeMessages.push(data.message);
                     await this.selectTemplate('free:' + data.message.id, this.renderPreview(text));
                     this.inlineMessageText = '';
                 },
                 renderPreview(body) {
                     return (body || '').replaceAll('@{{1}}', 'Maria').replaceAll('@{{nome}}', 'Maria');
                 },

                 async deleteCampaign(id, name) {
                     if (!(await confirmModal('Excluir a campanha &quot;' + name + '&quot;? Essa ação não pode ser desfeita.'))) return;
                     const { ok, data } = await this.postJson('/?view=broadcasting', { action: 'delete_campaign', campaign_id: id });
                     if (!ok || !data.success) {
                         Alpine.store('toast').show(data.message || 'Não foi possível excluir.', 'error');
                         return;
                     }
                     window.location.reload();
                 },
                 openDispatch(id) {
                     this.dispatchCampaignId = id;
                     this.dispatchMode = 'now';
                     this.dispatchScheduledAt = '';
                     this.showDispatchModal = true;
                 },
                 async confirmDispatch() {
                     if (this.dispatchMode === 'scheduled' && !this.dispatchScheduledAt) {
                         alertModal('Escolha a data e horário do disparo agendado.');
                         return;
                     }
                     this.dispatchSaving = true;
                     try {
                         const { ok, data } = await this.postJson('/?view=broadcasting', { action: 'dispatch_campaign', campaign_id: this.dispatchCampaignId, mode: this.dispatchMode, scheduled_at: this.dispatchScheduledAt });
                         if (!ok || !data.success) {
                             Alpine.store('toast').show(data.message || 'Não foi possível disparar.', 'error');
                             return;
                         }
                         this.showDispatchModal = false;
                         window.location.reload();
                     } finally {
                         this.dispatchSaving = false;
                     }
                 },
                 async pauseCampaign(id) {
                     const { ok, data } = await this.postJson('/?view=broadcasting', { action: 'pause_campaign', campaign_id: id });
                     if (!ok || !data.success) { Alpine.store('toast').show(data.message || 'Não foi possível pausar.', 'error'); return; }
                     window.location.reload();
                 },
                 async resumeCampaign(id) {
                     const { ok, data } = await this.postJson('/?view=broadcasting', { action: 'resume_campaign', campaign_id: id });
                     if (!ok || !data.success) { Alpine.store('toast').show(data.message || 'Não foi possível retomar.', 'error'); return; }
                     window.location.reload();
                 },
                 async cancelCampaign(id) {
                     if (!(await confirmModal('Cancelar essa campanha? Os contatos que ainda não receberam mensagem não vão mais receber.'))) return;
                     const { ok, data } = await this.postJson('/?view=broadcasting', { action: 'cancel_campaign', campaign_id: id });
                     if (!ok || !data.success) { Alpine.store('toast').show(data.message || 'Não foi possível cancelar.', 'error'); return; }
                     window.location.reload();
                 },
                 async openResult(id) {
                     this.resultLoading = true;
                     this.showResultModal = true;
                     try {
                         const res = await fetch('/?view=broadcasting&action=campaign_detail&campaign_id=' + id);
                         this.resultData = await res.json();
                     } finally {
                         this.resultLoading = false;
                     }
                 },

                 openMessageModal() {
                     this.messageAssistantId = '';
                     this.metaTemplateForm = { name: '', language: 'pt_BR', category: 'UTILITY', body: '' };
                     this.metaTemplateError = null;
                     this.freeMessageForm = { name: '', body: '' };
                     this.showMessageModal = true;
                 },
                 async createMetaTemplateInModal() {
                     if (!this.metaTemplateForm.name || !this.metaTemplateForm.body) {
                         this.metaTemplateError = 'Preencha o nome e o corpo do template.';
                         return;
                     }
                     this.metaTemplateSaving = true;
                     this.metaTemplateError = null;
                     try {
                         const { ok, data } = await this.postJson('/', { action: 'meta_create_template', assistant_id: this.messageAssistantId, name: this.metaTemplateForm.name, language: this.metaTemplateForm.language, category: this.metaTemplateForm.category, body: this.metaTemplateForm.body });
                         if (!ok || !data.success) {
                             this.metaTemplateError = data.message || 'Não foi possível salvar o template.';
                             return;
                         }
                         this.metaTemplateForm = { name: '', language: 'pt_BR', category: 'UTILITY', body: '' };
                         Alpine.store('toast').show('Template criado! Aguardando aprovação da Meta.', 'success');
                     } catch (e) {
                         this.metaTemplateError = 'Erro de conexão. Tente novamente.';
                     } finally {
                         this.metaTemplateSaving = false;
                     }
                 },
                 async createFreeMessage() {
                     if (!this.freeMessageForm.name || !this.freeMessageForm.body) {
                         alertModal('Preencha o nome e o texto da mensagem.');
                         return;
                     }
                     this.freeMessageSaving = true;
                     try {
                         const { ok, data } = await this.postJson('/?view=broadcasting', { action: 'store_broadcast_message', name: this.freeMessageForm.name, body: this.freeMessageForm.body });
                         if (!ok || !data.success) {
                             Alpine.store('toast').show(data.message || 'Não foi possível salvar.', 'error');
                             return;
                         }
                         this.freeMessages.push(data.message);
                         this.freeMessageForm = { name: '', body: '' };
                         Alpine.store('toast').show('Mensagem cadastrada!', 'success');
                     } finally {
                         this.freeMessageSaving = false;
                     }
                 },
                 async deleteFreeMessage(id, name) {
                     if (!(await confirmModal('Excluir a mensagem &quot;' + name + '&quot;?'))) return;
                     const { ok, data } = await this.postJson('/?view=broadcasting', { action: 'delete_broadcast_message', message_id: id });
                     if (!ok || !data.success) { Alpine.store('toast').show(data.message || 'Não foi possível excluir.', 'error'); return; }
                     this.freeMessages = this.freeMessages.filter(m => m.id !== id);
                 }
             }">

            <!-- CABEÇALHO -->
            <div class="shrink-0 flex flex-wrap items-center justify-between gap-3 pb-4">
                <div>
                    <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 017.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12 17.95l-.01.01" /></svg>
                        Broadcasting
                    </h1>
                    <p class="text-xs text-gray-500 mt-0.5">Campanhas de envio de mensagem em massa via WhatsApp.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="openMessageModal()" class="bg-white border border-gray-200 hover:border-indigo-300 text-gray-700 font-semibold py-2 px-4 rounded-lg text-xs transition">
                        Mensagens / Templates
                    </button>
                    <button type="button" @click="openNewCampaign()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg text-xs transition shadow-sm">
                        + Nova Campanha
                    </button>
                </div>
            </div>

            <!-- GRID DE CAMPANHAS -->
            <div class="flex-1 min-h-0 overflow-y-auto bg-white rounded-xl border border-gray-200 shadow-sm">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 border-b border-gray-200 sticky top-0">
                        <tr class="text-left text-gray-500 font-semibold">
                            <th class="px-4 py-3">Campanha</th>
                            <th class="px-4 py-3">Assistente</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Válidos/Total</th>
                            <th class="px-4 py-3">Enviados/Falhas</th>
                            <th class="px-4 py-3">Agendamento</th>
                            <th class="px-4 py-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="c in campaigns" :key="c.id">
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="px-4 py-3 font-semibold text-gray-700" x-text="c.name"></td>
                                <td class="px-4 py-3 text-gray-600" x-text="c.assistant_name"></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border" :class="statusClasses(c.status)" x-text="statusLabel(c.status)"></span>
                                </td>
                                <td class="px-4 py-3 text-gray-600" x-text="c.valid_contacts_count + ' / ' + c.raw_contacts_count"></td>
                                <td class="px-4 py-3 text-gray-600" x-text="c.sent_count + ' / ' + c.failed_count"></td>
                                <td class="px-4 py-3 text-gray-500" x-text="c.scheduled_at ? new Date(c.scheduled_at).toLocaleString('pt-BR') : '—'"></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <button type="button" x-show="['draft','ready'].includes(c.status)" @click="editCampaign(c)" class="text-indigo-600 hover:text-indigo-800 font-semibold">Editar</button>
                                        <button type="button" x-show="c.status === 'ready'" @click="openDispatch(c.id)" class="text-emerald-600 hover:text-emerald-800 font-semibold">Disparar</button>
                                        <button type="button" x-show="['sending','scheduled'].includes(c.status)" @click="pauseCampaign(c.id)" class="text-orange-600 hover:text-orange-800 font-semibold">Pausar</button>
                                        <button type="button" x-show="c.status === 'paused'" @click="resumeCampaign(c.id)" class="text-emerald-600 hover:text-emerald-800 font-semibold">Retomar</button>
                                        <button type="button" x-show="['sending','scheduled','paused'].includes(c.status)" @click="cancelCampaign(c.id)" class="text-red-600 hover:text-red-800 font-semibold">Cancelar</button>
                                        <button type="button" x-show="c.raw_contacts_count > 0" @click="openResult(c.id)" class="text-gray-500 hover:text-gray-700 font-semibold">Resultado</button>
                                        <button type="button" x-show="!['sending'].includes(c.status)" @click="deleteCampaign(c.id, c.name)" class="text-gray-400 hover:text-red-600 font-semibold">Excluir</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="campaigns.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">Nenhuma campanha ainda. Clique em "Nova Campanha" para começar.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- MODAL NOVA/EDITAR CAMPANHA -->
            <div x-show="showCampaignModal" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div @click.away="closeCampaignModal()" class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto border border-slate-200">
                    <div class="flex items-center justify-between p-5 border-b border-gray-100">
                        <h3 class="text-base font-bold text-gray-800">Campanha de Broadcasting</h3>
                        <button type="button" @click="closeCampaignModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="p-5 space-y-5">
                        <!-- Passo 1: nome + assistente -->
                        <div class="space-y-2">
                            <p class="text-xs font-bold text-gray-500 uppercase">1. Nome e remetente</p>
                            <input type="text" x-model="campaignForm.name" placeholder="Nome da campanha" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <select x-model="campaignForm.assistant_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="">Escolha o assistente/número que vai enviar</option>
                                <template x-for="a in assistants" :key="a.id">
                                    <option :value="a.id" x-text="a.name + (a.provider === 'meta' ? ' (Meta)' : ' (não-oficial)')"></option>
                                </template>
                            </select>
                            <p class="text-[11px] text-amber-600" x-show="campaignForm.assistant_id && !isMetaAssistant(campaignForm.assistant_id)">
                                Atenção: envio em massa por número não-oficial pode gerar bloqueio do WhatsApp. Lotes pequenos e com atraso já são aplicados automaticamente, mas isso é mitigação, não garantia.
                            </p>
                            <button type="button" @click="saveCampaignBasics()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-1.5 px-4 rounded-lg text-xs transition">Salvar nome/remetente</button>
                        </div>

                        <template x-if="campaignForm.id">
                            <div class="space-y-5 border-t border-gray-100 pt-5">
                                <!-- Passo 2: mailing -->
                                <div class="space-y-2">
                                    <p class="text-xs font-bold text-gray-500 uppercase">2. Mailing</p>
                                    <div class="flex items-center gap-3">
                                        <input type="file" accept=".csv,.txt" @change="handleMailingUpload($event)" :disabled="campaignForm.uploading" class="text-xs">
                                        <a href="/?view=broadcasting&action=download_mailing_template" class="text-indigo-600 hover:text-indigo-800 text-xs font-semibold whitespace-nowrap">Baixar modelo</a>
                                    </div>
                                    <p class="text-[11px] text-gray-500">CSV com colunas "nome" e "telefone" (separador ;). Salve como UTF-8 pra não corromper acentos.</p>
                                    <p class="text-xs text-gray-600" x-show="campaignForm.raw_contacts_count > 0">
                                        <span x-text="campaignForm.raw_contacts_count"></span> contatos no mailing.
                                        <button type="button" @click="sanitizeMailing()" :disabled="campaignForm.sanitizing" class="ml-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-semibold py-1 px-3 rounded-md text-[11px] transition">
                                            <span x-show="!campaignForm.sanitizing">Sanear</span>
                                            <span x-show="campaignForm.sanitizing">Saneando...</span>
                                        </button>
                                    </p>
                                    <p class="text-xs text-gray-600" x-show="campaignForm.invalid_contacts_count > 0 || campaignForm.valid_contacts_count > 0">
                                        <span class="text-emerald-600 font-semibold" x-text="campaignForm.valid_contacts_count + ' válidos'"></span>
                                        <span class="text-gray-300 mx-1">·</span>
                                        <span class="text-red-500 font-semibold" x-text="campaignForm.invalid_contacts_count + ' inválidos (vazios/duplicados/formato)'"></span>
                                    </p>
                                </div>

                                <!-- Passo 3: mensagem -->
                                <div class="space-y-2" x-show="campaignForm.valid_contacts_count > 0">
                                    <p class="text-xs font-bold text-gray-500 uppercase">3. Mensagem</p>
                                    <template x-if="isMetaAssistant(campaignForm.assistant_id)">
                                        <div class="space-y-1.5">
                                            <p class="text-[11px] text-gray-500" x-show="metaTemplatesLoading">Carregando templates da Meta...</p>
                                            <p class="text-[11px] text-red-500" x-show="metaTemplatesError" x-text="metaTemplatesError"></p>
                                            <select @change="selectTemplate($event.target.value, renderPreview(metaTemplatesForCampaign.find(t => ('tpl:'+t.name+':'+t.language+':'+(t.hasVariable?1:0)) === $event.target.value)?.bodyText))" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                                <option value="">Escolha um template aprovado</option>
                                                <template x-for="tpl in metaTemplatesForCampaign" :key="tpl.id">
                                                    <option :value="'tpl:' + tpl.name + ':' + tpl.language + ':' + (tpl.hasVariable ? 1 : 0)" :disabled="tpl.status !== 'APPROVED'" :selected="campaignForm.message_selector === ('tpl:' + tpl.name + ':' + tpl.language + ':' + (tpl.hasVariable ? 1 : 0))" x-text="tpl.name + ' (' + tpl.status + ')'"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </template>
                                    <template x-if="!isMetaAssistant(campaignForm.assistant_id)">
                                        <div class="space-y-2">
                                            <select x-show="freeMessages.length > 0" @change="selectTemplate($event.target.value, renderPreview(freeMessages.find(m => ('free:'+m.id) === $event.target.value)?.body))" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                                <option value="">Ou escolha uma mensagem já cadastrada</option>
                                                <template x-for="m in freeMessages" :key="m.id">
                                                    <option :value="'free:' + m.id" :selected="campaignForm.message_selector === ('free:' + m.id)" x-text="m.name"></option>
                                                </template>
                                            </select>
                                            <p class="text-[11px] text-gray-500">Digite a mensagem padrão desta campanha. Use @{{nome}} pra inserir o nome do cliente.</p>
                                            <textarea x-model="inlineMessageText" rows="3" placeholder="Ex: Oi @{{nome}}! Temos uma novidade pra te contar..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                                            <p class="text-[11px] text-gray-500">Antes de cada envio, a IA reescreve essa mensagem com uma pequena variação pra cada contato (sem mudar o sentido) - evita mandar o texto idêntico pra toda a lista, reduzindo o risco de bloqueio.</p>
                                            <button type="button" @click="createAndSelectInlineMessage()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-1.5 px-4 rounded-lg text-xs transition">Usar esta mensagem</button>
                                        </div>
                                    </template>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-600" x-show="campaignForm.message_preview">
                                        <span class="font-semibold text-gray-500">Preview (exemplo):</span>
                                        <p class="mt-1" x-text="campaignForm.message_preview"></p>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="flex items-center justify-end gap-2 p-5 border-t border-gray-100">
                        <button type="button" @click="closeCampaignModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-2 rounded-lg text-xs transition">Fechar</button>
                    </div>
                </div>
            </div>

            <!-- MODAL DISPARAR -->
            <div x-show="showDispatchModal" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div @click.away="showDispatchModal = false" class="bg-white rounded-xl shadow-2xl max-w-sm w-full border border-slate-200">
                    <div class="flex items-center justify-between p-5 border-b border-gray-100">
                        <h3 class="text-base font-bold text-gray-800">Disparar campanha</h3>
                        <button type="button" @click="showDispatchModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="p-5 space-y-3">
                        <p class="text-xs text-gray-500">Sem fila de processamento neste app: o disparo (imediato ou agendado) só começa de fato no próximo "tick" do cron externo configurado.</p>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" value="now" x-model="dispatchMode"> Agora
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" value="scheduled" x-model="dispatchMode"> Agendar
                        </label>
                        <input type="datetime-local" x-show="dispatchMode === 'scheduled'" x-model="dispatchScheduledAt" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div class="flex items-center justify-end gap-2 p-5 border-t border-gray-100">
                        <button type="button" @click="showDispatchModal = false" class="text-gray-500 hover:text-gray-700 text-xs font-semibold px-3 py-2">Cancelar</button>
                        <button type="button" @click="confirmDispatch()" :disabled="dispatchSaving" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2 rounded-lg text-xs transition">
                            <span x-show="!dispatchSaving">Confirmar disparo</span>
                            <span x-show="dispatchSaving">Disparando...</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- MODAL RESULTADO -->
            <div x-show="showResultModal" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div @click.away="showResultModal = false" class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[85vh] flex flex-col border border-slate-200">
                    <div class="flex items-center justify-between p-5 border-b border-gray-100 shrink-0">
                        <h3 class="text-base font-bold text-gray-800" x-text="resultData?.campaign?.name || 'Resultado'"></h3>
                        <button type="button" @click="showResultModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-5 space-y-4">
                        <p class="text-xs text-gray-400" x-show="resultLoading">Carregando...</p>
                        <template x-if="resultData">
                            <div class="space-y-4">
                                <div class="grid grid-cols-5 gap-2 text-center">
                                    <template x-for="key in ['pending','sending','sent','failed','skipped']" :key="key">
                                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-2">
                                            <p class="text-sm font-bold text-gray-700" x-text="resultData.status_counts?.[key] || 0"></p>
                                            <p class="text-[10px] text-gray-500" x-text="key"></p>
                                        </div>
                                    </template>
                                </div>
                                <table class="w-full text-xs">
                                    <thead class="text-left text-gray-400">
                                        <tr><th class="py-1">Nome</th><th class="py-1">Telefone</th><th class="py-1">Status</th><th class="py-1">Erro</th></tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="row in resultData.contacts" :key="row.id">
                                            <tr class="border-t border-gray-100">
                                                <td class="py-1" x-text="row.name || '—'"></td>
                                                <td class="py-1" x-text="row.phone_raw"></td>
                                                <td class="py-1" x-text="row.send_status"></td>
                                                <td class="py-1 text-red-500" x-text="row.send_error || ''"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                                <p class="text-[11px] text-gray-400" x-show="resultData.total_contacts > resultData.per_page">
                                    Mostrando os primeiros <span x-text="resultData.per_page"></span> de <span x-text="resultData.total_contacts"></span> contatos.
                                </p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- MODAL MENSAGENS / TEMPLATES -->
            <div x-show="showMessageModal" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                <div @click.away="showMessageModal = false" class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto border border-slate-200">
                    <div class="flex items-center justify-between p-5 border-b border-gray-100">
                        <h3 class="text-base font-bold text-gray-800">Mensagens / Templates</h3>
                        <button type="button" @click="showMessageModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="p-5 space-y-5">
                        <div class="space-y-2">
                            <p class="text-xs font-bold text-gray-500 uppercase">Cadastrar template da Meta (precisa de aprovação)</p>
                            <select x-model="messageAssistantId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="">Escolha o assistente Meta</option>
                                <template x-for="a in assistants.filter(a => a.provider === 'meta')" :key="a.id">
                                    <option :value="a.id" x-text="a.name"></option>
                                </template>
                            </select>
                            <template x-if="messageAssistantId">
                                <div class="space-y-2 bg-gray-50 border border-gray-200 rounded-lg p-3">
                                    <input type="text" x-model="metaTemplateForm.name" placeholder="nome_do_template (sem espaço/acento)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs">
                                    <div class="flex gap-2">
                                        <input type="text" x-model="metaTemplateForm.language" placeholder="Idioma (ex: pt_BR)" class="w-1/2 border border-gray-300 rounded-lg px-3 py-2 text-xs">
                                        <select x-model="metaTemplateForm.category" class="w-1/2 border border-gray-300 rounded-lg px-3 py-2 text-xs">
                                            <option value="UTILITY">Utilidade</option>
                                            <option value="MARKETING">Marketing (cobrança por conversa)</option>
                                        </select>
                                    </div>
                                    <textarea x-model="metaTemplateForm.body" rows="3" placeholder="Corpo da mensagem. Use @{{1}} pra inserir o nome do cliente." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs"></textarea>
                                    <p class="text-[11px] text-red-500" x-show="metaTemplateError" x-text="metaTemplateError"></p>
                                    <button type="button" @click="createMetaTemplateInModal()" :disabled="metaTemplateSaving" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-1.5 rounded-lg text-xs transition">
                                        <span x-show="!metaTemplateSaving">Enviar pra aprovação</span>
                                        <span x-show="metaTemplateSaving">Enviando...</span>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <div class="space-y-2 border-t border-gray-100 pt-5">
                            <p class="text-xs font-bold text-gray-500 uppercase">Mensagens de texto livre (números não-oficiais)</p>
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 space-y-2">
                                <input type="text" x-model="freeMessageForm.name" placeholder="Nome da mensagem (rótulo interno)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs">
                                <textarea x-model="freeMessageForm.body" rows="3" placeholder="Texto da mensagem. Use @{{nome}} pra inserir o nome do cliente." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs"></textarea>
                                <button type="button" @click="createFreeMessage()" :disabled="freeMessageSaving" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-1.5 rounded-lg text-xs transition">
                                    <span x-show="!freeMessageSaving">Cadastrar</span>
                                    <span x-show="freeMessageSaving">Salvando...</span>
                                </button>
                            </div>
                            <div class="space-y-1.5">
                                <template x-for="m in freeMessages" :key="m.id">
                                    <div class="flex items-center justify-between bg-white border border-gray-200 rounded-lg px-3 py-2">
                                        <div>
                                            <p class="text-xs font-semibold text-gray-700" x-text="m.name"></p>
                                            <p class="text-[11px] text-gray-500" x-text="m.body"></p>
                                        </div>
                                        <button type="button" @click="deleteFreeMessage(m.id, m.name)" class="text-gray-400 hover:text-red-600 text-xs font-semibold shrink-0 ml-2">Excluir</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
@endsection
