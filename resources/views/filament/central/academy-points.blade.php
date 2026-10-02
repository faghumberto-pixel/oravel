<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600 dark:text-gray-400">Participação dos colaboradores de todos os clientes na Academia.</p>
        <div class="flex flex-wrap gap-2">
            <select wire:model.live="tenantId" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" data-testid="tenant">
                <option value="">Todos os clientes</option>
                @foreach ($this->tenantOptions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <select wire:model.live="period" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" data-testid="period">
                @foreach (\App\Services\AcademyParticipation::PERIODS as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @include('filament.academy.participation', ['summary' => $this->summary(), 'courses' => $this->courses(), 'weekly' => $this->weekly()])

    {{ $this->table }}
</x-filament-panels::page>
