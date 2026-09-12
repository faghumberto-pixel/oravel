<x-filament-panels::page>
    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-600 dark:text-gray-400">Total de Leads</div>
                <div class="mt-2 text-3xl font-bold">{{ \App\Models\LandingPageLead::count() }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-600 dark:text-gray-400">Novos</div>
                <div class="mt-2 text-3xl font-bold text-blue-600">{{ \App\Models\LandingPageLead::where('status', 'novo')->count() }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-600 dark:text-gray-400">Contatados</div>
                <div class="mt-2 text-3xl font-bold text-amber-600">{{ \App\Models\LandingPageLead::where('status', 'contatado')->count() }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="text-sm text-gray-600 dark:text-gray-400">Convertidos</div>
                <div class="mt-2 text-3xl font-bold text-green-600">{{ \App\Models\LandingPageLead::where('status', 'convertido')->count() }}</div>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
