<?php

namespace App\Filament\Concerns;

use App\Models\Asset;
use App\Models\FrotaTrocaOleo;
use App\Services\Frota\EstoqueFrotaService;
use App\Services\Frota\OleoService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Illuminate\Validation\ValidationException;

/** Formulário e ação "Registrar óleo" (troca ou reposição), reaproveitados na tela de óleo e na ficha do veículo. */
trait AcoesOleo
{
    /** @return array<int, Forms\Components\Component> */
    protected static function camposRegistroOleo(?string $ativoFixo = null): array
    {
        return array_values(array_filter([
            $ativoFixo ? null : Forms\Components\Select::make('ativo_id')->label('Veículo')->required()->searchable()->live()->native(false)
                ->options(fn () => Asset::opcoesVeiculos())
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    $a = $state ? Asset::find($state) : null;
                    $set('odometro', $a ? (int) floor((float) $a->odometro_atual) : null);
                    $set('produto', $a?->planoOleoAtivo()?->especificacao_oleo);
                }),
            Forms\Components\Radio::make('tipo')->label('O que foi feito')->options(FrotaTrocaOleo::tipoLabels())->default(FrotaTrocaOleo::TROCA)->inline()->required()
                ->helperText('Troca zera o contador e calcula a próxima. Reposição (completar nível) não zera.'),
            Forms\Components\TextInput::make('litros')->label('Litros')->numeric()->required()->minValue(1)->step(1)->helperText('Em litros inteiros (arredonde para cima).'),
            Forms\Components\TextInput::make('produto')->label('Produto (óleo)')->required()->maxLength(191)->placeholder('Ex.: 15W40 CK-4')
                ->default($ativoFixo ? Asset::find($ativoFixo)?->planoOleoAtivo()?->especificacao_oleo : null),
            Forms\Components\TextInput::make('odometro')->label('Odômetro do veículo (km)')->numeric()->required()
                ->default($ativoFixo ? (int) floor((float) Asset::find($ativoFixo)?->odometro_atual) : null),
            Forms\Components\TextInput::make('lote')->label('Lote (opcional)')->maxLength(100),
            Forms\Components\TextInput::make('custo')->label('Custo (R$, opcional)')->numeric()->minValue(0)->prefix('R$'),
            Forms\Components\Select::make('peca_id')->label('Óleo no estoque (opcional)')->searchable()->native(false)->live()
                ->options(fn () => EstoqueFrotaService::opcoesPecas())
                ->helperText('Se escolher, os litros saem do almoxarifado.'),
            Forms\Components\Select::make('almoxarifado_id')->label('Almoxarifado de onde saiu')->searchable()->native(false)
                ->options(fn () => EstoqueFrotaService::opcoesAlmoxarifados())
                ->default(fn () => EstoqueFrotaService::almoxarifadoDoUsuario())
                ->required(fn (Forms\Get $get) => filled($get('peca_id'))),
            Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2)->columnSpanFull(),
        ]));
    }

    protected static function acaoRegistrarOleo(?\Closure $ativoDe = null): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('registrar_oleo')
            ->label('Registrar óleo')
            ->icon('heroicon-o-beaker')
            ->color('success')
            ->form(fn () => self::camposRegistroOleo($ativoDe ? $ativoDe()?->id : null))
            ->action(function (array $data) use ($ativoDe) {
                try {
                    $ativo = $ativoDe ? $ativoDe() : Asset::findOrFail($data['ativo_id']);
                    $r = app(OleoService::class)->registrar($ativo, $data, auth()->user());
                    $msg = $r->tipo === FrotaTrocaOleo::TROCA && ($r->proxima_troca_odometro || $r->proxima_troca_data)
                        ? 'Troca registrada. Próxima: '.collect([$r->proxima_troca_odometro ? number_format($r->proxima_troca_odometro, 0, ',', '.').' km' : null, $r->proxima_troca_data?->format('d/m/Y')])->filter()->implode(' ou ').' (o que vier primeiro).'
                        : 'Registro salvo.';
                    Notification::make()->title($msg)->success()->send();
                } catch (ValidationException $e) {
                    Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                }
            });
    }
}
