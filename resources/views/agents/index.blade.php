@extends('layouts.app')

@section('title', 'Gestão de Equipe & Agentes')

@section('content')
        <div class="container mx-auto px-6 max-w-7xl py-8 flex flex-col h-[calc(100vh-8rem)]"
            x-data="{
                editDeptModal: false,
                editDeptData: { id: null, name: '' }
            }"
        >

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

            <div class="flex-1 min-h-0 flex flex-col">
                <!-- CABEÇALHO COM O FILTRO GLOBAL (STATUS E ASSISTENTE) -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 flex-1 min-h-0 flex flex-col">
                    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-4 border-b border-gray-100 pb-4 shrink-0">
                        <div>
                            <h1 class="text-xl font-bold text-gray-800">Gestão de Departamentos & Agentes</h1>
                            <p class="text-xs text-gray-500 mt-1">Organize os setores corporativos e os agentes humanos de suporte.</p>
                        </div>
                        
                        <!-- FILTRO GLOBAL DE ASSISTENTE + STATUS -->
                        <form id="assistantFilter" method="GET" action="/" class="flex flex-wrap items-center gap-3 bg-indigo-50 px-4 py-2 rounded-xl border border-indigo-100 shadow-sm">
                            <input type="hidden" name="view" value="equipe">
                            
                            <!-- COMBO: STATUS -->
                            <div class="flex items-center gap-2 border-r border-indigo-200 pr-3">
                                <span class="dark-force-white font-bold text-indigo-700 uppercase text-[10px] tracking-wide">Status:</span>
                                <select name="status" onchange="document.getElementById('assistantFilter').submit()" class="font-bold text-slate-800 bg-transparent focus:outline-none cursor-pointer text-sm">
                                    <option value="ativo" {{ $statusFilter == 'ativo' ? 'selected' : '' }}>Ativos</option>
                                    <option value="inativo" {{ $statusFilter == 'inativo' ? 'selected' : '' }}>Inativos</option>
                                    <option value="todos" {{ $statusFilter == 'todos' ? 'selected' : '' }}>Todos</option>
                                </select>
                            </div>

                            <!-- COMBO: ASSISTENTE -->
                            <div class="flex items-center gap-2">
                                <span class="dark-force-white font-bold text-indigo-700 uppercase text-[10px] tracking-wide">Assistente IA:</span>
                                <select name="assistant_id" onchange="document.getElementById('assistantFilter').submit()" class="font-bold text-slate-800 bg-transparent focus:outline-none cursor-pointer text-sm">
                                    @foreach($assistants as $ast)
                                        <option value="{{ $ast->id }}" {{ $selectedAssistantId == $ast->id ? 'selected' : '' }}>{{ $ast->name }}</option>
                                    @endforeach
                                    @if($assistants->isEmpty())
                                        <option value="">Nenhum assistente encontrado</option>
                                    @endif
                                </select>
                            </div>
                        </form>
                    </div>

                    @if(!$selectedAssistantId)
                        <div class="text-center py-10 text-gray-500 font-medium">Nenhum Assistente IA disponível neste filtro. Crie um assistente ou altere o status.</div>
                    @else
                        <!-- CRIAR DEPARTAMENTO -->
                        <form action="/?view=equipe" method="POST" class="mb-4 bg-gray-50 p-3 rounded-xl border border-gray-200 shrink-0">
                            @csrf
                            <input type="hidden" name="action" value="store_department">
                            <input type="hidden" name="assistant_id" value="{{ $selectedAssistantId }}">
                            <input type="hidden" name="status" value="{{ $statusFilter }}">
                            <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wide mb-1.5">Novo Departamento</label>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <!-- INPUT ONINPUT FORÇANDO MAIÚSCULA NA VIEW -->
                                <input type="text" name="name" required placeholder="Ex: COMERCIAL, TECNOLOGIA..." oninput="this.value = this.value.toUpperCase()" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none uppercase">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-2.5 rounded-lg text-xs transition shadow-sm whitespace-nowrap">+ Adicionar Departamento</button>
                            </div>
                        </form>

                        <!-- LISTA DE CARDS DE DEPARTAMENTOS (rolagem interna do quadro, não da página) -->
                        <div class="border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50/40 dark:bg-gray-900 p-4 flex-1 min-h-0 overflow-y-auto custom-scroll">
                        <div class="space-y-6">
                            @forelse($departments as $dept)
                                <div class="light-surface bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col md:flex-row transition hover:border-indigo-200">
                                    
                                    <!-- ESQUERDA: DEPARTAMENTO E CONTROLES (AGORA COM ÍCONES) -->
                                    <div class="md:w-1/4 bg-gray-50/80 p-5 border-b md:border-b-0 md:border-r border-gray-200 flex flex-col justify-between shrink-0">
                                        <div>
                                            <h3 class="font-bold text-gray-800 text-base flex items-center gap-2 uppercase tracking-wide">
                                                <span class="w-2.5 h-2.5 bg-indigo-500 rounded-full"></span>
                                                {{ $dept->name }}
                                            </h3>
                                        </div>
                                        <div class="flex items-center gap-2 mt-5">
                                            <!-- BOTÃO EDITAR DEPARTAMENTO COM ÍCONE -->
                                            <button @click="editDeptData = { id: {{ $dept->id }}, name: '{{ $dept->name }}' }; editDeptModal = true" class="flex-1 flex items-center justify-center gap-1.5 text-[10px] font-bold text-indigo-700 bg-indigo-100 hover:bg-indigo-200 px-2.5 py-2 rounded transition">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                                                Editar
                                            </button>
                                            <!-- BOTÃO EXCLUIR DEPARTAMENTO COM ÍCONE -->
                                            <form action="/?view=equipe" method="POST" class="flex-1" onsubmit="return confirm('Excluir departamento e TODOS os agentes vinculados?');">
                                                @csrf 
                                                <input type="hidden" name="action" value="delete_department">
                                                <input type="hidden" name="department_id" value="{{ $dept->id }}">
                                                <input type="hidden" name="assistant_id" value="{{ $selectedAssistantId }}">
                                                <input type="hidden" name="status" value="{{ $statusFilter }}">
                                                <button type="submit" class="w-full flex items-center justify-center gap-1.5 text-[10px] font-bold text-red-600 bg-red-100 hover:bg-red-200 px-2.5 py-2 rounded transition">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                                    Excluir
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- DIREITA: CARDS DOS AGENTES (rolagem interna do quadro, não da página) -->
                                    <div class="md:w-3/4 p-4 flex flex-wrap content-start gap-3 items-start bg-slate-50/30 max-h-64 overflow-y-auto custom-scroll">

                                        <!-- CARD 1: VINCULAR USUÁRIO EXISTENTE (Sempre na esquerda) -->
                                        <div class="bg-white border border-slate-200 border-dashed hover:border-indigo-400 rounded-lg p-2 w-36 shrink-0 shadow-sm relative transition group">
                                            <form action="/?view=equipe" method="POST" class="flex flex-col gap-1.5">
                                                @csrf
                                                <input type="hidden" name="action" value="link_user">
                                                <input type="hidden" name="department_id" value="{{ $dept->id }}">
                                                <input type="hidden" name="assistant_id" value="{{ $selectedAssistantId }}">
                                                <input type="hidden" name="status" value="{{ $statusFilter }}">
                                                <label class="block text-[9px] font-bold text-gray-500 uppercase mb-0.5">Vincular</label>
                                                <select name="user_id" required class="w-full border border-slate-300 rounded text-[10px] px-1.5 py-1 outline-none focus:border-indigo-500 text-gray-700 bg-white">
                                                    <option value="">Selecione...</option>
                                                    @foreach($eligibleUsers as $eu)
                                                        @if(!in_array($eu->id, $linkedUserIdsByDept[$dept->id] ?? []))
                                                            <option value="{{ $eu->id }}">{{ $eu->name }} ({{ $eu->role === 'gestor' ? 'Gestor' : 'Agente' }})</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="w-full bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold py-1 rounded border border-indigo-200 text-[9px] transition uppercase tracking-wide">+ Vincular</button>
                                            </form>
                                        </div>

                                        <!-- CARDS 2...N: AGENTES VINCULADOS -->
                                        @foreach($agents as $agent)
                                            @if($agent->department_id == $dept->id)
                                                <div class="bg-white border border-gray-200 rounded-lg p-2 w-52 shrink-0 shadow-sm relative group hover:border-indigo-300 transition flex flex-col justify-between">

                                                    <div class="flex items-center gap-2">
                                                        <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 font-bold text-xs flex items-center justify-center shrink-0 border border-indigo-200 uppercase">
                                                            {{ substr($agent->name, 0, 1) }}
                                                        </div>
                                                        <div class="pr-1 min-w-0">
                                                            <h4 class="font-bold text-gray-800 text-xs truncate">{{ $agent->name }}</h4>
                                                            <p class="text-[10px] text-gray-500 font-mono truncate mt-0.5">{{ $agent->email }}</p>
                                                        </div>
                                                    </div>

                                                    <!-- FOOTER DO CARD: Controles aparecem no hover -->
                                                    <div class="mt-2 pt-1.5 border-t border-gray-50 flex justify-end gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                                        <form action="/?view=equipe" method="POST" onsubmit="return confirm('Desvincular este usuário do departamento?');">
                                                            @csrf
                                                            <input type="hidden" name="action" value="unlink_user">
                                                            <input type="hidden" name="user_id" value="{{ $agent->id }}">
                                                            <input type="hidden" name="department_id" value="{{ $dept->id }}">
                                                            <input type="hidden" name="assistant_id" value="{{ $selectedAssistantId }}">
                                                            <input type="hidden" name="status" value="{{ $statusFilter }}">
                                                            <button type="submit" class="text-red-600 hover:bg-red-50 px-2 py-1 rounded text-[10px] font-bold flex items-center gap-1 transition">
                                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 12H6" /></svg> Desvincular
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-12 text-gray-400 font-medium">Nenhum departamento cadastrado para este Assistente.</div>
                            @endforelse
                        </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- MODAL EDITAR DEPARTAMENTO -->
            <div x-show="editDeptModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4" x-transition>
                <div @click.away="editDeptModal = false" class="bg-white rounded-xl shadow-2xl max-w-sm w-full p-6 relative border border-slate-200">
                    <h3 class="text-base font-bold text-gray-800 mb-4">Editar Departamento</h3>
                    <form action="/?view=equipe" method="POST">
                        @csrf
                        <input type="hidden" name="action" value="update_department">
                        <input type="hidden" name="assistant_id" value="{{ $selectedAssistantId }}">
                        <input type="hidden" name="status" value="{{ $statusFilter }}">
                        <input type="hidden" name="department_id" :value="editDeptData.id">
                        <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Nome do Departamento</label>
                        <input type="text" name="name" x-model="editDeptData.name" oninput="this.value = this.value.toUpperCase()" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-xs mb-5 outline-none focus:border-indigo-500 uppercase">
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="editDeptModal = false" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition">Cancelar</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg transition">Atualizar</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
@endsection