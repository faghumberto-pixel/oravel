<?php

namespace App\Filament\Pages;

use App\Models\LandingPageLead;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;

class ManageLandingPageLeads extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Leads da Landing Page';
    protected static ?int $navigationSort = 50;
    protected static ?string $navigationGroup = 'Comercial';

    protected static string $view = 'filament.pages.manage-landing-page-leads';

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(LandingPageLead::query())
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->copyable()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Telefone')
                    ->copyable(),
                TextColumn::make('product')
                    ->label('Produto')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'crm' => 'blue',
                        'wms' => 'purple',
                        'frota' => 'orange',
                        'os' => 'green',
                        default => 'gray',
                    }),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'primary' => 'novo',
                        'warning' => 'contatado',
                        'success' => 'convertido',
                        'danger' => 'rejeitado',
                    ]),
                TextColumn::make('contacted_at')
                    ->label('Contatado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Recebido em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('view')
                    ->label('Detalhes')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (LandingPageLead $record) => "Lead: {$record->name}")
                    ->infolist(
                        fn (Infolist $infolist, LandingPageLead $record): Infolist => $infolist
                            ->schema([
                                Section::make('Informações de Contato')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('name')->label('Nome'),
                                        TextEntry::make('email')->label('Email'),
                                        TextEntry::make('phone')->label('Telefone'),
                                        TextEntry::make('company')->label('Empresa'),
                                    ]),
                                Section::make('Detalhes da Conversão')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('segment')->label('Segmento'),
                                        TextEntry::make('product')->label('Produto'),
                                        TextEntry::make('status')->label('Status'),
                                        TextEntry::make('created_at')
                                            ->label('Recebido em')
                                            ->dateTime('d/m/Y H:i'),
                                    ]),
                                Section::make('Notas')
                                    ->schema([
                                        TextEntry::make('notes')->label('Notas')->html(),
                                    ]),
                            ]),
                    ),
                Action::make('mark_contacted')
                    ->label('Marcar como Contatado')
                    ->icon('heroicon-o-check')
                    ->action(function (LandingPageLead $record) {
                        $record->update([
                            'status' => 'contatado',
                            'contacted_at' => now(),
                        ]);
                    })
                    ->requiresConfirmation()
                    ->hidden(fn (LandingPageLead $record) => $record->status !== 'novo'),
            ])
            ->bulkActions([
                //
            ]);
    }
}
