@php
    $signature = $getState();
@endphp

@if($signature)
    <img src="{{ $signature }}" class="h-32 rounded-lg border border-gray-200 bg-white dark:border-gray-700">
@else
    <p class="text-sm text-gray-500 dark:text-gray-400">Assinatura não coletada.</p>
@endif
