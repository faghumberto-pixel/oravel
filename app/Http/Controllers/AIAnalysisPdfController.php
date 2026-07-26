<?php

namespace App\Http\Controllers;

use App\Filament\Resources\AIAnalysisResource;
use App\Models\AIAnalysis;
use Barryvdh\DomPDF\Facade\Pdf;

class AIAnalysisPdfController extends Controller
{
    public function download(AIAnalysis $record)
    {
        abort_unless($record->status === AIAnalysis::STATUS_CONCLUIDA, 404);

        $pdf = Pdf::loadView('pdf.ai_analysis_report', [
            'analysis' => $record->load('user', 'equipmentDamage.asset'),
            'typeLabel' => AIAnalysisResource::typeLabels()[$record->type] ?? $record->type,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download("analise-ia-{$record->type}-{$record->id}.pdf");
    }
}
