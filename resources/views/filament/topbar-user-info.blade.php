@php $user = auth()->user(); @endphp
@if($user)
    {{-- hidden até md: so' aparece em telas de computador, nao no mobile
         (pedido explicito -- topbar mobile fica mais limpo/curto). --}}
    <div class="hidden md:flex flex-col items-end leading-tight mr-2">
        <span class="text-xs font-semibold text-white truncate max-w-[12rem]">{{ $user->name }}</span>
        @if($user->job_title)
            <span class="text-[11px] text-gray-400 truncate max-w-[12rem]">{{ $user->job_title }}</span>
        @endif
    </div>
@endif
