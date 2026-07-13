@props([
    'notifications',
    'unreadNotificationsCount',
])

{{--
    Override de vendor/filament/notifications/resources/views/components/database/modal/actions.blade.php --
    removido o link "Limpar" (wire:click="clearNotifications") de proposito: notificacoes servem de
    comprovacao de auditoria, ninguem deve conseguir apaga-las pela UI. "Marcar tudo como lido" continua
    (so seta read_at, nao apaga a linha). Ver tambem App\Livewire\DatabaseNotifications, que sobrescreve
    clearNotifications()/removeNotification() como defesa em profundidade caso algo chame o metodo direto.
--}}
<div {{ $attributes->class('mt-2 flex gap-x-3') }}>
    @if ($unreadNotificationsCount)
        <x-filament::link
            color="primary"
            tabindex="-1"
            tag="button"
            wire:click="markAllNotificationsAsRead"
        >
            {{ __('filament-notifications::database.modal.actions.mark_all_as_read.label') }}
        </x-filament::link>
    @endif
</div>
