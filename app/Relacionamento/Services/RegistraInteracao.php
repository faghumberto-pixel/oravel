<?php

namespace App\Relacionamento\Services;

use App\Models\Prospect;
use App\Models\SalesLead;
use App\Relacionamento\Models\RelContato;
use App\Relacionamento\Models\RelConteudo;
use App\Relacionamento\Models\RelInteracao;
use Illuminate\Support\Carbon;

/**
 * Ponto unico de entrada de interacoes do modulo Relacionamento Oravel (endpoint do oravel.org,
 * backfill e, depois, WhatsApp/e-mail). Faz tres coisas: resolve a PESSOA (e-mail/telefone),
 * liga a EMPRESA (Prospect/SalesLead) so' quando o casamento e' inequivoco, e grava a interacao de
 * forma idempotente (dedupe_key).
 */
class RegistraInteracao
{
    private const TIPO_CONTEUDO = [
        RelInteracao::TIPO_ARTIGO => 'artigo',
        RelInteracao::TIPO_VIDEO => 'video',
        RelInteracao::TIPO_FERRAMENTA => 'ferramenta',
        RelInteracao::TIPO_PDF => 'pdf',
    ];

    /**
     * @param  array  $d  tipo (obrigatorio); email|telefone|nome|empresa|cidade|estado|visitor_token|consentimento_origem;
     *                    prospect_id|sales_lead_id (forca a empresa, usado pelo backfill); conteudo_slug|tema|titulo|resultado|
     *                    duracao_segundos|origem|utm_*|dados|ocorrido_em|dedupe_key
     */
    public function registrar(array $d): RelInteracao
    {
        if (! empty($d['dedupe_key']) && ($ja = RelInteracao::where('dedupe_key', $d['dedupe_key'])->first())) {
            return $ja;
        }

        $contato = $this->resolverContato($d);

        $prospectId = $d['prospect_id'] ?? $contato?->prospect_id;
        $salesLeadId = $d['sales_lead_id'] ?? $contato?->sales_lead_id;

        if (! empty($d['conteudo_slug'])) {
            $this->garantirConteudo($d);
        }

        return RelInteracao::create([
            'contato_id' => $contato?->id,
            'prospect_id' => $prospectId,
            'sales_lead_id' => $salesLeadId,
            'tipo' => $d['tipo'],
            'conteudo_slug' => $d['conteudo_slug'] ?? null,
            'titulo' => $d['titulo'] ?? null,
            'resultado' => $d['resultado'] ?? null,
            'duracao_segundos' => $d['duracao_segundos'] ?? null,
            'origem' => $d['origem'] ?? null,
            'utm_source' => $d['utm_source'] ?? null,
            'utm_medium' => $d['utm_medium'] ?? null,
            'utm_campaign' => $d['utm_campaign'] ?? null,
            'dados' => $d['dados'] ?? null,
            'ocorrido_em' => isset($d['ocorrido_em']) ? Carbon::parse($d['ocorrido_em']) : now(),
            'dedupe_key' => $d['dedupe_key'] ?? null,
        ]);
    }

    public static function normalizaEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    /** Digitos nacionais (sem +55). Menos de 10 digitos nao identifica ninguem: devolve null. */
    public static function normalizaTelefone(?string $tel): ?string
    {
        $dig = preg_replace('/\D/', '', (string) $tel);
        if (strlen($dig) >= 12 && str_starts_with($dig, '55')) {
            $dig = substr($dig, 2);
        }

        return strlen($dig) >= 10 && strlen($dig) <= 11 ? $dig : null;
    }

    private function resolverContato(array $d): ?RelContato
    {
        $email = self::normalizaEmail($d['email'] ?? null);
        $digitos = self::normalizaTelefone($d['telefone'] ?? null);

        if ($email === null && $digitos === null) {
            return null;
        }

        $contato = ($email ? RelContato::where('email', $email)->first() : null)
            ?? ($digitos ? RelContato::where('telefone_digitos', $digitos)->first() : null);

        $contato ??= new RelContato;

        // Preenche so' o que falta: um evento posterior nunca sobrescreve dado ja confirmado.
        foreach ([
            'email' => $email,
            'telefone' => $d['telefone'] ?? null,
            'telefone_digitos' => $digitos,
            'nome' => $d['nome'] ?? null,
            'empresa_nome' => $d['empresa'] ?? null,
            'cidade' => $d['cidade'] ?? null,
            'estado' => $d['estado'] ?? null,
        ] as $campo => $valor) {
            if (blank($contato->{$campo}) && filled($valor)) {
                $contato->{$campo} = $valor;
            }
        }

        if (! empty($d['visitor_token'])) {
            $tokens = (array) $contato->visitor_tokens;
            if (! in_array($d['visitor_token'], $tokens, true)) {
                $tokens[] = $d['visitor_token'];
                $contato->visitor_tokens = array_slice($tokens, -20);
            }
        }

        if (! empty($d['consentimento_origem']) && $contato->consentimento_em === null) {
            $contato->consentimento_em = now();
            $contato->consentimento_origem = $d['consentimento_origem'];
        }

        if ($contato->prospect_id === null && $contato->sales_lead_id === null) {
            $this->vincularEmpresa($contato, $email, $digitos);
        }

        $contato->save();

        return $contato;
    }

    /** Liga a pessoa a UMA empresa. Ambiguo (0 ou 2+ casamentos) = nao liga: vinculo errado e' pior que nenhum. */
    private function vincularEmpresa(RelContato $contato, ?string $email, ?string $digitos): void
    {
        $porEmail = $email ? Prospect::whereRaw('LOWER(email) = ?', [$email])->limit(2)->get() : collect();

        // Telefone: o cadastro guarda "(19) 98888-7777"; filtra pelo final (4 digitos contiguos) e confere os digitos em PHP
        // (portatil, sem regexp_replace; a base de prospects e' pequena).
        $porTelefone = $digitos
            ? Prospect::where('telefone', 'like', '%'.substr($digitos, -4))->get()
                ->filter(fn (Prospect $p) => self::normalizaTelefone($p->telefone) === $digitos)
            : collect();

        $prospects = $porEmail->merge($porTelefone)->unique('id')->values();

        if ($prospects->count() === 1) {
            $contato->prospect_id = $prospects[0]->id;
            $contato->sales_lead_id = $prospects[0]->sales_lead_id;
        }

        if ($contato->sales_lead_id === null && $email) {
            $leads = SalesLead::whereRaw('LOWER(email) = ?', [$email])->limit(2)->get();
            if ($leads->count() === 1) {
                $contato->sales_lead_id = $leads[0]->id;
            }
        }
    }

    private function garantirConteudo(array $d): void
    {
        $tipo = self::TIPO_CONTEUDO[$d['tipo']] ?? null;
        if ($tipo === null) {
            return;
        }

        $conteudo = RelConteudo::firstOrCreate(
            ['slug' => $d['conteudo_slug']],
            ['tipo' => $tipo, 'titulo' => $d['titulo'] ?? $d['conteudo_slug'], 'tema' => $d['tema'] ?? null],
        );

        // O site pode passar a classificar o tema depois: completa so' se estava vazio.
        if ($conteudo->tema === null && ! empty($d['tema'])) {
            $conteudo->update(['tema' => $d['tema']]);
        }
    }
}
