<div>
    {{-- Backdrop (fecha ao clicar fora) --}}
    <div
        class="fixed inset-0 bg-black/30 backdrop-blur-sm z-40"
        wire:click="toggle"
        style="display: {{ $isOpen ? 'block' : 'none' }};"
    ></div>

    {{-- Chat Panel (lateral direita) --}}
    <div
        class="fixed right-0 top-0 h-full w-full max-w-2xl bg-white dark:bg-gray-900 shadow-2xl flex flex-col z-50"
        style="display: {{ $isOpen ? 'flex' : 'none' }};"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-amber-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white">Assistente de Prospecção</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $totalProspects }} prospects no total</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                {{-- Quick stats --}}
                <div class="hidden sm:flex items-center gap-3 text-xs px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded-lg">
                    <span class="text-gray-500 dark:text-gray-400">Aguardando:</span>
                    <span class="font-medium text-amber-600 dark:text-amber-400">{{ $prospectsAguardando }}</span>
                    <span class="text-gray-500 dark:text-gray-400">| Sem contato:</span>
                    <span class="font-medium text-red-600 dark:text-red-400">{{ $prospectsSemContato }}</span>
                    <span class="text-gray-500 dark:text-gray-400">| EPP não contatados:</span>
                    <span class="font-medium text-blue-600 dark:text-blue-400">{{ $prospectsEppNaoContatados }}</span>
                </div>
                <button
                    wire:click="toggle"
                    class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                    title="Fechar"
                >
                    <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Messages Area --}}
        <div class="flex-1 overflow-y-auto p-4 space-y-4" id="messages-container">
            @foreach ($messages as $index => $message)
                <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[85%] {{ $message['role'] === 'user' ? 'bg-orange-500 text-white rounded-2xl rounded-tr-sm' : 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white rounded-2xl rounded-tl-sm' }}">
                        <div class="px-4 py-2 whitespace-pre-wrap">{{ $message['content'] }}</div>

                        {{-- Download da lista: toda resposta com dados tem seus proprios botoes --}}
                        @if (! empty($message['data']))
                            <div class="px-3 pb-3 flex flex-wrap gap-2">
                                <button type="button" wire:click="exportExcel({{ $index }})" wire:loading.attr="disabled" wire:target="exportExcel"
                                    class="px-4 py-2 text-sm font-semibold bg-green-700 hover:bg-green-800 text-white rounded-lg transition-colors flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    Baixar Excel ({{ count($message['data']) }})
                                </button>
                                <button type="button" wire:click="exportCsv({{ $index }})" wire:loading.attr="disabled" wire:target="exportCsv"
                                    class="px-4 py-2 text-sm font-semibold bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    Baixar CSV
                                </button>
                            </div>
                        @endif

                        {{-- Insights --}}
                        @if (! empty($message['insights']))
                            <div class="px-3 pb-2">
                                <details class="text-xs">
                                    <summary class="cursor-pointer opacity-70 hover:opacity-100 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
                                        Insights
                                    </summary>
                                    <ul class="mt-1 space-y-1 pl-4 list-disc">
                                        @foreach ($message['insights'] as $insight)
                                            <li class="text-[11px] opacity-80">{{ $insight }}</li>
                                        @endforeach
                                    </ul>
                                </details>
                            </div>
                        @endif

                        {{-- Suggested actions --}}
                        @if (! empty($message['actions']))
                            <div class="px-3 pb-2">
                                <details class="text-xs">
                                    <summary class="cursor-pointer opacity-70 hover:opacity-100 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                                        Ações sugeridas
                                    </summary>
                                    <ul class="mt-1 space-y-1 pl-4 list-disc">
                                        @foreach ($message['actions'] as $action)
                                            <li class="text-[11px] opacity-80">{{ $action }}</li>
                                        @endforeach
                                    </ul>
                                </details>
                            </div>
                        @endif

                        {{-- Data table preview --}}
                        @if (! empty($message['data']))
                            <div class="px-3 pb-2">
                                <details class="text-xs">
                                    <summary class="cursor-pointer opacity-70 hover:opacity-100 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        {{ count($message['data']) }} resultados (clique para ver)
                                    </summary>
                                    <div class="mt-2 overflow-x-auto max-h-48">
                                        <table class="w-full text-[10px] border border-gray-200 dark:border-gray-700 rounded">
                                            <thead class="bg-gray-100 dark:bg-gray-800">
                                                <tr>
                                                    @foreach (array_keys($message['data'][0]) as $col)
                                                        <th class="px-2 py-1 text-left font-medium">{{ $col }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach (array_slice($message['data'], 0, 10) as $row)
                                                    <tr class="border-t border-gray-200 dark:border-gray-700">
                                                        @foreach ($row as $val)
                                                            <td class="px-2 py-1">{{ $val }}</td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                                @if (count($message['data']) > 10)
                                                    <tr>
                                                        <td colspan="{{ count(array_keys($message['data'][0])) }}" class="px-2 py-1 text-center text-gray-500 dark:text-gray-400">
                                                            +{{ count($message['data']) - 10 }} mais...
                                                        </td>
                                                    </tr>
                                                @endif
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            {{-- Indicador de "trabalhando": wire:loading acompanha a requisicao
                 (isLoading volta a false na mesma request, entao nao serve) --}}
            <div wire:loading.flex wire:target="send,handleSendQuestion" class="justify-start">
                <div
                    x-data="{ i: 0, frases: ['Analisando a pergunta...', 'Consultando os prospects...', 'Montando a resposta...', 'Quase lá...'] }"
                    x-init="setInterval(() => i = (i + 1) % frases.length, 2500)"
                    class="bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white rounded-2xl rounded-tl-sm px-4 py-3 flex items-center gap-3"
                >
                    <div class="flex gap-1">
                        <div class="w-2 h-2 bg-orange-500 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                        <div class="w-2 h-2 bg-orange-500 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                        <div class="w-2 h-2 bg-orange-500 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                    </div>
                    <span class="text-sm animate-pulse" x-text="frases[i]">Analisando...</span>
                </div>
            </div>
        </div>

        {{-- Quick Action Buttons --}}
        <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex-shrink-0">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Perguntas rápidas:</p>
            <div class="flex flex-wrap gap-2">
                @foreach([
                    'Quais prospects são EPP e não contatei ainda?' => 'epp-nao-contatados',
                    'Quais estão aguardando retorno?' => 'aguardando-retorno',
                    'Qual o status dos prospects?' => 'status-prospects',
                    'Prospects com nota >= 8 em Campinas' => 'nota-alta-campinas',
                    'Quantos prospects por lote?' => 'por-lote',
                    'Prospects sem interação nenhuma' => 'sem-interacao',
                ] as $label => $key)
                    <button
                        wire:click="handleSendQuestion('{{ $label }}')"
                        class="text-xs px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-full hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors whitespace-nowrap"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Input Area --}}
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex-shrink-0">
            <div class="flex gap-2">
                <input
                    type="text"
                    wire:model="question"
                    wire:keydown.enter="send"
                    placeholder="Digite sua pergunta sobre os prospects..."
                    class="flex-1 px-4 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    @disabled($isLoading)
                    autofocus
                >
                <button
                    wire:click="send"
                    @disabled($isLoading)
                    class="px-5 py-2 bg-orange-500 hover:bg-orange-600 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl font-medium text-sm transition-colors flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                    <span class="hidden sm:inline">Enviar</span>
                </button>
            </div>

            {{-- Export buttons (show when there's data) --}}
            @if (! empty($lastData))
                <div class="mt-2 flex gap-2">
                    <button
                        wire:click="exportCsv"
                        class="px-3 py-1.5 text-xs bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors flex items-center gap-1"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        CSV
                    </button>
                    <button
                        wire:click="exportExcel"
                        class="px-3 py-1.5 text-xs bg-green-700 hover:bg-green-800 text-white rounded-lg transition-colors flex items-center gap-1"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Excel
                    </button>
                    <button
                        wire:click="clearChat"
                        class="px-3 py-1.5 text-xs bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors flex items-center gap-1 ml-auto"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        Limpar
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Gatilho (fechado): barra no rodape direito, no estilo do chat do app --}}
    @if (! $isOpen)
        <button
            type="button"
            wire:click="toggle"
            class="fixed bottom-0 right-6 z-[9999] flex items-center gap-3 pl-4 pr-5 py-3 rounded-t-2xl bg-gradient-to-r from-orange-500 to-amber-600 text-white shadow-2xl hover:from-orange-600 hover:to-amber-700 transition-colors"
            title="Abrir Assistente de Prospecção"
        >
            <span class="relative flex items-center justify-center w-10 h-10 rounded-full bg-white/20">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                @if ($prospectsAguardando > 0 || $prospectsSemContato > 0)
                    <span class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">
                        {{ $prospectsAguardando + $prospectsSemContato }}
                    </span>
                @endif
            </span>
            <span class="text-base font-semibold whitespace-nowrap">Assistente de Prospecção</span>
        </button>
    @endif
</div>

@push('scripts')
<script>
    // Auto-scroll para a última mensagem
    document.addEventListener('livewire:load', () => {
        Livewire.on('message-added', () => {
            const container = document.getElementById('messages-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        });
    });

    // Fecha chat ao pressionar Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const chat = document.querySelector('[wire\\:id]');
            if (chat) {
                Livewire.dispatch('close-chat');
            }
        }
    });
</script>
@endpush