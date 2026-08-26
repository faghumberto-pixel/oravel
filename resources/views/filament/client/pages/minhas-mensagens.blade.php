<x-filament-panels::page>
    <div class="space-y-3 mb-6">
        @forelse ($this->messages as $message)
            <div @class([
                'p-3 rounded-lg max-w-lg',
                'ml-auto bg-primary-100 dark:bg-primary-900' => $message->isFromClient(),
                'bg-gray-100 dark:bg-gray-800' => ! $message->isFromClient(),
            ])>
                <div class="text-xs text-gray-500 mb-1">
                    {{ $message->senderName() }} — {{ $message->created_at->format('d/m/Y H:i') }}
                    @if ($message->area)
                        · {{ \App\Models\ClientMessage::areaLabels()[$message->area] ?? $message->area }}
                    @endif
                </div>
                @if ($message->body)
                    <div class="text-sm">{{ $message->body }}</div>
                @endif
                @foreach ($message->getMedia('anexos') as $media)
                    <a href="{{ $media->getUrl() }}" target="_blank" class="text-xs underline block mt-1">
                        📎 {{ $media->file_name }}
                    </a>
                @endforeach
            </div>
        @empty
            <p class="text-gray-500 text-sm">Nenhuma mensagem ainda.</p>
        @endforelse
    </div>

    <form wire:submit="send">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            Enviar
        </x-filament::button>
    </form>
</x-filament-panels::page>
