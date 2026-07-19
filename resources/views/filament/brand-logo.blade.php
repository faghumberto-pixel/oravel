@php
    $tenant = \App\Support\Tenancy::current();
    $segmentLabel = $tenant?->segment ? (\App\Models\Client::nicheLabels()[$tenant->segment] ?? null) : null;
@endphp
<div class="flex flex-col leading-tight">
    @if($tenant)
        <span class="text-base font-bold tracking-tight text-primary-500 truncate max-w-[12rem]">{{ $tenant->name }}</span>
        @if($segmentLabel)
            <span class="text-[11px] font-medium tracking-wide text-gray-400 truncate max-w-[12rem]">{{ $segmentLabel }}</span>
        @endif
    @else
        <div class="text-xl font-bold tracking-tight text-white">O<span class="text-primary-500">r</span>avel</div>
    @endif
</div>
