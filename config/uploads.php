<?php

use App\Models\ChatMessage;
use App\Models\ClientMessage;
use App\Models\EmailMessage;

return [

    /*
    |--------------------------------------------------------------------------
    | Redução de fotos no envio (Media Library)
    |--------------------------------------------------------------------------
    | Fotos de celular têm 4-8 MB e vão ser a maior parte do disco da PROD. Aqui só se reduz o que
    | NÃO é evidência: lista de PERMISSÃO (o que não estiver listado nasce intocado, inclusive
    | coleções novas). Fotos de avaria, movimentação, pátio, checklist, vistoria e OS alimentam o
    | dossiê jurídico e não são tocadas: reencodar apaga EXIF/GPS e o app não guarda hash do original.
    | Só imagens jpeg/png/webp; áudio e documentos ficam como enviados.
    | Desligar sem deploy: UPLOADS_DOWNSCALE=false no .env (e config:cache).
    */
    'downscale' => [
        'enabled' => (bool) env('UPLOADS_DOWNSCALE', true),
        'max_side' => (int) env('UPLOADS_DOWNSCALE_MAX_SIDE', 1600),
        'quality' => (int) env('UPLOADS_DOWNSCALE_QUALITY', 82),
        // Abaixo disso, se a foto já cabe em max_side, não vale reencodar.
        'min_bytes' => 300 * 1024,

        // Classe do model => coleções liberadas.
        'collections' => [
            ChatMessage::class => ['chat_attachments'],
            ClientMessage::class => ['anexos'],
            EmailMessage::class => ['anexos'],
        ],
    ],

];
