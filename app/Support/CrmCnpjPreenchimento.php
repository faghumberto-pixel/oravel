<?php

namespace App\Support;

use App\Models\Client;
use App\Models\CrmLead;
use App\Services\CepGeocodingService;
use App\Services\CnpjLookupService;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;

/**
 * Autopreenchimento de lead e cliente pelo CNPJ (BrasilAPI, via
 * CnpjLookupService). Preenche só o que ainda está vazio: nunca sobrescreve
 * o que a pessoa já digitou.
 */
class CrmCnpjPreenchimento
{
    public const ENTIDADE_LEAD = 'lead';

    public const ENTIDADE_CLIENTE = 'cliente';

    /** Rótulos para a notificação "o que foi preenchido". */
    private const ROTULOS = [
        'company_name' => 'Empresa', 'name' => 'Razão social', 'fantasy_name' => 'Nome fantasia',
        'address' => 'Endereço', 'address_complement' => 'Complemento', 'neighborhood' => 'Bairro',
        'city' => 'Cidade', 'uf' => 'UF', 'state' => 'UF', 'cep' => 'CEP', 'zip_code' => 'CEP',
        'phone' => 'Telefone', 'email' => 'E-mail',
    ];

    /**
     * Traduz o retorno do CnpjLookupService para os campos de cada entidade.
     *
     * @param  array<string, mixed>  $d
     * @return array<string, string|null>
     */
    public static function mapear(string $entidade, array $d): array
    {
        $endereco = trim(implode(', ', array_filter([$d['logradouro'] ?? null, $d['numero'] ?? null]))) ?: null;

        if ($entidade === self::ENTIDADE_LEAD) {
            return [
                'company_name' => $d['razao_social'] ?? null,
                'address' => $endereco,
                'city' => $d['cidade'] ?? null,
                'uf' => $d['uf'] ?? null,
                'cep' => $d['cep'] ?? null,
                'phone' => $d['telefone'] ?? null,
                'email' => $d['email_contato'] ?? null,
            ];
        }

        return [
            'name' => $d['razao_social'] ?? null,
            'fantasy_name' => $d['nome_fantasia'] ?? null,
            'address' => $endereco,
            'address_complement' => $d['complemento'] ?? null,
            'neighborhood' => $d['bairro'] ?? null,
            'city' => $d['cidade'] ?? null,
            'state' => $d['uf'] ?? null,
            'zip_code' => $d['cep'] ?? null,
            'phone' => $d['telefone'] ?? null,
            'email' => $d['email_contato'] ?? null,
        ];
    }

    /**
     * Grava só campos vazios. Devolve os rótulos do que foi preenchido.
     *
     * @param  array<string, string|null>  $mapeado
     * @return array<int, string>
     */
    public static function preencher(array $mapeado, callable $get, callable $set): array
    {
        $preenchidos = [];

        foreach ($mapeado as $campo => $valor) {
            if (blank($valor) || filled($get($campo))) {
                continue;
            }

            $set($campo, $valor);
            $preenchidos[] = self::ROTULOS[$campo] ?? $campo;
        }

        return $preenchidos;
    }

    /** Já existe lead ou cliente deste tenant com o mesmo CNPJ? (só para avisar, nunca bloqueia) */
    public static function duplicidade(string $cnpj, ?string $ignorarLeadId = null, ?string $ignorarClienteId = null): ?string
    {
        $digitos = Cnpj::limpar($cnpj);
        $mesmo = "regexp_replace(coalesce(%s, ''), '\\D', '', 'g') = ?";

        $cliente = Client::query()
            ->when($ignorarClienteId, fn ($q) => $q->whereKeyNot($ignorarClienteId))
            ->where(fn ($q) => $q->whereRaw(sprintf($mesmo, 'document'), [$digitos])->orWhereRaw(sprintf($mesmo, 'cpf_cnpj'), [$digitos]))
            ->first();

        if ($cliente) {
            return "Já existe o cliente \"{$cliente->name}\" com este CNPJ.";
        }

        $lead = CrmLead::query()
            ->when($ignorarLeadId, fn ($q) => $q->whereKeyNot($ignorarLeadId))
            ->whereRaw(sprintf($mesmo, 'document'), [$digitos])
            ->first();

        return $lead ? 'Já existe o lead "'.($lead->company_name ?: $lead->name).'" com este CNPJ.' : null;
    }

    /** Botão "Buscar dados pelo CNPJ" para o campo de documento do lead ou do cliente. */
    public static function action(string $entidade): Action
    {
        return Action::make('buscar_cnpj')
            ->label('Buscar dados pelo CNPJ')
            ->icon('heroicon-o-magnifying-glass')
            ->action(function (Get $get, Set $set, $livewire) use ($entidade) {
                $documento = (string) $get('document');
                $digitos = Cnpj::limpar($documento);

                if (strlen($digitos) === 11) {
                    Notification::make()->title('A busca automática é só para CNPJ')->body('CPF não tem consulta pública.')->warning()->send();

                    return;
                }

                if (! Cnpj::valido($digitos)) {
                    Notification::make()->title('CNPJ inválido')->body('Confira os 14 dígitos e tente de novo.')->warning()->send();

                    return;
                }

                $dados = app(CnpjLookupService::class)->lookup($digitos);

                if (! $dados) {
                    Notification::make()->title('CNPJ não encontrado')->body('A Receita não devolveu dados agora. Tente novamente em instantes ou preencha à mão.')->warning()->send();

                    return;
                }

                $preenchidos = self::preencher(self::mapear($entidade, $dados), $get, $set);

                self::geocodificar($entidade, $get, $set, $preenchidos);

                Notification::make()
                    ->title($preenchidos ? 'Dados preenchidos pelo CNPJ' : 'Nada para preencher')
                    ->body($preenchidos ? implode(', ', array_unique($preenchidos)).'. Revise antes de salvar.' : 'Os campos já estavam preenchidos.')
                    ->success()->send();

                $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;
                $aviso = self::duplicidade(
                    $digitos,
                    $entidade === self::ENTIDADE_LEAD ? $record?->getKey() : null,
                    $entidade === self::ENTIDADE_CLIENTE ? $record?->getKey() : null,
                );

                if ($aviso) {
                    Notification::make()->title('Atenção: possível duplicidade')->body($aviso)->warning()->persistent()->send();
                }
            });
    }

    /** Localiza o endereço no mapa quando o CNPJ trouxe endereço (mesmo critério do preenchimento por CEP). */
    private static function geocodificar(string $entidade, callable $get, callable $set, array $preenchidos): void
    {
        if (! array_intersect($preenchidos, ['Endereço', 'Cidade'])) {
            return;
        }

        $uf = $entidade === self::ENTIDADE_LEAD ? $get('uf') : $get('state');
        $completo = trim(implode(', ', array_filter([$get('address'), $get('city'), $uf])));

        if (! $completo) {
            return;
        }

        $coords = app(CepGeocodingService::class)->geocodeAddress($completo);

        if ($coords) {
            $set('latitude', $coords['latitude']);
            $set('longitude', $coords['longitude']);
        }
    }
}
