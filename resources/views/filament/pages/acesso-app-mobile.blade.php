<x-filament-panels::page>
    <x-filament::section>
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Escolha o que cada pessoa enxerga no App do Colaborador (celular). Só aparecem os módulos que o contrato da sua empresa libera.
            Quem está em <b>Padrão do perfil</b> continua vendo o que o perfil de acesso e as permissões já permitiam, sem nenhuma mudança.
        </p>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
