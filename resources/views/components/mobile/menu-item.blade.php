{{-- Item de menu em cartao claro (icone colorido, titulo e descricao). tone: emerald|violet|sky|amber|blue|rose --}}
@props(['href', 'icon', 'title', 'description' => null, 'tone' => 'blue'])

@php
    $tons = [
        'emerald' => 'bg-emerald-100', 'violet' => 'bg-violet-100', 'sky' => 'bg-sky-100',
        'amber' => 'bg-amber-100', 'blue' => 'bg-blue-100', 'rose' => 'bg-rose-100',
    ];
@endphp

<a href="{{ $href }}" class="flex items-center gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition hover:shadow-md active:bg-zinc-50">
    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-2xl {{ $tons[$tone] ?? $tons['blue'] }}">{{ $icon }}</span>
    <div class="min-w-0">
        <p class="text-sm font-bold text-zinc-900">{{ $title }}</p>
        @if ($description)
            <p class="text-xs text-zinc-600">{{ $description }}</p>
        @endif
    </div>
</a>
