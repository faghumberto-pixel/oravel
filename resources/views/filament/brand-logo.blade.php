@php $tenant = \App\Support\Tenancy::current(); @endphp
<div class="flex flex-col leading-tight">
    @if($tenant)
        <span class="text-base font-bold tracking-tight text-primary-500 truncate max-w-[12rem]">{{ $tenant->name }}</span>
    @else
        <div class="text-xl font-bold tracking-tight text-white">O<span class="text-primary-500">r</span>avel</div>
    @endif
</div>
