<?php

namespace App\Filament\Pages;

use App\Filament\Attributes\BelongsToFeature;

use Filament\Pages\Page;

#[BelongsToFeature('users')]
class Chat extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'COMUNICAÇÃO';
    protected static ?string $navigationLabel = 'Chat Interno';
    protected static ?string $title = '';

    protected static string $view = 'filament.pages.chat';

    public function getMaxContentWidth(): string
    {
        return 'full';
    }

    /**
     * Chat liberado para todos os usuarios autenticados,
     * independente da feature do plano do tenant.
     */
    public static function canAccess(): bool
    {
        return true;
    }
}
