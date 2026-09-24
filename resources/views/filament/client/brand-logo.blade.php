@php
    $client = \Illuminate\Support\Facades\Auth::guard('client')->user();
@endphp
{{-- Logo Oravel + nome do cliente, mesma logica visual do painel admin
     (filament.brand-logo.blade.php) -- guard proprio ('client', nao o
     guard padrao 'web' que Tenancy::current() usa), por isso uma partial
     separada em vez de reaproveitar a do admin. Pedido do usuario
     2026-09-24: "coloque o mesmo logo... tambem do top bar do cliente". --}}
<div class="flex items-center gap-2 shrink-0">
    <img
        src="{{ asset('images/oravel-logo-or.png') }}"
        alt="Oravel"
        class="h-7 w-auto shrink-0"
    >
    @if($client)
        <span class="text-base font-bold tracking-tight text-white truncate max-w-[12rem]">{{ $client->name }}</span>
    @endif
</div>
