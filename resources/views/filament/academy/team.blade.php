<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600 dark:text-gray-400">Acompanhe quem da sua equipe está estudando na Academia.</p>
        <select wire:model.live="period" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" data-testid="period">
            @foreach (\App\Services\AcademyParticipation::PERIODS as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    @include('filament.academy.participation', ['summary' => $this->summary(), 'courses' => $this->courses(), 'weekly' => $this->weekly()])

    {{ $this->table }}
</x-filament-panels::page>
