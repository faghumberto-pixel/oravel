<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\ProspectResource\Pages;
use App\Filament\Central\Resources\ProspectResource\RelationManagers;
use App\Models\Prospect;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Prospects (menu Comercial, so' super admin): lista de empresas a abordar, com mensagem pronta, situacao
 * da abordagem e historico de contatos. Alimentada por `php artisan prospects:importar`.
 */
class ProspectResource extends Resource
{
    protected static ?string $model = Prospect::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = 45;

    protected static ?string $modelLabel = 'prospect';

    protected static ?string $pluralModelLabel = 'Prospects';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Acompanhamento')->schema([
                Forms\Components\Select::make('status')->label('Situação')->options(Prospect::STATUS)->required()->native(false),
                Forms\Components\TextInput::make('lote')->label('Lote')->numeric()->minValue(1),
                Forms\Components\DatePicker::make('proximo_contato')->label('Próximo contato')->native(false)->displayFormat('d/m/Y'),
                Forms\Components\Textarea::make('observacoes')->label('Observações e detalhes')->rows(4)->columnSpanFull(),
                Forms\Components\Textarea::make('o_que_olhar')->label('O que olhar antes de enviar')->rows(3)->columnSpanFull(),
                Forms\Components\Textarea::make('mensagem_personalizada')->label('Mensagem própria (substitui a padrão)')->rows(6)->columnSpanFull(),
            ])->columns(3),
            Forms\Components\Section::make('Empresa')->schema([
                Forms\Components\TextInput::make('nome')->required()->maxLength(200),
                Forms\Components\TextInput::make('razao_social')->label('Razão social')->maxLength(255),
                Forms\Components\TextInput::make('cnpj')->label('CNPJ')->required()->maxLength(20)->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('cidade')->maxLength(120),
                Forms\Components\TextInput::make('atividade')->maxLength(160),
                Forms\Components\TextInput::make('porte')->maxLength(20),
                Forms\Components\TextInput::make('capital')->numeric()->prefix('R$'),
                Forms\Components\TextInput::make('ano_abertura')->label('Ano de abertura')->numeric(),
                Forms\Components\TextInput::make('nota')->label('Nota (0–10)')->numeric()->minValue(0)->maxValue(10),
                Forms\Components\Toggle::make('regiao_campinas')->label('Região de Campinas'),
                Forms\Components\Toggle::make('empresa_nova')->label('Empresa nova'),
            ])->columns(3)->collapsed(),
            Forms\Components\Section::make('Contato')->schema([
                Forms\Components\TextInput::make('socio')->label('Sócio')->maxLength(200),
                Forms\Components\TextInput::make('socio_cargo')->label('Cargo')->maxLength(120),
                Forms\Components\TextInput::make('telefone')->maxLength(40),
                Forms\Components\TextInput::make('email')->label('E-mail')->maxLength(200),
            ])->columns(2)->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Empresa')->weight('bold')->searchable(['nome', 'razao_social', 'cnpj', 'socio'])->sortable()
                    ->description(fn (Prospect $r): string => trim($r->cidade.' · '.$r->porte.($r->ano_abertura ? ' · desde '.$r->ano_abertura : ''), ' ·')),
                Tables\Columns\TextColumn::make('atividade')->label('Atividade')->wrap()->toggleable(),
                Tables\Columns\TextColumn::make('nota')->label('Nota')->badge()->color(fn (int $state): string => $state >= 8 ? 'success' : 'gray')->sortable(),
                Tables\Columns\TextColumn::make('telefone')->copyable()->copyMessage('Número copiado')->searchable()
                    ->description(fn (Prospect $r): string => $r->ehCelular() ? 'Celular' : 'Fixo: ligar'),
                Tables\Columns\TextColumn::make('socio')->label('Sócio')->placeholder('—')->toggleable(),
                Tables\Columns\SelectColumn::make('status')->label('Situação')->options(Prospect::STATUS)->selectablePlaceholder(false),
                Tables\Columns\TextColumn::make('origem')->label('Lista')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('lote')->label('Lote')->placeholder('—')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('proximo_contato')->label('Próx. contato')->date('d/m/Y')->placeholder('—')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Situação')->options(Prospect::STATUS),
                Tables\Filters\SelectFilter::make('lote')->label('Lote')->options(fn () => Prospect::whereNotNull('lote')->distinct()->orderBy('lote')->pluck('lote', 'lote')->all()),
                Tables\Filters\SelectFilter::make('origem')->label('Lista de origem')->options(fn () => Prospect::whereNotNull('origem')->distinct()->orderBy('origem')->pluck('origem', 'origem')->all()),
                Tables\Filters\SelectFilter::make('porte')->options(fn () => Prospect::whereNotNull('porte')->distinct()->pluck('porte', 'porte')->all()),
                Tables\Filters\SelectFilter::make('atividade')->options(fn () => Prospect::whereNotNull('atividade')->distinct()->orderBy('atividade')->pluck('atividade', 'atividade')->all()),
                Tables\Filters\Filter::make('ano_abertura')->label('Ano de abertura')
                    ->form([
                        Forms\Components\TextInput::make('de')->label('Aberta a partir de')->numeric()->minValue(1900)->maxValue(2100)->placeholder('2023'),
                        Forms\Components\TextInput::make('ate')->label('Aberta até')->numeric()->minValue(1900)->maxValue(2100)->placeholder('2026'),
                    ])->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query->anoAbertura($data['de'] ?? null, $data['ate'] ?? null))
                    ->indicateUsing(function (array $data): array {
                        $i = [];
                        if (filled($data['de'] ?? null)) {
                            $i[] = 'Aberta desde '.$data['de'];
                        }
                        if (filled($data['ate'] ?? null)) {
                            $i[] = 'Aberta até '.$data['ate'];
                        }

                        return $i;
                    }),
                Tables\Filters\TernaryFilter::make('empresa_nova')->label('Empresa nova (desde 2023)'),
                Tables\Filters\TernaryFilter::make('regiao_campinas')->label('Região de Campinas'),
                Tables\Filters\Filter::make('nota_alta')->label('Nota 8 ou mais')->toggle()->query(fn (Builder $query): Builder => $query->notaAlta()),
                Tables\Filters\Filter::make('retorno_vencido')->label('Retorno vencido')->toggle()
                    ->query(fn (Builder $query): Builder => $query->retornoVencido()),
            ])
            ->actions([
                Tables\Actions\Action::make('mensagem')->label('Mensagem')->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->modalHeading(fn (Prospect $r): string => 'Mensagem para '.$r->nome)
                    ->modalContent(fn (Prospect $r) => view('filament.central.prospect-mensagem', ['abordagem' => $r->mensagemAbordagem(), 'followup' => $r->mensagemFollowUp(), 'objecoes' => Prospect::OBJECOES, 'olhar' => $r->o_que_olhar]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fechar'),
                Tables\Actions\Action::make('enviei')->label('Enviei')->icon('heroicon-o-paper-airplane')->color('success')
                    ->visible(fn (Prospect $r): bool => $r->status === 'nao_enviado')
                    ->action(function (Prospect $r): void {
                        $r->update(['status' => 'enviado', 'ultimo_contato_em' => now(), 'proximo_contato' => now()->addDays(4)]);
                        $r->contatos()->create(['data' => now(), 'canal' => $r->ehCelular() ? 'whatsapp' : 'telefone', 'titulo' => 'Primeiro contato', 'texto' => 'Mensagem de abordagem enviada.', 'autor' => auth()->user()?->name]);
                    }),
                ProspectResource\ConverterEmLeadAction::make(),
                Tables\Actions\Action::make('verLead')->label('Ver lead')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                    ->visible(fn (Prospect $r): bool => $r->sales_lead_id !== null)
                    ->url(fn (Prospect $r): string => SalesLeadResource::getUrl('edit', ['record' => $r->sales_lead_id]))->openUrlInNewTab(),
                Tables\Actions\ViewAction::make()->label('Abrir')->url(fn (Prospect $r): string => static::getUrl('edit', ['record' => $r])),
            ])
            ->defaultSort('nota', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\ContatosRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProspects::route('/'),
            'create' => Pages\CreateProspect::route('/create'),
            'edit' => Pages\EditProspect::route('/{record}/edit'),
        ];
    }
}
