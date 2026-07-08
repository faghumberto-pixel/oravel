<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Services\AudioTranscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TranscribeChatAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ChatMessage $chatMessage) {}

    public function handle(AudioTranscriptionService $service): void
    {
        $audio = $this->chatMessage->getMedia('chat_attachments')
            ->first(fn ($m) => str_starts_with($m->mime_type, 'audio/'));

        if (! $audio) {
            return;
        }

        $transcript = $service->transcribe($audio->getPath(), $audio->file_name);

        if ($transcript) {
            $this->chatMessage->update(['transcript' => $transcript]);
        }
    }
}
