<div>
    @if ($tenants->isNotEmpty())
        <div class="flex items-center gap-x-2">
            <x-heroicon-o-building-office-2 class="h-5 w-5 text-gray-400" />

            <select
                wire:model.live="actingTenantId"
                class="fi-select-input block rounded-lg border border-gray-300 bg-white py-1.5 pe-8 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            >
                <option value="">Selecione um tenant…</option>
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant->id }}" @selected($actingTenantId === $tenant->id)>
                        {{ $tenant->name }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif
</div>
