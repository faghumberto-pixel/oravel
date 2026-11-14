<?php

namespace App\Http\Controllers;

use App\Models\Prospect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use PDF;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProspectImpressaoController extends Controller
{
    public const LIMITE = 1500;

    public function show(Request $request)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $formato = $request->query('formato', 'pdf'); // pdf, html, csv, excel
        $filtros = (array) $request->query('filtros', []);
        $busca = $request->query('busca', '');

        $query = Prospect::query()->filtrar($filtros)->busca($busca);
        $total = (clone $query)->count();
        $prospects = $query->orderByDesc('nota')->orderBy('nome')->limit(self::LIMITE)->get();

        $partes = [];
        foreach (Prospect::filtrosAtivos($filtros) as $k => $v) {
            $partes[] = match ($k) {
                'status' => 'Situação: '.(Prospect::STATUS[$v] ?? $v),
                'origem' => 'Lista: '.$v,
                'empresa_nova' => 'Empresas novas',
                'regiao_campinas' => 'Região de Campinas',
                'nota_alta' => 'Nota 8 ou mais',
                'retorno_vencido' => 'Retorno vencido',
                'ano_de' => 'Aberta desde '.$v,
                'ano_ate' => 'Aberta até '.$v,
                default => ucfirst($k).': '.$v,
            };
        }
        if (filled($busca)) {
            $partes[] = 'Busca: "'.$busca.'"';
        }
        $descricao = implode('; ', $partes);

        $coresStatus = [
            'nao_enviado' => ['bg' => '#f59e0b', 'text' => '#fff'],
            'enviado' => ['bg' => '#2563eb', 'text' => '#fff'],
            'respondeu' => ['bg' => '#10b981', 'text' => '#fff'],
            'em_teste' => ['bg' => '#7c3aed', 'text' => '#fff'],
            'sem_interesse' => ['bg' => '#ef4444', 'text' => '#fff'],
            'convertido' => ['bg' => '#0d9488', 'text' => '#fff'],
        ];
        $coresResultado = [
            'nao_atendeu' => ['bg' => '#6b7280', 'text' => '#fff'],
            'nao_respondeu' => ['bg' => '#f59e0b', 'text' => '#fff'],
            'numero_invalido' => ['bg' => '#6b7280', 'text' => '#fff'],
            'ja_tem_sistema' => ['bg' => '#ec4899', 'text' => '#fff'],
            'sem_interesse_momento' => ['bg' => '#f97316', 'text' => '#fff'],
            'sem_interesse' => ['bg' => '#ef4444', 'text' => '#fff'],
            'pediu_email' => ['bg' => '#3b82f6', 'text' => '#fff'],
            'quer_reuniao' => ['bg' => '#06b6d4', 'text' => '#fff'],
            'reuniao_agendada' => ['bg' => '#10b981', 'text' => '#fff'],
            'proposta_enviada' => ['bg' => '#7c3aed', 'text' => '#fff'],
            'contrato_andamento' => ['bg' => '#2563eb', 'text' => '#fff'],
            'fechado' => ['bg' => '#059669', 'text' => '#fff'],
        ];

        if ($formato === 'csv') {
            return $this->csv($prospects, $total, $descricao);
        }
        if ($formato === 'excel') {
            return $this->excel($prospects, $total, $descricao);
        }
        if ($formato === 'html') {
            return response()->view('central.prospects-print', compact('prospects', 'total', 'descricao', 'coresStatus', 'coresResultado'))
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('Cache-Control', 'private, no-store');
        }

        // PDF (default)
        $pdf = PDF::loadView('central.prospects-print', compact('prospects', 'total', 'descricao', 'coresStatus', 'coresResultado'))
            ->setPaper('a4', 'landscape')
            ->setOptions(['dpi' => 96, 'defaultFont' => 'DejaVu Sans']);

        return $pdf->stream('prospects-'.now()->format('Ymd-His').'.pdf');
    }

    private function csv($prospects, $total, $descricao): StreamedResponse
    {
        $filename = 'prospects-'.now()->format('Ymd-His').'.csv';

        return Response::stream(function () use ($prospects) {
            $out = fopen('php://temp', 'r+');
            fputcsv($out, ['Empresa', 'CNPJ', 'Cidade', 'Porte', 'Atividade', 'Aberta', 'Nota', 'Telefone', 'Email', 'Sócio', 'Cargo', 'Situação', 'Resultado', 'Próx. Contato', 'Lote', 'Origem', 'Região Campinas', 'Empresa Nova']);
            foreach ($prospects as $p) {
                fputcsv($out, [
                    $p->nome, $p->cnpj, $p->cidade, $p->porte, $p->atividade, $p->ano_abertura, $p->nota,
                    $p->telefone, $p->email, $p->socio, $p->socio_cargo,
                    \App\Models\Prospect::STATUS[$p->status] ?? $p->status,
                    $p->resultado ? (\App\Models\Prospect::RESULTADOS[$p->resultado] ?? '') : '',
                    $p->proximo_contato?->format('d/m/Y') ?? '',
                    $p->lote ?? '', $p->origem ?? '',
                    $p->regiao_campinas ? 'Sim' : 'Não',
                    $p->empresa_nova ? 'Sim' : 'Não',
                ]);
            }
            rewind($out);
            echo stream_get_contents($out);
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function excel($prospects, $total, $descricao): StreamedResponse
    {
        $filename = 'prospects-'.now()->format('Ymd-His').'.xlsx';

        return Response::stream(function () use ($prospects) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['Empresa', 'CNPJ', 'Cidade', 'Porte', 'Atividade', 'Aberta', 'Nota', 'Telefone', 'Email', 'Sócio', 'Cargo', 'Situação', 'Resultado', 'Próx. Contato', 'Lote', 'Origem', 'Região Campinas', 'Empresa Nova'];
            $sheet->fromArray([$headers], null, 'A1');
            $sheet->getStyle('A1:R1')->getFont()->setBold(true);

            $row = 2;
            foreach ($prospects as $p) {
                $sheet->setCellValue('A'.$row, $p->nome);
                $sheet->setCellValue('B'.$row, $p->cnpj);
                $sheet->setCellValue('C'.$row, $p->cidade);
                $sheet->setCellValue('D'.$row, $p->porte);
                $sheet->setCellValue('E'.$row, $p->atividade);
                $sheet->setCellValue('F'.$row, $p->ano_abertura);
                $sheet->setCellValue('G'.$row, $p->nota);
                $sheet->setCellValue('H'.$row, $p->telefone);
                $sheet->setCellValue('I'.$row, $p->email);
                $sheet->setCellValue('J'.$row, $p->socio);
                $sheet->setCellValue('K'.$row, $p->socio_cargo);
                $sheet->setCellValue('L'.$row, \App\Models\Prospect::STATUS[$p->status] ?? $p->status);
                $sheet->setCellValue('M'.$row, $p->resultado ? (\App\Models\Prospect::RESULTADOS[$p->resultado] ?? '') : '');
                $sheet->setCellValue('N'.$row, $p->proximo_contato?->format('d/m/Y') ?? '');
                $sheet->setCellValue('O'.$row, $p->lote ?? '');
                $sheet->setCellValue('P'.$row, $p->origem ?? '');
                $sheet->setCellValue('Q'.$row, $p->regiao_campinas ? 'Sim' : 'Não');
                $sheet->setCellValue('R'.$row, $p->empresa_nova ? 'Sim' : 'Não');
                $row++;
            }

            foreach (range('A', 'R') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}