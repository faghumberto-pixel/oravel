<?php

namespace App\Support;

use App\Mail\MaterialGratuitoMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Iscas (materiais gratuitos) do site: origem do lead => material entregue por e-mail.
 * O site (HostGator) só entrega o lead ao app (POST /api/inbound/leads); quem envia o e-mail é o app, que
 * tem a caixa de e-mail configurada e funcionando -- o site não guarda senha de SMTP.
 *
 * Nova isca: 1 item em materiais() + o arquivo em storage/app/private/ + o item em public_html/lead-isca.php
 * + a página em public_html/materiais-gratuitos/<slug>/ (+ card no índice e sitemap). Sem classe nova de e-mail.
 */
class Iscas
{
    private const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * origem => assunto, nome do material (no texto), o que fazer com ele e o arquivo (em storage/app/private/).
     *
     * @return array<string, array{assunto: string, material: string, miolo: string, arquivo: string, mime: string}>
     */
    public static function materiais(): array
    {
        return [
            'isca-planilha-manutencao' => [
                'assunto' => 'Sua planilha de manutenção preventiva (Oravel)',
                'material' => 'planilha de controle de manutenção preventiva',
                'miolo' => 'Abra o arquivo, cadastre seus ativos, defina os intervalos em dias e/ou horas e a planilha mostra o que está em dia, em atenção ou vencido.',
                'arquivo' => 'Oravel_Controle_Manutencao_Preventiva.xlsx',
                'mime' => self::XLSX,
            ],
            'isca-controle-combustivel-oleo' => [
                'assunto' => 'Sua planilha de controle de combustível e óleo (Oravel)',
                'material' => 'planilha de controle de combustível e óleo',
                'miolo' => 'Cadastre seus equipamentos, registre os abastecimentos e as trocas de óleo, e a planilha calcula o consumo médio, o custo por hora ou km e mostra quando é a próxima troca (em dia, atenção ou vencido).',
                'arquivo' => 'Oravel_Controle_Combustivel_Oleo.xlsx',
                'mime' => self::XLSX,
            ],
        ];
    }

    /**
     * Envia o material da isca. null = a origem não é uma isca (nada a fazer);
     * true/false = o e-mail saiu ou não. Envio síncrono de propósito: o site precisa saber se saiu.
     */
    public static function enviar(array $data): ?bool
    {
        if (! isset(self::materiais()[$data['origem'] ?? ''])) {
            return null;
        }

        try {
            Mail::to($data['email'])->sendNow(new MaterialGratuitoMail($data['origem'], (string) ($data['name'] ?? '')));

            return true;
        } catch (\Throwable $e) {
            Log::error('Isca: e-mail não enviado', ['origem' => $data['origem'], 'email' => $data['email'], 'erro' => $e->getMessage()]);

            return false;
        }
    }
}
