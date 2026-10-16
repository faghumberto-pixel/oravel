<?php

namespace App\Support;

use App\Models\WebVisit;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Exportação (Excel e PDF) das Visitas do Site, com todos os campos da visita. */
class WebVisitExport
{
    public const PDF_LIMIT = 300;

    public static function headings(): array
    {
        return [
            'Início', 'Última atividade', 'Origem', 'Veio de (URL)', 'Domínio de referência',
            'UTM Source', 'UTM Medium', 'UTM Campaign', 'UTM Term', 'UTM Content',
            'Página de entrada', 'Páginas vistas', 'Tempo ativo', 'Tempo ativo (s)', 'Voltou ao site',
            'Dispositivo', 'Navegador', 'Sistema', 'IP', 'Cidade', 'UF',
            'Jornada (páginas)', 'Cliques', 'User agent', 'Token do visitante', 'Token da sessão',
        ];
    }

    public static function row(WebVisit $v): array
    {
        $jornada = $v->pageviews->map(fn ($p) => $p->entered_at?->format('H:i:s').' '.$p->path
            .' ('.WebAnalytics::duration($p->active_seconds).', '.$p->max_scroll.'%)')->implode(' | ');
        $cliques = $v->events->map(fn ($e) => $e->occurred_at?->format('H:i:s').' '.$e->label
            .($e->path ? ' em '.$e->path : ''))->implode(' | ');

        return [
            $v->started_at?->format('d/m/Y H:i:s'),
            $v->last_activity_at?->format('d/m/Y H:i:s'),
            $v->sourceLabel(), $v->referrer_url, $v->referrer_host,
            $v->utm_source, $v->utm_medium, $v->utm_campaign, $v->utm_term, $v->utm_content,
            $v->landing_path, $v->page_views, WebAnalytics::duration($v->duration_seconds), $v->duration_seconds,
            $v->is_returning ? 'Sim' : 'Não',
            match ($v->device_type) { 'mobile' => 'Celular', 'tablet' => 'Tablet', default => 'Desktop' },
            $v->browser, $v->os, $v->ip_address, $v->city, $v->state,
            $jornada, $cliques, $v->user_agent, $v->visitor_token, $v->session_token,
        ];
    }

    public static function xlsx(Builder $query): string
    {
        $path = tempnam(sys_get_temp_dir(), 'visitas').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::headings()));

        $query->with(['pageviews', 'events'])->chunk(500, function ($visitas) use ($writer) {
            foreach ($visitas as $v) {
                $writer->addRow(Row::fromValues(self::row($v)));
            }
        });

        $writer->close();

        return $path;
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: int} visitas (até PDF_LIMIT) e total */
    public static function pdfData(Builder $query): array
    {
        $total = (clone $query)->count();
        $visitas = $query->with(['pageviews', 'events'])->limit(self::PDF_LIMIT)->get();

        return [$visitas, $total];
    }
}
