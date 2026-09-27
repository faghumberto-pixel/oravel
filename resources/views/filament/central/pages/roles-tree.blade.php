<x-filament-panels::page>
    @php
        $tree = $this->getTenantTree();
    @endphp

    <div class="space-y-3" x-data="{ open: null }">
        @forelse ($tree as $index => $node)
            @php
                $tenant = $node['tenant'];
                $roles = $node['roles'];
            @endphp

            <div class="fi-section rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <button
                    type="button"
                    x-on:click="open = (open === {{ $index }}) ? null : {{ $index }}"
                    class="flex w-full items-center justify-between gap-4 px-4 py-3 text-left"
                >
                    <div class="flex items-center gap-3">
                        <x-heroicon-o-building-office-2 class="h-5 w-5 shrink-0 text-gray-400" />
                        <div>
                            <div class="font-semibold text-gray-950 dark:text-white">{{ $tenant->name }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $roles->count() }} {{ $roles->count() === 1 ? 'perfil' : 'perfis' }}
                            </div>
                        </div>
                    </div>

                    <x-heroicon-o-chevron-down
                        class="h-5 w-5 shrink-0 text-gray-400 transition-transform"
                        x-bind:class="open === {{ $index }} ? 'rotate-180' : ''"
                    />
                </button>

                <div x-show="open === {{ $index }}" x-collapse x-cloak>
                    <div class="border-t border-gray-200 dark:border-gray-700">
                        @if ($roles->isEmpty())
                            <p class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">Nenhum perfil cadastrado.</p>
                        @else
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                                        <th class="px-4 py-2 text-left font-medium">Perfil</th>
                                        <th class="px-4 py-2 text-left font-medium">Permissões</th>
                                        <th class="px-4 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($roles as $role)
                                        <tr>
                                            <td class="px-4 py-2 text-gray-950 dark:text-white">{{ $role->name }}</td>
                                            <td class="px-4 py-2">
                                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                    {{ $role->permissions_count }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-right">
                                                <a href="{{ $this->editUrl($role) }}" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">
                                                    Editar
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum tenant cadastrado.</p>
        @endforelse
    </div>
</x-filament-panels::page>
