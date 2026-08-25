<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Carga de técnicos, lado a lado</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            OS em Aberto, Pendente ou Em Andamento por técnico, agora — sem precisar filtrar um por vez.
            Técnicos sem nenhuma OS em aberto aparecem marcados como ociosos.
        </p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2 font-medium">Técnico</th>
                        <th class="px-4 py-2 font-medium">OS em aberto agora</th>
                        <th class="px-4 py-2 font-medium">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->carga as $linha)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-2 font-medium text-gray-950 dark:text-white">{{ $linha['technician']->name }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ $linha['url'] }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $linha['em_aberto'] }}</a>
                            </td>
                            <td class="px-4 py-2">
                                @if ($linha['ocioso'])
                                    <span class="fi-badge inline-flex items-center rounded-md bg-success-50 px-2 py-1 text-xs font-medium text-success-700 ring-1 ring-inset ring-success-600/10 dark:bg-success-400/10 dark:text-success-400">
                                        Livre agora
                                    </span>
                                @else
                                    <span class="fi-badge inline-flex items-center rounded-md bg-warning-50 px-2 py-1 text-xs font-medium text-warning-700 ring-1 ring-inset ring-warning-600/10 dark:bg-warning-400/10 dark:text-warning-400">
                                        Em atividade
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Nenhum técnico com histórico de OS ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
