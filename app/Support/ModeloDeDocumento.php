<?php

namespace App\Support;

use App\Models\Contract;
use App\Models\CrmProposta;
use App\Models\DocumentoModelo;
use App\Models\Quote;
use Illuminate\Support\Facades\Storage;

/**
 * Aplica o modelo de documento da empresa (DocumentoModelo padrão do tipo) a um orçamento, proposta ou contrato na hora de imprimir ou enviar:
 * troca os marcadores ({{cliente}}, {{valor_total}}...) pelos dados do documento e entrega logo, cabeçalho, seções ou cláusulas, termos e rodapé
 * prontos para as telas de impressão. Sem modelo, devolve null e vale o padrão do sistema.
 */
class ModeloDeDocumento
{
    /**
     * @param  array<string, string|null>  $dados  marcador (sem chaves) => valor
     * @param  string|null  $tenantId  empresa dona do documento (sempre informe ao imprimir um documento existente)
     * @return array{nome: string, logo: ?string, cabecalho: ?string, campos: list<array{titulo: string, texto: string}>, termos: ?string, rodape: ?string}|null
     */
    public static function carregar(string $tipo, array $dados, ?string $tenantId = null): ?array
    {
        $modelo = DocumentoModelo::padraoDe($tipo, $tenantId);
        if (! $modelo) {
            return null;
        }

        $campos = [];
        foreach ((array) $modelo->campos as $campo) {
            $campos[] = ['titulo' => self::preencher((string) ($campo['titulo'] ?? ''), $dados), 'texto' => self::preencher((string) ($campo['texto'] ?? ''), $dados)];
        }

        return [
            'nome' => $modelo->nome,
            'logo' => self::logo($modelo),
            'cabecalho' => $modelo->cabecalho ? self::preencher($modelo->cabecalho, $dados) : null,
            'campos' => $campos,
            'termos' => $modelo->termos ? self::preencher($modelo->termos, $dados) : null,
            'rodape' => $modelo->rodape ? self::preencher($modelo->rodape, $dados) : null,
        ];
    }

    /** Troca {{marcador}} pelo valor; marcador sem valor vira "—" (nunca fica a chave crua no documento do cliente). */
    public static function preencher(string $texto, array $dados): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', function (array $m) use ($dados) {
            $valor = $dados[strtolower($m[1])] ?? null;

            return filled($valor) ? (string) $valor : '—';
        }, $texto);
    }

    /** Logo como data URI (o PDF não acessa o disco privado por URL). Arquivo ausente ou grande demais: sem logo. */
    private static function logo(DocumentoModelo $modelo): ?string
    {
        $caminho = $modelo->logo_path;
        if (! $caminho || ! Storage::disk('local')->exists($caminho) || Storage::disk('local')->size($caminho) > 2 * 1024 * 1024) {
            return null;
        }
        $mime = Storage::disk('local')->mimeType($caminho) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($caminho));
    }

    private static function moeda(float|string|null $valor): string
    {
        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }

    public static function doOrcamento(Quote $q): ?array
    {
        return self::carregar(DocumentoModelo::TIPO_ORCAMENTO, self::dadosOrcamento($q), (string) $q->tenant_id);
    }

    public static function doContrato(Contract $c): ?array
    {
        return self::carregar(DocumentoModelo::TIPO_CONTRATO, self::dadosContrato($c), (string) $c->tenant_id);
    }

    public static function daPropostaCrm(CrmProposta $p): ?array
    {
        return self::carregar(DocumentoModelo::TIPO_PROPOSTA, self::dadosPropostaCrm($p), (string) $p->tenant_id);
    }

    /** @return array<string, string|null> */
    public static function dadosOrcamento(Quote $q): array
    {
        return [
            'cliente' => $q->client?->name, 'cnpj' => $q->client?->cpf_cnpj, 'endereco_cliente' => $q->client?->address, 'data' => now()->format('d/m/Y'),
            'numero' => substr((string) $q->id, 0, 8), 'valor_total' => self::moeda($q->total_value), 'validade' => null, 'empresa' => Relatorio::emissor(),
        ];
    }

    /** @return array<string, string|null> */
    public static function dadosContrato(Contract $c): array
    {
        return [
            'cliente' => $c->client?->name, 'cnpj' => $c->client?->cpf_cnpj, 'endereco_cliente' => $c->client?->address, 'data' => now()->format('d/m/Y'),
            'numero' => (string) $c->contract_number, 'valor_total' => self::moeda($c->price), 'validade' => $c->end_date?->format('d/m/Y'), 'empresa' => Relatorio::emissor(),
        ];
    }

    /** @return array<string, string|null> */
    public static function dadosPropostaCrm(CrmProposta $p): array
    {
        return [
            'cliente' => $p->destinatarioNome(), 'cnpj' => null, 'endereco_cliente' => null, 'data' => now()->format('d/m/Y'), 'numero' => (string) $p->numero,
            'valor_total' => self::moeda($p->total), 'validade' => $p->validade_ate?->format('d/m/Y'), 'empresa' => $p->tenant?->name ?? Relatorio::emissor(),
        ];
    }
}
