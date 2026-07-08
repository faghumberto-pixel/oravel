@php $tenant = \App\Support\Tenancy::current(); @endphp
<div class="flex flex-col leading-tight">
    <div class="text-xl font-bold tracking-tight text-white">O<span class="text-primary-500">r</span>avel</div>
    @if($tenant)
        <span class="text-[11px] font-medium text-gray-400 truncate max-w-[9rem]">{{ $tenant->name }}</span>
    @endif
</div>
