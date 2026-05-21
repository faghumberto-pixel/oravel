<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($this->events as $event)
                <a href="{{ $event['url'] }}" class="block p-6 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm hover:shadow-md hover:border-amber-500 dark:hover:border-amber-500 transition duration-300 group relative overflow-hidden">
                    
                    {{-- Detalhe estético: Barra lateral que responde ao toque/hover --}}
                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-amber-500 opacity-0 group-hover:opacity-100 transition duration-300"></div>

                    <div class="flex items-center justify-between mb-4">
                        {{-- 🔑 CORREÇÃO: Puxa o status_label traduzido do back-end --}}
                        <span class="text-xs font-mono font-bold tracking-tight px-3 py-1 rounded-lg border {{ $event['style'] }}">
                            {{ $event['status_label'] }}
                        </span>
                        <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 font-medium">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                            </svg>
                            {{ $event['date'] }}
                        </div>
                    </div>

                    <h3 class="text-base font-black text-gray-900 dark:text-white group-hover:text-amber-500 transition duration-200 tracking-tight">
                        {{ $event['title'] }}
                    </h3>

                    <div class="mt-4 space-y-2.5 border-t border-gray-100 dark:border-gray-800/60 pt-4">
                        <div class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <svg class="w-4 h-4 mt-0.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="truncate"><strong class="text-gray-800 dark:text-gray-200 font-semibold">Ativo:</strong> {{ $event['asset'] }}</span>
                        </div>

                        <div class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <svg class="w-4 h-4 mt-0.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25s-7.5-4.108-7.5-11.25a3 3 0 1115 0z" />
                            </svg>
                            <span class="truncate"><strong class="text-gray-800 dark:text-gray-200 font-semibold">Local:</strong> {{ $event['client'] }}</span>
                        </div>
                    </div>

                    <div class="mt-5 pt-4 border-t border-gray-100 dark:border-gray-800/80 flex items-center justify-end text-xs font-bold text-amber-600 dark:text-amber-500 gap-1 group-hover:text-amber-500 transition duration-200">
                        Abrir Checklist de Campo
                        <svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </div>
                </a>
            @empty
                <div class="col-span-full p-12 text-center bg-gray-50 dark:bg-gray-900/40 rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-800">
                    <svg class="w-10 h-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.375M9 18h3.375m14.125-11.25v10.5A2.25 2.25 0 0118 19.5H6A2.25 2.25 0 013.75 17.25V6.75A2.25 2.25 0 016 4.5h1.5m.75-1.5h3a.75.75 0 01.75.75V4.5h-4.5V3.75A.75.75 0 018.25 3zM16.5 4.5h1.5a.75.75 0 01.75.75v.008c0 .414-.336.75-.75.75h-1.5a.75.75 0 01-.75-.75V5.25c0-.414.336-.75.75-.75z" />
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">Nenhum atendimento ou histórico localizado para o seu perfil.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>