<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaItemSegurancaResource\Pages;
use App\Models\Asset;
use App\Models\FrotaItemSeguranca;
use App\Services\Frota\KitSegurancaService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Kit de segurança de cada veículo: extintor com validade, triângulo, macaco e demais itens, com conferência. */
class FrotaItemSegurancaResource extends BaseResource
{
    protected static ?string $model = FrotaItemSeguranca::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Kit de segurança';

    protected static ?int $navigationSort = 16;

    protected static ?string $modelLabel = 'Item de segurança';

    protected static ?string $pluralModelLabel = 'Kit de segurança dos veículos';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    private static function executar(\Closure $acao, string $sucesso): void
    {
        try {
            $acao();
            Notification::make()->title($sucesso)->success()->send();
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('veiculo')->where('ativo', true))
            ->defaultSort('nome')
            ->headerActions([
                Tables\Actions\Action::make('novo_item')->label('Novo item')->icon('heroicon-o-plus')->color('success')
                    ->form([
                        Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)->options(fn () => Asset::opcoesVeiculos()),
                        Forms\Components\TextInput::make('nome')->label('Item')->required()->maxLength(191)->placeholder('Ex.: Extintor de incêndio'),
                        Forms\Components\TextInput::make('identificacao')->label('Série / lacre (opcional)')->maxLength(60),
                        Forms\Components\Toggle::make('obrigatorio')->label('Obrigatório')->default(true),
                        Forms\Components\Toggle::make('tem_validade')->label('Tem validade')->live(),
                        Forms\Components\DatePicker::make('validade')->label('Validade')->visible(fn (Forms\Get $get) => (bool) $get('tem_validade'))->required(fn (Forms\Get $get) => (bool) $get('tem_validade')),
                        Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2),
                    ])
                    ->action(fn (array $data) => self::executar(fn () => app(KitSegurancaService::class)->criar(Asset::findOrFail($data['ativo_id']), $data), 'Item criado')),
                Tables\Actions\Action::make('aplicar_padrao')->label('Aplicar kit sugerido')->icon('heroicon-o-sparkles')->color('gray')
                    ->modalDescription('Cria extintor, triângulo, macaco, chave de roda, kit de primeiros socorros e colete refletivo. Só cria o que o veículo ainda não tem. Os itens com validade ficam como pendência até você conferir.')
                    ->form([Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)->options(fn () => Asset::opcoesVeiculos())])
                    ->action(function (array $data) {
                        try {
                            $n = app(KitSegurancaService::class)->aplicarPadrao(Asset::findOrFail($data['ativo_id']));
                            Notification::make()->title($n ? "{$n} item(ns) criado(s)" : 'O veículo já tem todos os itens sugeridos')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('situacao')->label('Situação')->badge()
                    ->state(function (FrotaItemSeguranca $r) {
                        $a = collect(KitSegurancaService::alertas($r));

                        return $a->contains('gravidade', 'critica') ? 'Crítica' : ($a->isNotEmpty() ? 'Atenção' : 'Em dia');
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Crítica' => 'danger', 'Atenção' => 'warning', default => 'success',
                    }),
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('nome')->label('Item')->searchable()->description(fn (FrotaItemSeguranca $r) => $r->identificacao ? 'Série '.$r->identificacao : null),
                Tables\Columns\IconColumn::make('obrigatorio')->label('Obrigatório')->boolean(),
                Tables\Columns\IconColumn::make('presente')->label('Presente')->boolean(),
                Tables\Columns\TextColumn::make('validade')->label('Validade')->date('d/m/Y')->placeholder('—'),
                Tables\Columns\TextColumn::make('conferido_em')->label('Conferido em')->date('d/m/Y')->placeholder('Nunca'),
                Tables\Columns\TextColumn::make('alertas')->label('Alertas')->badge()->wrap()->placeholder('—')
                    ->state(fn (FrotaItemSeguranca $r) => collect(KitSegurancaService::alertas($r))->pluck('mensagem')->all())
                    ->color(fn (FrotaItemSeguranca $r) => collect(KitSegurancaService::alertas($r))->contains('gravidade', 'critica') ? 'danger' : 'warning'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ativo_id')->label('Veículo')->options(fn () => Asset::opcoesVeiculos())->searchable(),
                Tables\Filters\TernaryFilter::make('presente')->label('Presente'),
                Tables\Filters\TernaryFilter::make('obrigatorio')->label('Obrigatório'),
            ])
            ->actions([
                Tables\Actions\Action::make('conferir')->label('Conferir')->icon('heroicon-o-check-circle')->color('success')
                    ->form(fn (FrotaItemSeguranca $r) => [
                        Forms\Components\Toggle::make('presente')->label('Está no veículo')->default($r->presente),
                        Forms\Components\DatePicker::make('validade')->label('Validade')->default($r->validade)->visible($r->tem_validade),
                        Forms\Components\TextInput::make('identificacao')->label('Série / lacre')->default($r->identificacao)->maxLength(60),
                        Forms\Components\DatePicker::make('conferido_em')->label('Data da conferência')->default(now())->maxDate(now())->required(),
                        Forms\Components\Textarea::make('observacoes')->label('Observações')->default($r->observacoes)->rows(2),
                    ])
                    ->action(fn (FrotaItemSeguranca $r, array $data) => self::executar(fn () => app(KitSegurancaService::class)->conferir($r, $data), 'Conferência registrada')),
                Tables\Actions\Action::make('desativar')->label('Desativar')->icon('heroicon-o-x-circle')->color('danger')->requiresConfirmation()
                    ->modalDescription('O item deixa de aparecer e de gerar pendências.')
                    ->action(fn (FrotaItemSeguranca $r) => self::executar(fn () => app(KitSegurancaService::class)->desativar($r), 'Item desativado')),
            ])
            ->emptyStateHeading('Nenhum item de segurança')
            ->emptyStateDescription('Use "Aplicar kit sugerido" para começar com extintor, triângulo, macaco e chave de roda.');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFrotaItensSeguranca::route('/')];
    }
}
