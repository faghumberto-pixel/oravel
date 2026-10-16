<?php

namespace App\Services\CrmImport;

use App\Models\Client;
use App\Models\CrmLead;
use App\Support\CrmCamposCustom;
use Illuminate\Support\Str;

/** Campos importáveis de cada destino (lead, cliente, contato) e sugestão de mapeamento por cabeçalho. */
class CrmImportDestinos
{
    public const LEAD = 'lead';

    public const CLIENTE = 'cliente';

    public const CONTATO = 'contato';

    public static function rotulos(): array
    {
        return [self::LEAD => 'Leads', self::CLIENTE => 'Clientes', self::CONTATO => 'Contatos (de clientes ou leads)'];
    }

    /**
     * @return array<string, array{rotulo: string, tipo: string, obrigatorio: bool, sinonimos: list<string>}>
     */
    public static function campos(string $destino, ?string $tenantId = null): array
    {
        $c = fn (string $rotulo, string $tipo = 'texto', array $sinonimos = [], bool $obrigatorio = false) => [
            'rotulo' => $rotulo, 'tipo' => $tipo, 'obrigatorio' => $obrigatorio, 'sinonimos' => $sinonimos,
        ];

        $campos = match ($destino) {
            self::LEAD => [
                'name' => $c('Nome do contato', 'texto', ['nome', 'contato', 'nome do contato', 'responsavel pelo contato'], true),
                'company_name' => $c('Empresa', 'texto', ['empresa', 'razao social', 'nome da empresa', 'cliente']),
                'document' => $c('CNPJ/CPF', 'doc', ['cnpj', 'cpf', 'cnpj/cpf', 'documento', 'cpf/cnpj']),
                'email' => $c('E-mail', 'email', ['email', 'e-mail', 'e mail']),
                'phone' => $c('Telefone', 'texto', ['telefone', 'fone', 'tel']),
                'whatsapp' => $c('WhatsApp', 'texto', ['whatsapp', 'celular', 'whats']),
                'address' => $c('Endereço', 'texto', ['endereco', 'logradouro', 'rua']),
                'city' => $c('Cidade', 'texto', ['cidade', 'municipio']),
                'uf' => $c('UF', 'uf', ['uf', 'estado']),
                'cep' => $c('CEP', 'texto', ['cep']),
                'segment' => $c('Segmento', 'segmento', ['segmento', 'ramo', 'setor']),
                'estimated_value' => $c('Valor estimado', 'dinheiro', ['valor', 'valor estimado', 'oportunidade', 'valor da oportunidade']),
                'origem' => $c('Origem', 'origem', ['origem', 'fonte', 'canal']),
                'etapa' => $c('Etapa do funil', 'etapa', ['etapa', 'estagio', 'fase', 'status']),
                'responsavel' => $c('Responsável (e-mail)', 'usuario', ['responsavel', 'vendedor', 'e-mail do responsavel', 'email do vendedor']),
                'equipment_interest' => $c('Equipamento de interesse', 'texto', ['equipamento', 'interesse', 'equipamento de interesse', 'observacao', 'observacoes']),
            ],
            self::CLIENTE => [
                'name' => $c('Razão social', 'texto', ['razao social', 'nome', 'cliente', 'empresa'], true),
                'fantasy_name' => $c('Nome fantasia', 'texto', ['nome fantasia', 'fantasia']),
                'document' => $c('CNPJ/CPF', 'doc', ['cnpj', 'cpf', 'cnpj/cpf', 'documento', 'cpf/cnpj']),
                'state_registration' => $c('Inscrição estadual', 'texto', ['inscricao estadual', 'ie']),
                'municipal_registration' => $c('Inscrição municipal', 'texto', ['inscricao municipal', 'im']),
                'tax_regime' => $c('Regime tributário', 'texto', ['regime tributario', 'regime']),
                'activity_type' => $c('Nicho', 'nicho', ['nicho', 'segmento', 'ramo']),
                'contact_name' => $c('Pessoa de contato', 'texto', ['contato', 'pessoa de contato', 'nome do contato']),
                'email' => $c('E-mail', 'email', ['email', 'e-mail', 'e mail']),
                'phone' => $c('Telefone', 'texto', ['telefone', 'fone', 'tel']),
                'whatsapp' => $c('WhatsApp', 'texto', ['whatsapp', 'celular', 'whats']),
                'email_financial' => $c('E-mail financeiro', 'email', ['email financeiro', 'e-mail financeiro']),
                'email_purchasing' => $c('E-mail suprimentos', 'email', ['email suprimentos', 'e-mail compras', 'email compras']),
                'address' => $c('Endereço', 'texto', ['endereco', 'logradouro', 'rua']),
                'address_complement' => $c('Complemento', 'texto', ['complemento']),
                'neighborhood' => $c('Bairro', 'texto', ['bairro']),
                'city' => $c('Cidade', 'texto', ['cidade', 'municipio']),
                'state' => $c('UF', 'uf', ['uf', 'estado']),
                'zip_code' => $c('CEP', 'texto', ['cep']),
                'delivery_address' => $c('Endereço da obra', 'texto', ['endereco da obra', 'local de entrega', 'obra']),
            ],
            default => [
                'name' => $c('Nome do contato', 'texto', ['nome', 'contato', 'nome do contato'], true),
                'role_title' => $c('Cargo', 'texto', ['cargo', 'funcao']),
                'phone' => $c('Telefone', 'texto', ['telefone', 'celular', 'fone', 'whatsapp']),
                'email' => $c('E-mail', 'email', ['email', 'e-mail']),
                'is_primary' => $c('Contato principal (sim/não)', 'booleano', ['principal', 'contato principal']),
                'pai_documento' => $c('CNPJ/CPF do cliente ou lead', 'doc', ['cnpj', 'cpf', 'cnpj do cliente', 'documento', 'cnpj/cpf']),
                'pai_nome' => $c('Nome/razão social do cliente ou lead', 'texto', ['cliente', 'empresa', 'razao social', 'razao social do cliente']),
            ],
        };

        if ($destino !== self::CONTATO) {
            foreach (CrmCamposCustom::campos($destino === self::LEAD ? 'lead' : 'cliente', true, $tenantId) as $campo) {
                $campos['custom.'.$campo->chave] = $c($campo->nome.' (personalizado)', 'personalizado', [$campo->nome]);
            }
        }

        return $campos;
    }

    /** @return array<string, string> segmento: rótulo => slug (para o lead). */
    public static function segmentos(): array
    {
        return array_flip(CrmLead::segmentLabels());
    }

    /** @return array<string, string> nicho: rótulo => slug (para o cliente). */
    public static function nichos(): array
    {
        return array_flip(Client::nicheLabels());
    }

    public static function normalizar(?string $texto): string
    {
        $t = Str::ascii(trim((string) $texto));

        return trim(preg_replace('/[^a-z0-9\/]+/', ' ', mb_strtolower($t)));
    }

    /**
     * Casa cada campo do destino com a coluna da planilha de cabeçalho mais parecido.
     *
     * @param  array<int, string>  $cabecalhos
     * @return array<string, int|null> campo => índice da coluna
     */
    public static function sugerirMapeamento(array $cabecalhos, string $destino, ?string $tenantId = null): array
    {
        $normalizados = array_map([self::class, 'normalizar'], $cabecalhos);
        $usadas = [];
        $mapa = [];

        foreach (self::campos($destino, $tenantId) as $chave => $def) {
            $candidatos = array_map([self::class, 'normalizar'], array_merge([$def['rotulo']], $def['sinonimos']));
            $achado = null;

            foreach ($normalizados as $i => $nome) {
                if (! in_array($i, $usadas, true) && in_array($nome, $candidatos, true)) {
                    $achado = $i;
                    $usadas[] = $i;
                    break;
                }
            }

            $mapa[$chave] = $achado;
        }

        return $mapa;
    }

    /** Linha de exemplo + cabeçalhos do modelo CSV de cada destino. */
    public static function modeloCsv(string $destino, ?string $tenantId = null): string
    {
        $campos = self::campos($destino, $tenantId);
        $exemplos = [
            'name' => 'Maria Souza', 'company_name' => 'Construtora Exemplo Ltda', 'document' => '11.222.333/0001-81',
            'email' => 'maria@exemplo.com.br', 'phone' => '(19) 3333-4444', 'whatsapp' => '(19) 99999-8888',
            'city' => 'Campinas', 'uf' => 'SP', 'cep' => '13010-000', 'estimated_value' => '15000,00',
            'fantasy_name' => 'Exemplo Construções', 'state' => 'SP', 'zip_code' => '13010-000',
            'role_title' => 'Gerente de compras', 'is_primary' => 'sim', 'pai_documento' => '11.222.333/0001-81',
        ];

        $linha = fn (array $v) => implode(';', array_map(fn ($c) => '"'.str_replace('"', '""', (string) $c).'"', $v))."\r\n";
        $cabecalho = array_map(fn ($d) => $d['rotulo'], array_values($campos));
        $exemplo = array_map(fn ($k) => $exemplos[$k] ?? '', array_keys($campos));

        return "\xEF\xBB\xBF".$linha($cabecalho).$linha($exemplo);
    }
}
