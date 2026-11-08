<?php

namespace App\Services\Checklists;

use App\Models\Asset;
use App\Models\CkExecution;
use App\Models\CkItem;
use App\Models\CkTemplate;
use App\Models\MaintenanceOrder;
use App\Models\User;
use App\Services\AnthropicApiClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Checklist em PAPEL lido pela IA: o mecanico preenche a folha impressa (ver print.partials.checklist-folhas), alguem fotografa, a IA le as
 * marcacoes e a pessoa CONFERE antes de gravar. Nada e gravado por `ler()`: so devolve o que leu. `registrar()` so roda depois da conferencia
 * e passa pelas mesmas regras da tela (item critico nao conforme reprova, deixa o ativo inapto e abre a OS corretiva), com a foto da folha como
 * evidencia no lugar da assinatura e da foto por item. A numeracao dos itens e a mesma da folha impressa (ordem das secoes e dos itens ativos).
 */
class ChecklistFolhaIA
{
    public function __construct(private AnthropicApiClient $client, private ChecklistExecucaoService $execucao) {}

    /**
     * Itens do modelo na numeracao da folha impressa.
     *
     * @return array<int, CkItem> numero => item
     */
    public function itensNumerados(CkTemplate $template): array
    {
        $n = 0;
        $out = [];

        foreach ($this->execucao->itensAtivos($template) as $item) {
            $out[++$n] = $item;
        }

        return $out;
    }

    /**
     * @return array{ok: bool, itens: array<string, array{resposta: ?string, valor: ?string, obs: ?string, duvida: bool}>, leitura: ?string, erro: ?string}
     */
    public function ler(CkTemplate $template, string $fotoDataUrl): array
    {
        [$mime, $base64] = $this->parseDataUrl($fotoDataUrl);

        if (! $base64) {
            return ['ok' => false, 'itens' => [], 'leitura' => null, 'erro' => 'Foto inválida.'];
        }

        $numerados = $this->itensNumerados($template);

        $r = $this->client->send($this->prompt($template, $numerados), [
            ['type' => 'text', 'text' => 'Leia as marcações desta folha de checklist e responda só com o JSON pedido.'],
            ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $base64]],
        ]);

        if (! $r['ok']) {
            return ['ok' => false, 'itens' => [], 'leitura' => null, 'erro' => $r['error']];
        }

        $json = $this->client->parseJson($r['text']);

        if ($json === null) {
            return ['ok' => false, 'itens' => [], 'leitura' => null, 'erro' => 'A resposta da IA não veio em um formato reconhecível.'];
        }

        $itens = [];

        foreach ((array) ($json['itens'] ?? []) as $linha) {
            $n = (int) ($linha['n'] ?? 0);
            $item = $numerados[$n] ?? null;

            if (! $item) {
                continue;
            }

            $itens[$item->id] = $this->normalizar($item, $linha);
        }

        $leitura = isset($json['horimetro_km']) && is_scalar($json['horimetro_km']) ? $this->numero((string) $json['horimetro_km']) : null;

        return ['ok' => true, 'itens' => $itens, 'leitura' => $leitura, 'erro' => null];
    }

    /**
     * Grava o checklist conferido. $respostas: item_id => ['resposta'?, 'valor'?, 'obs'?]. $folhas: fotos da folha (data URL), guardadas em disco privado.
     *
     * @param  array<string, array{resposta?: ?string, valor?: ?string, obs?: ?string}>  $respostas
     * @param  array<int, string>  $folhas
     *
     * @throws ValidationException
     */
    public function registrar(Asset $ativo, CkTemplate $template, array $respostas, array $folhas, ?MaintenanceOrder $os, ?string $leitura, ?string $observacoes, ?User $usuario): CkExecution
    {
        $caminhos = [];

        foreach ($folhas as $dataUrl) {
            [, $base64] = $this->parseDataUrl($dataUrl);

            if (! $base64) {
                continue;
            }

            $caminho = 'ck-execucoes/'.$ativo->tenant_id.'/folhas/'.Str::uuid().'.jpg';
            Storage::disk('local')->put($caminho, base64_decode($base64));
            $caminhos[] = $caminho;
        }

        $dados = [];

        foreach ($this->execucao->itensAtivos($template) as $item) {
            $r = $respostas[$item->id] ?? [];
            $dados[$item->id] = [
                'resposta' => $r['resposta'] ?? null,
                'valor_numerico' => $item->tipo_resposta === 'numerico' ? ($r['valor'] ?? null) : null,
                'valor_texto' => in_array($item->tipo_resposta, ['texto', 'selecao', 'foto'], true) ? ($r['valor'] ?? null) : null,
                'observacao' => $r['obs'] ?? null,
            ];
        }

        $nome = $usuario?->name ?? 'usuário';
        $nota = "Registrado a partir da foto da folha de checklist em papel, lida pela IA e conferida por {$nome}.";

        return $this->execucao->registrar($ativo, $template, [
            'respostas' => $dados, 'origem' => CkExecution::ORIGEM_PAPEL_IA, 'folhas' => $caminhos, 'os_id' => $os?->id,
            'leitura_medidor' => $leitura, 'medidor' => $leitura !== null ? ($ativo->category?->medidor === 'km' ? 'km' : 'horimetro') : null,
            'observacoes' => trim((string) $observacoes."\n".$nota),
        ], $usuario);
    }

    /** @param  array<string, mixed>  $linha */
    private function normalizar(CkItem $item, array $linha): array
    {
        $marca = Str::lower(trim((string) ($linha['marcacao'] ?? '')));
        $valor = isset($linha['valor']) && is_scalar($linha['valor']) ? trim((string) $linha['valor']) : '';
        $obs = isset($linha['obs']) && is_scalar($linha['obs']) && trim((string) $linha['obs']) !== '' ? trim((string) $linha['obs']) : null;
        $duvida = (bool) ($linha['duvida'] ?? false);
        $resposta = null;
        $v = null;

        switch ($item->tipo_resposta) {
            case 'conforme_nao_conforme_na':
                $resposta = match ($marca) {
                    'ok', 'conforme' => ChecklistExecucaoService::RESPOSTA_CONFORME,
                    'nc', 'nao_conforme', 'problema' => ChecklistExecucaoService::RESPOSTA_NAO_CONFORME,
                    'na', 'n/a', 'nao_se_aplica' => ChecklistExecucaoService::RESPOSTA_NA,
                    default => null,
                };
                break;
            case 'numerico':
                $v = $valor !== '' ? $this->numero($valor) : null;
                break;
            case 'selecao':
                foreach ((array) $item->opcoes as $op) {
                    if ($valor !== '' && Str::lower($op) === Str::lower($valor)) {
                        $v = $op;
                    }
                }
                break;
            case 'texto':
                $v = $valor !== '' ? $valor : null;
                break;
            case 'foto':
                $v = in_array($marca, ['ok', 'conforme'], true) || $valor !== '' ? 'Foto registrada na folha' : null;
                break;
        }

        // Marcacao ilegivel ou ausente nunca vira resposta: vai para a conferencia da pessoa, em branco.
        return ['resposta' => $resposta, 'valor' => $v, 'obs' => $obs, 'duvida' => $duvida || ($resposta === null && $v === null && $marca !== '' && $marca !== 'vazio')];
    }

    private function numero(string $texto): ?string
    {
        if (! preg_match('/-?\d+(?:[.,]\d+)?/', $texto, $m)) {
            return null;
        }

        return str_replace(',', '.', $m[0]);
    }

    /** @return array{0: string, 1: ?string} */
    private function parseDataUrl(string $dataUrl): array
    {
        if (! preg_match('#^data:(image/[a-zA-Z0-9.+-]+);base64,(.+)$#s', $dataUrl, $m)) {
            return ['image/jpeg', null];
        }

        return [$m[1], preg_replace('/\s+/', '', $m[2])];
    }

    /** @param  array<int, CkItem>  $numerados */
    private function prompt(CkTemplate $template, array $numerados): string
    {
        $lista = collect($numerados)->map(function (CkItem $i, int $n) {
            $extra = match ($i->tipo_resposta) {
                'numerico' => ' [número'.($i->unidade ? ', '.$i->unidade : '').']',
                'selecao' => ' [uma das opções: '.implode(' / ', (array) $i->opcoes).']',
                'texto' => ' [texto]',
                'foto' => ' [foto anexada: marque ok se a caixa estiver marcada]',
                default => ' [marcar OK, N/C ou N/A]',
            };

            return $n.'. '.$i->descricao.$extra;
        })->implode("\n");

        return <<<PROMPT
Você lê uma foto de uma FOLHA DE CHECKLIST impressa e preenchida à mão por um mecânico. O checklist é "{$template->nome}".

A folha é uma tabela com as colunas: Nº | Item a conferir | OK | N/C | N/A | Valor / observação. Cada item tem 3 caixas (OK, N/C = não conforme, N/A = não se aplica); a marcação é um X, um traço ou a caixa preenchida. Itens de medição têm um espaço para o número, itens de escolha têm caixas para as opções.

Itens do checklist (use exatamente estes números):
{$lista}

Responda SOMENTE com um JSON neste formato, sem texto fora dele:
{"itens":[{"n":1,"marcacao":"ok|nc|na|vazio","valor":"","obs":"","duvida":false}],"horimetro_km":""}

Regras: "marcacao" só pode ser ok, nc, na ou vazio. Use "vazio" quando não houver marca. NUNCA adivinhe: se a marca estiver ilegível, se houver duas caixas marcadas no mesmo item, ou se você não tiver certeza, use "vazio" e "duvida": true. Em "valor" ponha o número escrito (itens de medição), a opção marcada (itens de escolha) ou o texto escrito. Em "obs" copie à mão o que estiver escrito como observação do item. "horimetro_km" é o número escrito no campo Horímetro ou km, se houver. Inclua só os itens que aparecem nesta folha (o checklist pode ter mais de uma folha).
PROMPT;
    }
}
