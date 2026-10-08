<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\ContratoAssinadoResource\Pages;
use App\Models\DocumentSignature;
use App\Models\Tenant;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Contratos de assinatura (Oravel x cada cliente) já assinados: ver como
 * página e imprimir (documento + comprovante de assinatura).
 */
class ContratoAssinadoResource extends Resource
{
    protected static ?string $model = DocumentSignature::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $navigationLabel = 'Contratos Assinados';

    protected static ?string $modelLabel = 'Contrato Assinado';

    protected static ?string $pluralModelLabel = 'Contratos Assinados';

    protected static ?int $navigationSort = 3;

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->where('signable_type', Tenant::class)
            ->where('status', 'signed');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('signed_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('cliente')
                    ->label('Cliente')
                    ->state(fn (DocumentSignature $r) => Tenant::withoutGlobalScopes()->find($r->signable_id)?->name ?? '—')
                    ->searchable(query: fn (Builder $q, string $s) => $q->whereIn('signable_id', Tenant::withoutGlobalScopes()->where('name', 'ilike', "%{$s}%")->select('id'))),
                Tables\Columns\TextColumn::make('signer_name')->label('Assinado por')->searchable(),
                Tables\Columns\TextColumn::make('signer_document')->label('CPF/CNPJ')->toggleable(),
                Tables\Columns\TextColumn::make('signer_email')->label('E-mail')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('signed_at')->label('Assinado em')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('document_hash')->label('Código de segurança')->limit(16)->tooltip(fn (DocumentSignature $r) => $r->document_hash)->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver')
                    ->label('Ver e imprimir')
                    ->icon('heroicon-o-eye')
                    ->url(fn (DocumentSignature $r) => route('central.contrato-assinado.ver', $r->id), shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContratosAssinados::route('/'),
        ];
    }
}
