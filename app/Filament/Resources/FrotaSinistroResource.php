<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaSinistroResource\Pages;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaSinistro;
use App\Services\Frota\SinistroService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Sinistros e ocorrências da frota: colisão, avaria, furto/roubo..., com fotos, B.O., seguradora, orçamento e dias parado. */
class FrotaSinistroResource extends BaseResource
{
    protected static ?string $model = FrotaSinistro::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Sinistros e ocorrências';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Sinistro';

    protected static ?string $pluralModelLabel = 'Sinistros e ocorrências';

    public static function getNavigationBadge(): ?string
    {
        if (! static::canViewAny()) {
            return null;
        }
        $n = FrotaSinistro::whereNotIn('situacao', [FrotaSinistro::ENCERRADO, FrotaSinistro::CANCELADO])->count();

        return $n > 0 ? (string) $n : null;
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

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Ocorrência')->columns(3)->schema([
                Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->native(false)
                    ->options(fn () => Asset::opcoesVeiculos())->disabledOn('edit'),
                Forms\Components\Select::make('tipo')->label('Tipo')->options(FrotaSinistro::tipoLabels())->required()->native(false),
                Forms\Components\DateTimePicker::make('ocorrido_em')->label('Data e hora')->seconds(false)->required()->maxDate(now()->addMinutes(5))->default(now())->disabledOn('edit'),
                Forms\Components\Select::make('motorista_id')->label('Motorista (em branco = sugerido pela saída do veículo)')->searchable()->native(false)
                    ->options(fn () => FleetDriver::query()->where('active', true)->orderBy('name')->pluck('name', 'id')->all()),
                Forms\Components\TextInput::make('local')->label('Local')->maxLength(191),
                Forms\Components\Select::make('culpa')->label('Culpa')->options(FrotaSinistro::culpaLabels())->default('indefinida')->native(false),
                Forms\Components\Toggle::make('houve_vitima')->label('Houve vítima'),
                Forms\Components\Toggle::make('veiculo_parado')->label('Veículo parado')->live()->disabledOn('edit')
                    ->helperText('Enquanto estiver parado, o veículo não pode registrar saída.'),
                Forms\Components\DateTimePicker::make('parado_desde')->label('Parado desde')->seconds(false)->visible(fn (Forms\Get $get) => (bool) $get('veiculo_parado'))->disabledOn('edit')
                    ->helperText('Em branco = a hora da ocorrência.'),
                Forms\Components\Textarea::make('descricao')->label('O que aconteceu')->required()->rows(3)->columnSpanFull(),
            ]),
            Forms\Components\Section::make('B.O. e seguradora')->columns(3)->schema([
                Forms\Components\TextInput::make('bo_numero')->label('Nº do B.O.')->maxLength(60),
                Forms\Components\TextInput::make('seguradora')->label('Seguradora')->maxLength(191),
                Forms\Components\TextInput::make('apolice')->label('Apólice')->maxLength(60),
                Forms\Components\TextInput::make('numero_sinistro_seguradora')->label('Nº do sinistro na seguradora')->maxLength(60),
                Forms\Components\TextInput::make('valor_orcamento')->label('Valor do orçamento (R$)')->numeric()->minValue(0)->prefix('R$'),
                Forms\Components\TextInput::make('valor_franquia')->label('Franquia (R$)')->numeric()->minValue(0)->prefix('R$'),
            ]),
            Forms\Components\Section::make('Fotos e documentos')->columns(2)->schema([
                Forms\Components\SpatieMediaLibraryFileUpload::make('fotos')->label('Fotos')->collection('fotos')->multiple()->image()->maxFiles(12)->reorderable(),
                Forms\Components\SpatieMediaLibraryFileUpload::make('documentos')->label('Documentos (B.O., laudo, orçamento)')->collection('documentos')->multiple()
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])->maxFiles(10),
                Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['veiculo', 'motorista', 'ordemServico']))
            ->defaultSort('ocorrido_em', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('situacao')->label('Situação')->badge()->formatStateUsing(fn (string $state) => FrotaSinistro::situacaoLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        FrotaSinistro::ENCERRADO => 'success', FrotaSinistro::CANCELADO => 'gray', FrotaSinistro::ABERTO => 'danger', default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('veiculo.placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->formatStateUsing(fn (string $state) => FrotaSinistro::tipoLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('ocorrido_em')->label('Ocorrência')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('motorista.name')->label('Motorista')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('descricao')->label('Descrição')->limit(40)->tooltip(fn (FrotaSinistro $r) => $r->descricao)->toggleable(),
                Tables\Columns\TextColumn::make('bo_numero')->label('B.O.')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('seguradora')->label('Seguradora')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('valor_orcamento')->label('Orçamento')->money('BRL')->placeholder('—'),
                Tables\Columns\TextColumn::make('dias_parado')->label('Dias parado')->placeholder('—')->state(fn (FrotaSinistro $r) => $r->diasParado()),
                Tables\Columns\TextColumn::make('os')->label('OS')->placeholder('—')->state(fn (FrotaSinistro $r) => $r->ordemServico?->os_number),
            ])
            ->filters([
                Tables\Filters\Filter::make('em_aberto')->label('Só em aberto')->toggle()->default()
                    ->query(fn (Builder $q) => $q->whereNotIn('situacao', [FrotaSinistro::ENCERRADO, FrotaSinistro::CANCELADO])),
                Tables\Filters\SelectFilter::make('ativo_id')->label('Veículo')->options(fn () => Asset::opcoesVeiculos())->searchable(),
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')->options(FrotaSinistro::tipoLabels()),
                Tables\Filters\TernaryFilter::make('veiculo_parado')->label('Veículo parado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn (FrotaSinistro $r) => ! $r->encerrado()),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('gerar_os')->label('Gerar OS de reparo')->icon('heroicon-o-wrench-screwdriver')
                        ->visible(fn (FrotaSinistro $r) => ! $r->encerrado() && ! $r->ordem_servico_id)->requiresConfirmation()
                        ->action(fn (FrotaSinistro $r) => self::executar(fn () => app(SinistroService::class)->gerarOs($r), 'OS de reparo aberta')),
                    Tables\Actions\Action::make('orcamento')->label('Registrar orçamento')->icon('heroicon-o-calculator')
                        ->visible(fn (FrotaSinistro $r) => ! $r->encerrado())
                        ->form([
                            Forms\Components\TextInput::make('valor_orcamento')->label('Valor do orçamento (R$)')->numeric()->required()->minValue(0.01)->prefix('R$'),
                            Forms\Components\TextInput::make('valor_franquia')->label('Franquia (R$)')->numeric()->minValue(0)->prefix('R$'),
                        ])
                        ->action(fn (FrotaSinistro $r, array $data) => self::executar(fn () => app(SinistroService::class)->registrarOrcamento($r, $data['valor_orcamento'], filled($data['valor_franquia'] ?? null) ? (float) $data['valor_franquia'] : null), 'Orçamento registrado')),
                    Tables\Actions\Action::make('voltou_a_rodar')->label('Voltou a rodar')->icon('heroicon-o-play')->color('success')
                        ->visible(fn (FrotaSinistro $r) => $r->parado())
                        ->form([Forms\Components\DateTimePicker::make('voltou_a_rodar_em')->label('Data e hora')->seconds(false)->default(now())->maxDate(now())->required()])
                        ->action(fn (FrotaSinistro $r, array $data) => self::executar(fn () => app(SinistroService::class)->voltouARodar($r, $data['voltou_a_rodar_em']), 'Veículo liberado para rodar')),
                    Tables\Actions\Action::make('encerrar')->label('Encerrar')->icon('heroicon-o-check-circle')->color('success')
                        ->visible(fn (FrotaSinistro $r) => ! $r->encerrado())->requiresConfirmation()
                        ->action(fn (FrotaSinistro $r) => self::executar(fn () => app(SinistroService::class)->encerrar($r), 'Sinistro encerrado')),
                    Tables\Actions\Action::make('cancelar')->label('Cancelar')->icon('heroicon-o-x-circle')->color('danger')
                        ->visible(fn (FrotaSinistro $r) => ! $r->encerrado())
                        ->form([Forms\Components\Textarea::make('motivo')->label('Motivo do cancelamento')->required()->rows(2)])
                        ->action(fn (FrotaSinistro $r, array $data) => self::executar(fn () => app(SinistroService::class)->cancelar($r, $data['motivo']), 'Sinistro cancelado')),
                ]),
            ])
            ->emptyStateHeading('Nenhum sinistro registrado')
            ->emptyStateDescription('Use "Novo sinistro" para registrar colisão, avaria, furto ou outra ocorrência.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFrotaSinistros::route('/'),
            'create' => Pages\CreateFrotaSinistro::route('/novo'),
            'edit' => Pages\EditFrotaSinistro::route('/{record}/editar'),
        ];
    }
}
