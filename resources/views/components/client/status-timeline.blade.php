@php
    // Mapeia os status reais de MaintenanceOrder (Aberto/Pendente/Em Andamento/
    // Concluída/Cancelada -- os únicos usados no painel interno, ver
    // MaintenanceOrderResource) para os 4 estágios do Portal do Cliente. Não
    // introduz um enum paralelo -- só uma camada de apresentação em cima do
    // status real, pra não divergir do que o técnico/operador vê.
    $stages = [
        ['key' => 'aberto', 'label' => 'Aberto'],
        ['key' => 'a_caminho', 'label' => 'Técnico a Caminho'],
        ['key' => 'em_atendimento', 'label' => 'Em Atendimento'],
        ['key' => 'resolvido', 'label' => 'Resolvido / Liberado'],
    ];

    $currentIndex = match ($status) {
        'Aberto' => 0,
        'Pendente' => 1,
        'Em Andamento' => 2,
        'Concluída' => 3,
        default => null, // Cancelada ou status desconhecido: sem timeline normal
    };
@endphp

@if ($status === 'Cancelada')
    <div class="flex items-center gap-2 rounded-lg bg-gray-100 px-4 py-3 text-sm font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
        <x-filament::icon icon="heroicon-o-x-circle" class="h-5 w-5" />
        Chamado cancelado
    </div>
@else
    <ol class="flex items-center w-full text-xs sm:text-sm font-medium text-center text-gray-500 dark:text-gray-400">
        @foreach ($stages as $index => $stage)
            @php
                $isDone = $currentIndex !== null && $index < $currentIndex;
                $isCurrent = $currentIndex !== null && $index === $currentIndex;
            @endphp
            <li @class([
                'flex items-center',
                'text-primary-600 dark:text-primary-400' => $isDone,
                'text-primary-700 dark:text-primary-300 font-bold' => $isCurrent,
                'after:content-[\'\'] after:w-full after:h-0.5 after:border-b after:border-1 after:hidden sm:after:inline-block after:mx-3 xl:after:mx-6 w-full' => ! $loop->last,
                'after:border-primary-500' => $isDone,
                'after:border-gray-200 dark:after:border-gray-700' => ! $isDone,
            ])>
                <span @class([
                    'flex items-center justify-center w-6 h-6 rounded-full shrink-0',
                    'bg-primary-600 text-white' => $isDone || $isCurrent,
                    'bg-gray-100 text-gray-500 dark:bg-gray-700' => ! $isDone && ! $isCurrent,
                ])>
                    @if ($isDone)
                        <x-filament::icon icon="heroicon-o-check" class="h-3.5 w-3.5" />
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>
                <span class="hidden sm:inline-flex sm:ms-2">{{ $stage['label'] }}</span>
            </li>
        @endforeach
    </ol>
@endif
