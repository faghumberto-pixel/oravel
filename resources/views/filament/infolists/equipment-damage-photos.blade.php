@php
    $media = $getRecord()->getMedia('photos');
@endphp

<div class="flex flex-wrap gap-3">
    @forelse($media as $photo)
        <div class="flex flex-col items-center gap-1">
            <a href="{{ $photo->getUrl() }}" target="_blank">
                <img src="{{ $photo->getUrl('thumb') }}" class="h-24 w-24 rounded-lg object-cover border border-gray-200 dark:border-gray-700">
            </a>
            @if($photo->getCustomProperty('latitude'))
                <span class="text-[10px] text-gray-500 dark:text-gray-400">
                    {{ number_format($photo->getCustomProperty('latitude'), 5) }}, {{ number_format($photo->getCustomProperty('longitude'), 5) }}
                </span>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">Nenhuma foto anexada.</p>
    @endforelse
</div>
