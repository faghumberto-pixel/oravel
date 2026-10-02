<x-filament-panels::page>
    @php
        $rows = $this->ranking();
        $me = auth()->id();
    @endphp

    <p class="text-sm text-gray-600 dark:text-gray-400">
        Pontos por leitura, respostas certas e tempo de estudo. Só aparecem as pessoas da sua empresa.
    </p>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left dark:bg-gray-800">
                <tr><th class="w-16 p-3">#</th><th class="p-3">Nome</th><th class="p-3 text-right">Pontos</th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $i => $row)
                    <tr class="border-t border-gray-100 dark:border-gray-800 {{ $row->user_id === $me ? 'bg-primary-50 font-semibold dark:bg-primary-950' : '' }}">
                        <td class="p-3">{{ $i + 1 }}</td>
                        <td class="p-3">{{ $row->name }}{{ $row->user_id === $me ? ' (você)' : '' }}</td>
                        <td class="p-3 text-right">{{ number_format($row->points, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="p-6 text-center text-gray-500">Ninguém pontuou ainda. Abra uma aula e comece!</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
