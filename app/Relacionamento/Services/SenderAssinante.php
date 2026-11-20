<?php

namespace App\Relacionamento\Services;

use App\Relacionamento\Models\RelInteracao;
use Illuminate\Support\Carbon;

/**
 * Converte o assinante do webhook `subscribers/new` do Sender em interacoes do Relacionamento. Formato
 * conferido numa entrega real (20/11/2026): id, email, firstname, lastname, phone, source (FORM...),
 * subscriber_tags [{id,title}], columns [{title,value}] (campos personalizados, ex. "Empresa"), created_utc.
 * A TAG diz de que formulario veio (config relacionamento.sender_tags); tag sem correspondencia nao gera nada.
 */
class SenderAssinante
{
    /** @return list<array> dados prontos para RegistraInteracao::registrar() (um por tipo distinto de tag reconhecida) */
    public function interacoes(array $p, ?string $tipoPadrao = null): array
    {
        $email = RegistraInteracao::normalizaEmail($p['email'] ?? null);
        if ($email === null) {
            return [];
        }

        $tags = collect($p['subscriber_tags'] ?? [])->pluck('title')->filter()->values();
        $tipos = [];
        foreach ($tags as $titulo) {
            foreach ((array) config('relacionamento.sender_tags', []) as [$trecho, $tipo]) {
                if (str_contains(mb_strtolower($titulo), $trecho)) {
                    $tipos[$tipo] ??= $titulo;
                }
            }
        }

        // Lista exportada de um grupo/formulario sem tags: o tipo padrao assume o lugar da tag.
        if ($tipos === [] && $tipoPadrao !== null) {
            $tipos[$tipoPadrao] = $tipoPadrao;
        }

        $nome = trim(($p['firstname'] ?? '').' '.($p['lastname'] ?? ''));
        $empresa = collect($p['columns'] ?? [])->first(fn ($c) => mb_strtolower((string) ($c['title'] ?? '')) === 'empresa')['value'] ?? null;
        $veioDeFormulario = ($p['source'] ?? null) === 'FORM';

        $saida = [];
        foreach ($tipos as $tipo => $tagTitulo) {
            $saida[] = [
                'tipo' => $tipo,
                'email' => $email,
                'telefone' => $p['phone'] ?? null,
                'nome' => $nome !== '' ? $nome : null,
                'empresa' => $empresa,
                'titulo' => $tipo === RelInteracao::TIPO_GAZETA ? 'Gazeta Oravel' : 'Material: '.$tagTitulo,
                'origem' => 'sender',
                'ocorrido_em' => $this->quando($p['created_utc'] ?? null, $p['created_local'] ?? null),
                // So' formulario carrega o aceite do titular; assinante importado/manual nao prova consentimento.
                'consentimento_origem' => $veioDeFormulario ? 'sender-form' : null,
                'dados' => array_filter(['sender_id' => $p['id'] ?? null, 'tags' => $tags->all(), 'source' => $p['source'] ?? null]),
                // Uma pessoa = uma interacao por tipo, venha do webhook ou do CSV (mesma chave nos dois caminhos).
                'dedupe_key' => 'sender:'.$tipo.':'.sha1($email),
            ];
        }

        return $saida;
    }

    private function quando(?string $utc, ?string $local = null): Carbon
    {
        try {
            if ($utc) {
                return Carbon::parse($utc, 'UTC')->setTimezone(config('app.timezone'));
            }

            return $local ? Carbon::parse($local, config('app.timezone')) : now();
        } catch (\Throwable) {
            return now();
        }
    }
}
