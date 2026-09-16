@extends('layouts.app')

@section('title', 'Pesquisas - ' . $assistant->name)

@section('content')
        <div class="container mx-auto px-6 max-w-6xl flex flex-col h-[calc(100vh-10rem)] pt-4">

            <div class="dark-card-invert shrink-0 bg-gray-50 py-4 mb-4 border-b border-gray-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <a href="{{ $editingSurvey ? '/?view=surveys&assistant_id=' . $assistant->id : '/' }}" class="dark-btn-fix text-indigo-600 hover:text-indigo-800 font-semibold flex items-center gap-1.5 text-sm transition bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg border border-indigo-100 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg> Voltar
                    </a>
                    <div class="h-6 w-px bg-gray-300 hidden sm:block"></div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">Pesquisas — {{ $assistant->name }}</h1>
                        <p class="text-xs text-gray-500 mt-0.5">Cadastre pesquisas de opinião que a Ingrid pode oferecer ao cliente durante o atendimento.</p>
                    </div>
                </div>
            </div>

            <div id="surveysScrollArea" class="flex-1 min-h-0 overflow-y-auto custom-scroll pr-1 pb-8">

                @if(session('success'))
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2 shadow-sm">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm flex items-center gap-2 shadow-sm">
                        <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                        {{ session('error') }}
                    </div>
                @endif

                @if($editingSurvey)
                    {{-- ====================== DETALHE DA PESQUISA ====================== --}}
                    <div x-data="{ showResponsesModal: false }">
                    <div class="flex items-center gap-3 mb-5">
                        <a href="/?view=surveys&assistant_id={{ $assistant->id }}" class="text-gray-400 hover:text-indigo-600 transition" title="Voltar para a lista de pesquisas">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                        </a>
                        <h2 class="text-lg font-bold text-gray-800">{{ $editingSurvey->name }}</h2>
                        <span class="text-[11px] font-mono font-bold text-indigo-600 bg-indigo-50 border border-indigo-100 px-2 py-0.5 rounded">[{{ $editingSurvey->tag }}]</span>
                        <button type="button" @click="showResponsesModal = true" class="ml-auto bg-white border border-gray-200 hover:border-indigo-300 hover:text-indigo-700 text-gray-600 font-bold py-1.5 px-3 rounded-lg text-xs transition flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                            Ver Respostas ({{ $responses->count() }})
                        </button>
                    </div>

                    <div class="dark-card-invert bg-white p-5 rounded-xl shadow-sm border border-gray-200 mb-6">
                        <h3 class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-3">Dados da Pesquisa</h3>
                        <form action="/" method="POST" class="grid grid-cols-1 md:grid-cols-[1fr_1fr_auto_auto] gap-3 items-end" onsubmit="saveSurveyScrollPosition()">
                            @csrf
                            <input type="hidden" name="view" value="surveys">
                            <input type="hidden" name="action" value="update_survey">
                            <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">
                            <input type="hidden" name="survey_id" value="{{ $editingSurvey->id }}">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Nome</label>
                                <input type="text" name="name" value="{{ $editingSurvey->name }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Tag (usada no prompt)</label>
                                <input type="text" name="tag" value="{{ $editingSurvey->tag }}" required pattern="[A-Za-z0-9_]+" title="Só letras, números e underline" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono outline-none focus:border-indigo-500">
                            </div>
                            <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 pb-2.5 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" {{ $editingSurvey->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                Ativa
                            </label>
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold py-2.5 px-4 rounded-lg transition">Salvar</button>
                            <div class="md:col-span-4">
                                <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Quando usar (contexto para a IA, opcional)</label>
                                <input type="text" name="trigger_context" value="{{ $editingSurvey->trigger_context }}" placeholder="Ex: usar apenas ao encerrar atendimentos de pós-venda/suporte" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500">
                            </div>
                        </form>
                        <p class="text-[11px] text-gray-400 mt-2">Use <code class="bg-gray-100 px-1 rounded">[{{ $editingSurvey->tag }}]</code> no prompt do assistente para oferecer essa pesquisa ao cliente. Se houver mais de uma pesquisa ativa, preencha "Quando usar" para a IA saber escolher a certa.</p>
                    </div>

                    <div class="dark-card-invert bg-white p-5 rounded-xl shadow-sm border border-gray-200">
                        <h3 class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-4">Perguntas</h3>

                        <div class="space-y-4 mb-5">
                            @forelse($editingSurvey->questions as $question)
                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-start justify-between gap-3 mb-2">
                                        <form action="/" method="POST" class="flex-1 flex flex-col gap-2" onsubmit="saveSurveyScrollPosition()">
                                            @csrf
                                            <input type="hidden" name="view" value="surveys">
                                            <input type="hidden" name="action" value="update_question">
                                            <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">
                                            <input type="hidden" name="survey_id" value="{{ $editingSurvey->id }}">
                                            <input type="hidden" name="question_id" value="{{ $question->id }}">

                                            <div class="flex flex-col sm:flex-row gap-2">
                                                <input type="text" name="question_text" value="{{ $question->question_text }}" required class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-indigo-500">
                                                <select name="type" class="border border-gray-300 rounded-lg px-2 py-2 text-xs font-semibold outline-none focus:border-indigo-500">
                                                    <option value="multiple_choice" {{ $question->type === 'multiple_choice' ? 'selected' : '' }}>Múltipla Escolha</option>
                                                    <option value="free_text" {{ $question->type === 'free_text' ? 'selected' : '' }}>Texto Livre</option>
                                                </select>
                                            </div>

                                            @if($question->type === 'multiple_choice')
                                                <div class="pl-1 space-y-1.5">
                                                    @foreach($question->options as $option)
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-gray-300">•</span>
                                                            <input type="text" name="options[{{ $option->id }}]" value="{{ $option->option_text }}" class="flex-1 border border-gray-200 rounded-md px-2.5 py-1.5 text-xs outline-none focus:border-indigo-500">
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif

                                            <div>
                                                <button type="submit" class="bg-white border border-gray-300 hover:border-indigo-300 hover:text-indigo-700 text-gray-600 text-xs font-bold px-3 py-2 rounded-lg transition">Salvar</button>
                                            </div>
                                        </form>
                                        <form action="/" method="POST" onsubmit="if(!confirm('Remover esta pergunta?'))return false; saveSurveyScrollPosition();">
                                            @csrf
                                            <input type="hidden" name="view" value="surveys">
                                            <input type="hidden" name="action" value="delete_question">
                                            <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">
                                            <input type="hidden" name="survey_id" value="{{ $editingSurvey->id }}">
                                            <input type="hidden" name="question_id" value="{{ $question->id }}">
                                            <button type="submit" class="text-gray-400 hover:text-red-500 hover:bg-red-50 p-2 rounded-lg transition shrink-0" title="Remover pergunta">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        </form>
                                    </div>

                                    @if($question->type === 'multiple_choice')
                                        <div class="pl-1 flex flex-wrap items-center gap-1.5">
                                            @foreach($question->options as $option)
                                                <form action="/" method="POST" onsubmit="saveSurveyScrollPosition()" class="inline-flex">
                                                    @csrf
                                                    <input type="hidden" name="view" value="surveys">
                                                    <input type="hidden" name="action" value="delete_option">
                                                    <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">
                                                    <input type="hidden" name="survey_id" value="{{ $editingSurvey->id }}">
                                                    <input type="hidden" name="option_id" value="{{ $option->id }}">
                                                    <button type="submit" class="text-[11px] text-gray-400 hover:text-red-500 border border-gray-200 hover:border-red-300 rounded px-1.5 py-0.5 transition flex items-center gap-1" title="Remover esta opção">
                                                        <span>{{ \Illuminate\Support\Str::limit($option->option_text, 18) }}</span>
                                                        <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                                    </button>
                                                </form>
                                            @endforeach

                                            <form action="/" method="POST" class="flex gap-1.5" onsubmit="saveSurveyScrollPosition()">
                                                @csrf
                                                <input type="hidden" name="view" value="surveys">
                                                <input type="hidden" name="action" value="add_option">
                                                <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">
                                                <input type="hidden" name="survey_id" value="{{ $editingSurvey->id }}">
                                                <input type="hidden" name="question_id" value="{{ $question->id }}">
                                                <input type="text" name="option_text" placeholder="Nova opção" required class="w-32 border border-dashed border-gray-300 rounded-md px-2 py-1 text-[11px] outline-none focus:border-indigo-500">
                                                <button type="submit" class="text-indigo-600 hover:text-indigo-800 text-[11px] font-bold px-1">+ Adicionar</button>
                                            </form>
                                        </div>
                                    @else
                                        <p class="text-[11px] text-gray-400 italic pl-1">Resposta livre — o cliente digita o texto.</p>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-gray-400 text-center py-6">Nenhuma pergunta cadastrada ainda.</p>
                            @endforelse
                        </div>

                        <form action="/" method="POST" class="flex flex-col sm:flex-row gap-2 border-t border-gray-100 pt-4" onsubmit="saveSurveyScrollPosition()">
                            @csrf
                            <input type="hidden" name="view" value="surveys">
                            <input type="hidden" name="action" value="add_question">
                            <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">
                            <input type="hidden" name="survey_id" value="{{ $editingSurvey->id }}">
                            <input type="text" name="question_text" placeholder="Digite a nova pergunta" required class="flex-1 border border-gray-300 rounded-lg px-3 py-2.5 text-sm outline-none focus:border-indigo-500">
                            <select name="type" class="border border-gray-300 rounded-lg px-3 py-2.5 text-xs font-semibold outline-none focus:border-indigo-500">
                                <option value="multiple_choice">Múltipla Escolha</option>
                                <option value="free_text">Texto Livre</option>
                            </select>
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold py-2.5 px-5 rounded-lg transition shrink-0">+ Adicionar Pergunta</button>
                        </form>
                    </div>

                    <div x-show="showResponsesModal" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
                        <div @click.away="showResponsesModal = false" class="dark-card-invert bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[85vh] flex flex-col border border-slate-200">
                            <div class="flex items-center justify-between p-5 border-b border-gray-100 shrink-0">
                                <h3 class="text-base font-bold text-gray-800">Respostas Recebidas ({{ $responses->count() }})</h3>
                                <div class="flex items-center gap-2">
                                    @if($responses->isNotEmpty())
                                        <a href="/?view=surveys&assistant_id={{ $assistant->id }}&survey_id={{ $editingSurvey->id }}&export=csv" class="bg-white border border-gray-200 hover:border-emerald-300 hover:text-emerald-700 text-gray-600 font-bold py-1.5 px-3 rounded-lg text-xs transition flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                            Exportar CSV
                                        </a>
                                    @endif
                                    <button type="button" @click="showResponsesModal = false" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg transition">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="flex-1 min-h-0 overflow-y-auto custom-scroll p-5">
                                @forelse($responses as $response)
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-3">
                                        <div class="flex items-center justify-between mb-2 text-xs">
                                            <span class="font-bold text-gray-700">{{ $response->client_name ?: $response->phone_number }}</span>
                                            <span class="text-gray-400">{{ $response->phone_number }} • {{ optional($response->completed_at)->format('d/m/Y H:i') }}</span>
                                        </div>
                                        <div class="space-y-1.5">
                                            @foreach($editingSurvey->questions as $q)
                                                @php($answer = $response->answers->firstWhere('survey_question_id', $q->id))
                                                <div class="text-xs">
                                                    <span class="text-gray-500">{{ $q->question_text }}:</span>
                                                    <span class="text-gray-800 font-medium">{{ $answer?->answer_text ?? '—' }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-400 text-center py-6">Nenhuma resposta recebida ainda.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    </div>

                @else
                    {{-- ====================== LISTA DE PESQUISAS ====================== --}}
                    <div class="dark-card-invert bg-white p-3 rounded-xl shadow-sm border border-gray-200 mb-4">
                        <h3 class="text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-2">Nova Pesquisa</h3>
                        <form action="/" method="POST" class="grid grid-cols-1 md:grid-cols-[1fr_1fr_auto] gap-2">
                            @csrf
                            <input type="hidden" name="view" value="surveys">
                            <input type="hidden" name="action" value="store_survey">
                            <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">
                            <input type="text" name="name" placeholder="Nome (ex: Pesquisa de Satisfação)" required class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs outline-none focus:border-indigo-500">
                            <input type="text" name="tag" placeholder="Tag (ex: PESQUISA_SATISFACAO)" required pattern="[A-Za-z0-9_]+" title="Só letras, números e underline" class="w-full border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-mono outline-none focus:border-indigo-500">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold py-1.5 px-4 rounded-lg transition whitespace-nowrap">+ Criar Pesquisa</button>
                            <input type="text" name="trigger_context" placeholder="Quando usar (opcional - só precisa se tiver mais de uma pesquisa ativa)" class="w-full md:col-span-3 border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs outline-none focus:border-indigo-500">
                        </form>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @forelse($surveys as $survey)
                            <div class="dark-card-invert bg-white p-3 rounded-xl shadow-sm border border-gray-200 flex flex-col gap-1.5">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <h3 class="font-bold text-gray-800 truncate">{{ $survey->name }}</h3>
                                            <span class="text-[11px] text-gray-400 shrink-0">· {{ $survey->questions()->count() }} pergunta(s)</span>
                                        </div>
                                        <span class="text-[11px] font-mono text-indigo-600">[{{ $survey->tag }}]</span>
                                    </div>
                                    <span class="text-[11px] px-2.5 py-1 rounded-full font-semibold shrink-0 {{ $survey->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-600 border border-gray-300' }}">
                                        {{ $survey->is_active ? 'Ativa' : 'Inativa' }}
                                    </span>
                                </div>
                                @if($survey->trigger_context)
                                    <p class="text-[11px] text-gray-400 italic truncate" title="{{ $survey->trigger_context }}">Uso: {{ $survey->trigger_context }}</p>
                                @endif
                                <div class="flex items-center justify-between border-t border-gray-100 pt-2">
                                    <a href="/?view=surveys&assistant_id={{ $assistant->id }}&survey_id={{ $survey->id }}" class="bg-white border border-gray-200 hover:border-indigo-300 hover:text-indigo-700 text-gray-600 font-bold py-1.5 px-3 rounded-lg text-xs transition">
                                        Configurar Perguntas
                                    </a>
                                    <form action="/" method="POST" onsubmit="return confirm('Excluir esta pesquisa e todas as perguntas dela?');">
                                        @csrf
                            <input type="hidden" name="view" value="surveys">
                                        <input type="hidden" name="action" value="delete_survey">
                                        <input type="hidden" name="assistant_id" value="{{ $assistant->id }}">
                                        <input type="hidden" name="survey_id" value="{{ $survey->id }}">
                                        <button type="submit" class="text-gray-400 hover:text-red-500 hover:bg-red-50 p-2 rounded-lg transition" title="Excluir">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full text-center py-12 text-gray-400">Nenhuma pesquisa cadastrada ainda.</div>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>

        <script>
            function saveSurveyScrollPosition() {
                const scrollArea = document.getElementById('surveysScrollArea');
                if (scrollArea) {
                    sessionStorage.setItem('scrollpos_surveys_{{ $editingSurvey->id ?? $assistant->id }}', scrollArea.scrollTop);
                }
            }

            document.addEventListener("DOMContentLoaded", function() {
                const key = 'scrollpos_surveys_{{ $editingSurvey->id ?? $assistant->id }}';
                const scrollpos = sessionStorage.getItem(key);
                if (scrollpos !== null) {
                    setTimeout(() => {
                        const scrollArea = document.getElementById('surveysScrollArea');
                        if (scrollArea) {
                            scrollArea.scrollTop = parseInt(scrollpos);
                        }
                    }, 50);
                    sessionStorage.removeItem(key);
                }
            });
        </script>
@endsection
