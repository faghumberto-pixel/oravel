<?php

namespace App\Filament\Central\Resources\ProspectResource;

use App\Integracoes\Observatorio\ObservatorioGateway;
use App\Integracoes\Observatorio\ObservatorioIndisponivel;
use App\Integracoes\Observatorio\RegistrarEntrevista;
use App\Models\Prospect;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;

/**
 * "Registrar entrevista" no prospect: atividade do CRM + envio ao Observatorio. A definicao das perguntas
 * vem do Observatorio (nao e' mantida aqui) e as respostas seguem direto para la.
 */
class RegistrarEntrevistaAction
{
    public static function make(): Action
    {
        return Action::make('registrarEntrevista')
            ->label('Registrar entrevista (Observatório)')->icon('heroicon-o-clipboard-document-check')->color('gray')
            ->modalHeading('Entrevista do Observatório')->modalSubmitActionLabel('Registrar e enviar')->modalWidth('3xl')
            ->form(fn () => self::formulario())
            ->action(function (array $data, Prospect $record) {
                $pesquisa = collect(self::pesquisas())->firstWhere('id', (int) $data['pesquisa_id']);
                if (! $pesquisa) {
                    Notification::make()->title('Pesquisa indisponível. Atualize a página.')->danger()->send();

                    return;
                }
                try {
                    $envio = app(RegistrarEntrevista::class)->executar($record, $pesquisa, $data, $data['respostas'] ?? [], auth()->id());
                } catch (ObservatorioIndisponivel $e) {
                    // Configuracao incompleta (ex.: chave do pseudonimo ausente): nada foi gravado.
                    Notification::make()->title('Integração com o Observatório incompleta')->body($e->getMessage())->danger()->send();

                    return;
                }
                $situacao = $envio->fresh()->status;
                Notification::make()->title('Entrevista registrada')
                    ->body($situacao === 'enviado' ? 'Enviada ao Observatório.' : 'Registrada. O envio ao Observatório será refeito automaticamente; acompanhe em "Envios ao Observatório".')
                    ->success()->send();
            });
    }

    /** @return array<int, array> */
    private static function pesquisas(): array
    {
        try {
            return app(ObservatorioGateway::class)->pesquisasAtivas();
        } catch (ObservatorioIndisponivel $e) {
            return [];
        }
    }

    private static function formulario(): array
    {
        $pesquisas = self::pesquisas();
        if (! $pesquisas) {
            return [Forms\Components\Placeholder::make('indisponivel')->hiddenLabel()
                ->content('Nenhuma pesquisa ativa disponível agora (Observatório fora do ar, não configurado ou sem pesquisas ativas).')];
        }

        return [
            Forms\Components\Select::make('pesquisa_id')->label('Pesquisa')->required()->native(false)->live()
                ->options(collect($pesquisas)->pluck('titulo', 'id')),
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('contato_nome')->label('Contato'),
                Forms\Components\TextInput::make('uf')->label('UF')->maxLength(2),
                Forms\Components\Select::make('canal')->options(['telefone' => 'Telefone', 'whatsapp' => 'WhatsApp', 'email' => 'E-mail', 'evento' => 'Evento', 'site' => 'Site'])->default('telefone')->native(false),
            ]),
            Forms\Components\Group::make()->schema(fn (Forms\Get $get) => self::camposDasPerguntas($get('pesquisa_id'), $pesquisas))
                ->visible(fn (Forms\Get $get) => filled($get('pesquisa_id'))),
            Forms\Components\Textarea::make('observacao_livre')->label('O que o gestor comentou')->rows(3)
                ->helperText('Vai para o Observatório e não é publicada. Evite nomes e telefones.'),
        ];
    }

    private static function camposDasPerguntas($pesquisaId, array $pesquisas): array
    {
        $pesquisa = collect($pesquisas)->firstWhere('id', (int) $pesquisaId);

        return collect($pesquisa['perguntas'] ?? [])->sortBy('ordem')->map(function (array $p) {
            $nome = 'respostas.'.$p['id'];
            $opcoes = collect($p['opcoes'] ?? [])->mapWithKeys(fn ($o) => [$o => $o])->all();
            $campo = match ($p['tipo']) {
                'unica' => Forms\Components\Radio::make($nome)->options($opcoes),
                'multipla' => Forms\Components\CheckboxList::make($nome)->options($opcoes),
                'escala' => Forms\Components\Radio::make($nome)->options(['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5'])->inline(),
                default => Forms\Components\Textarea::make($nome)->rows(2),
            };

            return $campo->label($p['texto'])->required((bool) $p['obrigatoria']);
        })->values()->all();
    }
}
