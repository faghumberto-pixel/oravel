@php
    $tenant = auth()->user()->tenant;
    $signature = $tenant?->signature;
@endphp

<x-filament-panels::page>
    @if ($signature)
        <div class="rounded-lg border-2 border-green-300 bg-green-50 p-6 dark:border-green-700 dark:bg-green-900">
            <div class="flex gap-4">
                <div class="text-4xl">✅</div>
                <div>
                    <h2 class="text-xl font-bold text-green-900 dark:text-green-100">Contrato Já Assinado</h2>
                    <p class="mt-2 text-sm text-green-800 dark:text-green-200">
                        Você já assinou o contrato de serviço em <strong>{{ $signature->signed_at->format('d \\d\\e F \\d\\e Y \\à\\s H:i') }}</strong>
                    </p>
                    <p class="mt-1 text-xs text-green-700 dark:text-green-300">
                        Assinado por: <strong>{{ $signature->name }}</strong> ({{ $signature->email }})
                    </p>
                </div>
            </div>
        </div>
    @else
        <form wire:submit="submit" class="space-y-6">
            {{ $this->form }}

            <div class="flex justify-end gap-3">
                <a href="{{ route('filament.admin.pages.painel-controle') }}"
                   class="rounded-lg border border-gray-300 bg-white px-6 py-2 font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    Cancelar
                </a>
                <button type="submit"
                        class="rounded-lg bg-orange-600 px-6 py-2 font-medium text-white hover:bg-orange-700 transition">
                    🔏 Assinar Eletronicamente
                </button>
            </div>
        </form>
    @endif
</x-filament-panels::page>
