<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComplianceDocumentResource\Pages;
use App\Models\ComplianceDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables;
use Filament\Tables\Table;

class ComplianceDocumentResource extends BaseResource
{
    protected static ?string $model = ComplianceDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Itens Agregados';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Segurança e Documentação';

    protected static ?string $modelLabel = 'Documento de Segurança';

    protected static ?string $pluralModelLabel = 'Segurança e Documentação';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('document_type')->label('Tipo de documento')
                ->options(ComplianceDocument::typeLabels())->required()->native(false)->live(),
            Forms\Components\TextInput::make('number')->label('Número / apólice')->maxLength(100),
            Forms\Components\TextInput::make('issuer')->label('Emissor / seguradora')->maxLength(255),
            Forms\Components\TextInput::make('responsible')->label('Responsável técnico')->maxLength(255),
            Forms\Components\Select::make('asset_id')->label('Equipamento')
                ->relationship('asset', 'name')->searchable()->preload(),
            Forms\Components\Select::make('contract_id')->label('Contrato de locação')
                ->relationship('contract', 'contract_number')->searchable()->preload(),
            Forms\Components\DatePicker::make('issue_date')->label('Emissão / início da vigência'),
            Forms\Components\DatePicker::make('expires_at')->label('Validade / fim da vigência'),
            Forms\Components\TextInput::make('coverage_value')->label('Valor segurado')->numeric()->prefix('R$')
                ->visible(fn (Get $get) => in_array($get('document_type'), ['seguro_reta', 'seguro_riscos_diversos'], true)),
            Forms\Components\FileUpload::make('attachment')->label('Arquivo (PDF/imagem)')
                ->directory('compliance-documents')->acceptedFileTypes(['application/pdf', 'image/*'])->maxSize(10240),
            Forms\Components\Textarea::make('notes')->label('Observações')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_type')->label('Tipo')->badge()
                    ->formatStateUsing(fn ($state) => ComplianceDocument::typeLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('number')->label('Número')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('issuer')->label('Emissor / seguradora')->placeholder('—'),
                Tables\Columns\TextColumn::make('asset.name')->label('Equipamento')->placeholder('—'),
                Tables\Columns\TextColumn::make('contract.contract_number')->label('Contrato')->placeholder('—'),
                Tables\Columns\TextColumn::make('expires_at')->label('Validade')->date('d/m/Y')->sortable()->placeholder('—')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : ($state && now()->diffInDays($state, false) <= 30 ? 'warning' : null)),
            ])
            ->defaultSort('expires_at')
            ->filters([
                Tables\Filters\SelectFilter::make('document_type')->label('Tipo')->options(ComplianceDocument::typeLabels()),
                Tables\Filters\Filter::make('vencidos')->label('Vencidos')
                    ->query(fn ($query) => $query->whereDate('expires_at', '<', now())),
                Tables\Filters\Filter::make('vencendo')->label('Vencem em 30 dias')
                    ->query(fn ($query) => $query->whereBetween('expires_at', [now()->toDateString(), now()->addDays(30)->toDateString()])),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageComplianceDocuments::route('/')];
    }
}
