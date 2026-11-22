<div>
    {{-- Backdrop (fecha ao clicar fora) --}}
    @unless ($fullscreen || $embutido)
        <div
            class="fixed inset-0 bg-black/30 backdrop-blur-sm z-40"
            wire:click="toggle"
            style="display: {{ $isOpen ? 'block' : 'none' }};"
        ></div>
    @endunless

    {{-- Chat Panel (lateral direita) --}}
    <div
        class="{{ $fullscreen ? 'fixed inset-0 w-full' : ($embutido ? 'relative w-full rounded-xl overflow-hidden ring-1 ring-gray-200 dark:ring-white/10' : 'fixed right-0 top-0 h-full w-full max-w-2xl shadow-2xl') }} bg-white dark:bg-gray-900 flex flex-col {{ $embutido ? '' : 'z-50' }}"
        @if ($fullscreen) data-tema style="display: flex; height: 100dvh; --ac: {{ $tema['cor'] }}; --ac-d: {{ $tema['escura'] }}; --fundo: {{ $tema['fundo'] }};" @elseif ($embutido) data-tema-i style="display: flex; height: 640px; --ac: {{ $tema['cor'] }}; --ac-d: {{ $tema['escura'] }};" @else style="display: {{ $isOpen ? 'flex' : 'none' }};" @endif
    >
        {{-- Header --}}
        <div class="tema-cab flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex-shrink-0"
             @if ($fullscreen) style="padding-top: max(0.75rem, env(safe-area-inset-top));" @endif>
            <div class="flex items-center gap-3">
                @if ($fullscreen)
                    {{-- App do celular (Oravel Mobile): icone da Oravel no topo --}}
                    <img src="{{ asset('icon.png') }}" alt="Oravel" class="w-10 h-10 rounded-xl ring-2 ring-white/70">
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white leading-tight">Oravel Mobile</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $tituloApp }} · {{ $subtituloApp }}</p>
                    </div>
                @elseif ($embutido)
                    <img src="{{ asset($tema['icone']) }}" alt="" class="w-10 h-10 rounded-xl ring-2 ring-white/70">
                    <div>
                        <h3 class="font-bold leading-tight">{{ $tituloApp }}</h3>
                        <p class="text-xs">Pergunte sobre a saúde e a evolução da empresa · {{ $subtituloApp }}</p>
                    </div>
                @else
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-amber-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ $tituloApp }}</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $subtituloApp }}</p>
                </div>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @if ($mostrarStats)
                {{-- Quick stats --}}
                <div class="hidden sm:flex items-center gap-3 text-xs px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded-lg">
                    <span class="text-gray-500 dark:text-gray-400">Aguardando:</span>
                    <span class="font-medium text-amber-600 dark:text-amber-400">{{ $prospectsAguardando }}</span>
                    <span class="text-gray-500 dark:text-gray-400">| Sem contato:</span>
                    <span class="font-medium text-red-600 dark:text-red-400">{{ $prospectsSemContato }}</span>
                    <span class="text-gray-500 dark:text-gray-400">| EPP não contatados:</span>
                    <span class="font-medium text-blue-600 dark:text-blue-400">{{ $prospectsEppNaoContatados }}</span>
                </div>
                @endif
                @unless ($fullscreen || $embutido)
                <button
                    wire:click="toggle"
                    class="p-2 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                    title="Fechar"
                >
                    <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                @endunless
            </div>
        </div>

        {{-- Messages Area --}}
        <div class="flex-1 overflow-y-auto p-4 space-y-4" id="messages-container">
            @foreach ($messages as $index => $message)
                <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[85%] {{ $message['role'] === 'user' ? 'tema-user bg-orange-500 text-white rounded-2xl rounded-tr-sm' : 'tema-bolha bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white rounded-2xl rounded-tl-sm' }}">
                        <div class="px-4 py-2 whitespace-pre-wrap">{{ $message['content'] }}</div>

                        {{-- Download da lista: toda resposta com dados tem seus proprios botoes --}}
                        @if (! empty($message['data']))
                            <div class="px-3 pb-3 flex flex-wrap gap-2">
                                <button type="button" wire:click="exportExcel({{ $index }})" wire:loading.attr="disabled" wire:target="exportExcel"
                                    class="px-4 py-2 text-sm font-semibold bg-green-700 hover:bg-green-800 text-white rounded-lg transition-colors flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    Baixar Excel ({{ count($message['data']) }})
                                </button>
                                @if (! empty($message['relatorio']))
                                    <a href="{{ $message['relatorio']['url'] }}" target="_blank" rel="noopener"
                                        class="tema-send px-4 py-2 text-sm font-semibold bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition-colors flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        Abrir relatório
                                    </a>
                                    <a href="{{ $message['relatorio']['whatsapp'] }}" target="_blank" rel="noopener"
                                        class="px-4 py-2 text-sm font-semibold bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2a9.9 9.9 0 00-8.5 14.9L2 22l5.25-1.5A9.9 9.9 0 1012.04 2zm5.8 14.1c-.25.7-1.45 1.35-2 1.4-.5.05-1.1.2-3.7-.8-3.1-1.3-5.1-4.5-5.25-4.7-.15-.2-1.25-1.65-1.25-3.15s.8-2.25 1.1-2.55c.3-.3.6-.35.8-.35h.6c.2 0 .45-.05.7.55.25.6.85 2.1.95 2.25.1.15.15.35.05.55-.1.2-.15.3-.3.5-.15.15-.3.35-.45.5-.15.15-.3.3-.15.6.15.3.7 1.15 1.5 1.85 1.05.9 1.9 1.2 2.2 1.35.3.15.45.1.65-.1.2-.2.75-.85.95-1.15.2-.3.4-.25.65-.15.25.1 1.65.8 1.95.95.3.15.5.2.55.3.05.1.05.65-.2 1.35z"/></svg>
                                        WhatsApp
                                    </a>
                                @else
                                <button type="button" wire:click="exportCsv({{ $index }})" wire:loading.attr="disabled" wire:target="exportCsv"
                                    class="px-4 py-2 text-sm font-semibold bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    Baixar CSV
                                </button>
                                @endif
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
            <div wire:loading.flex wire:target="send,handleSendQuestion" class="flex-col gap-3">
                {{-- Eco imediato da pergunta digitada (o servidor so adiciona a mensagem quando responde) --}}
                <div x-data x-show="$wire.question && $wire.question.trim() !== ''" x-cloak class="flex justify-end">
                    <div class="tema-user max-w-[85%] bg-orange-500 text-white rounded-2xl rounded-tr-sm px-4 py-2 whitespace-pre-wrap" x-text="$wire.question"></div>
                </div>
                <div
                    x-data="{ i: 0, frases: ['Analisando a pergunta...', 'Consultando os dados...', 'Montando a resposta...', 'Quase lá...'] }"
                    x-init="setInterval(() => i = (i + 1) % frases.length, 2500)"
                    class="tema-bolha self-start bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white rounded-2xl rounded-tl-sm px-4 py-3 flex items-center gap-3"
                >
                    <div class="flex gap-1">
                        <div class="tema-ponto w-2 h-2 bg-orange-500 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                        <div class="tema-ponto w-2 h-2 bg-orange-500 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                        <div class="tema-ponto w-2 h-2 bg-orange-500 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                    </div>
                    <span class="text-sm animate-pulse" x-text="frases[i]">Analisando...</span>
                </div>
            </div>
        </div>

        {{-- Sugestoes so no desktop: no app do celular (fullscreen) tiravam espaco do campo de digitar.
             Embutido: so mostra se a area informou as proprias ($sugestoes); as de Prospects nao servem para outras areas. --}}
        @if (! $fullscreen && (! $embutido || ! empty($sugestoes)))
        <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex-shrink-0">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Perguntas rápidas:</p>
            <div class="flex gap-2 {{ $fullscreen ? 'flex-nowrap overflow-x-auto pb-1' : 'flex-wrap' }}">
                @foreach($sugestoes ?? [
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
        @endif

        {{-- Input Area --}}
        <div class="tema-barra px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex-shrink-0"
             @if ($fullscreen) style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));" @endif>
            <div class="flex gap-2">
                <input
                    type="text"
                    wire:model="question"
                    wire:keydown.enter="send"
                    placeholder="{{ $placeholder }}"
                    class="flex-1 px-4 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-xl {{ $fullscreen ? 'text-base' : 'text-sm' }} focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                    @disabled($isLoading)
                    @if (! $embutido) autofocus @endif
                >
                <button
                    wire:click="send"
                    @disabled($isLoading)
                    class="tema-send px-5 py-2 bg-orange-500 hover:bg-orange-600 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl font-medium text-sm transition-colors flex items-center gap-2"
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
    @if (! $isOpen && ! $fullscreen && ! $embutido)
        <button
            type="button"
            wire:click="toggle"
            class="fixed bottom-0 right-6 z-[9999] flex items-center gap-3 pl-4 pr-5 py-3 rounded-t-2xl bg-gradient-to-r from-orange-500 to-amber-600 text-white shadow-2xl hover:from-orange-600 hover:to-amber-700 transition-colors"
            title="Abrir {{ $tituloApp }}"
        >
            <span class="relative flex items-center justify-center w-10 h-10 rounded-full bg-white/20">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                @if ($badge > 0)
                    <span class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">
                        {{ $badge }}
                    </span>
                @endif
            </span>
            <span class="text-base font-semibold whitespace-nowrap">{{ $tituloApp }}</span>
        </button>
    @endif
    @script
    <script>
        // Rola a lista de mensagens pro fim sempre que ela muda (pergunta enviada,
        // bolinhas "pensando" aparecendo/sumindo, resposta chegando).
        const box = () => document.getElementById('messages-container');
        const descer = () => requestAnimationFrame(() => {
            const el = box();
            if (el) el.scrollTop = el.scrollHeight;
        });
        const el = box();
        if (el) {
            // So reage a mudancas que importam: mensagem nova (filhos diretos) e o
            // indicador "pensando" (wire:loading) ligando/desligando. Ignora o texto
            // da frase que troca a cada 2,5 s, senao puxaria a tela pra baixo o tempo todo.
            new MutationObserver((muts) => {
                if (muts.some((m) => m.target === el || m.target.hasAttribute?.('wire:target'))) descer();
            }).observe(el, { childList: true, subtree: true, attributes: true, attributeFilter: ['style'] });
        }
        // O painel abre/fecha por display: rola de novo quando o chat e' aberto.
        $wire.$watch('isOpen', descer);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && $wire.isOpen && !$wire.fullscreen && !$wire.embutido) $wire.toggle();
        });
        descer();
    </script>
    @endscript

    @if ($embutido)
        {{-- Embutido (desktop): só a cor de destaque da área; o fundo segue o claro/escuro do painel. --}}
        <style>
            [data-tema-i] .tema-cab { background: var(--ac) !important; border-color: var(--ac-d) !important; }
            [data-tema-i] .tema-cab h3, [data-tema-i] .tema-cab p { color: #fff !important; }
            [data-tema-i] .tema-cab p { opacity: .85; }
            [data-tema-i] .tema-user { background: var(--ac) !important; color: #fff !important; }
            [data-tema-i] .tema-ponto { background: var(--ac) !important; }
            [data-tema-i] .tema-send { background: var(--ac) !important; }
            [data-tema-i] .tema-send:hover { background: var(--ac-d) !important; }
            [data-tema-i] .tema-barra input:focus { box-shadow: 0 0 0 2px var(--ac) !important; border-color: var(--ac) !important; }
        </style>
    @endif

    @if ($fullscreen)
        {{-- Tema do app do celular: cor propria da area (cada chat instalado tem a sua), sem branco.
             Escopo [data-tema]: o chat da Central (desktop) nao e' afetado. --}}
        <style>
            [data-tema] { background: var(--fundo) !important; }
            [data-tema] .tema-cab { background: var(--ac) !important; border-color: var(--ac-d) !important; }
            [data-tema] .tema-cab h3, [data-tema] .tema-cab p { color: #fff !important; }
            [data-tema] .tema-cab p { opacity: .85; }
            [data-tema] .tema-user { background: var(--ac) !important; color: #fff !important; }
            [data-tema] .tema-bolha { background: #fff !important; color: #111827 !important; box-shadow: 0 1px 2px rgba(0,0,0,.08); }
            [data-tema] .tema-ponto { background: var(--ac) !important; }
            [data-tema] .tema-send { background: var(--ac) !important; }
            [data-tema] .tema-send:hover { background: var(--ac-d) !important; }
            [data-tema] .tema-barra { background: var(--fundo) !important; border-color: var(--ac) !important; }
            [data-tema] .tema-barra input { border-color: var(--ac) !important; }
            [data-tema] .tema-barra input:focus { box-shadow: 0 0 0 2px var(--ac) !important; }
        </style>
    @endif
</div>
