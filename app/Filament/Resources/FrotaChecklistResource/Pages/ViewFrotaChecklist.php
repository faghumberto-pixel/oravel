<?php

namespace App\Filament\Resources\FrotaChecklistResource\Pages;

use App\Filament\Resources\FrotaChecklistResource;
use App\Models\FrotaChecklist;
use App\Models\FrotaRespostaChecklist;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewFrotaChecklist extends ViewRecord
{
    protected static string $resource = FrotaChecklistResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Dados do checklist')->columns(4)->schema([
                Infolists\Components\TextEntry::make('ativo.name')->label('Veículo'),
                Infolists\Components\TextEntry::make('ativo.placa')->label('Placa')->placeholder('—'),
                Infolists\Components\TextEntry::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn (string $state) => FrotaChecklist::tipoLabels()[$state] ?? $state),
                Infolists\Components\TextEntry::make('situacao')->label('Situação')->badge()
                    ->formatStateUsing(fn (string $state) => FrotaChecklist::situacaoLabels()[$state] ?? $state)
                    ->color(fn (string $state) => FrotaChecklistResource::situacaoCor($state)),
                Infolists\Components\TextEntry::make('motorista.name')->label('Motorista')->placeholder('—'),
                Infolists\Components\TextEntry::make('preenchidoPor.name')->label('Preenchido por')->placeholder('—'),
                Infolists\Components\TextEntry::make('odometro')->label('Odômetro')->suffix(' km')->numeric(thousandsSeparator: '.'),
                Infolists\Components\TextEntry::make('nivel_combustivel')->label('Combustível')->placeholder('—'),
                Infolists\Components\TextEntry::make('concluido_em')->label('Concluído em')->dateTime('d/m/Y H:i'),
                Infolists\Components\TextEntry::make('modelo.nome')->label('Modelo')->placeholder('—'),
                Infolists\Components\TextEntry::make('observacoes')->label('Observações')->placeholder('—')->columnSpan(2),
            ]),
            Infolists\Components\Section::make('Veículo liberado')->columns(3)
                ->visible(fn (FrotaChecklist $record) => $record->situacao === FrotaChecklist::LIBERADO)
                ->schema([
                    Infolists\Components\TextEntry::make('liberadoPor.name')->label('Liberado por'),
                    Infolists\Components\TextEntry::make('liberado_em')->label('Liberado em')->dateTime('d/m/Y H:i'),
                    Infolists\Components\TextEntry::make('motivo_liberacao')->label('Motivo')->columnSpanFull(),
                ]),
            Infolists\Components\Section::make('Problemas novos neste retorno')
                ->description('Itens que estavam OK na saída e agora têm problema: ficam registrados com o motorista deste retorno.')
                ->visible(fn (FrotaChecklist $record) => $record->novosProblemasNoRetorno()->isNotEmpty())
                ->schema([
                    Infolists\Components\TextEntry::make('novos_problemas')->hiddenLabel()->state(fn (FrotaChecklist $record) => $record->novosProblemasNoRetorno()->pluck('descricao_registrada')->all())->bulleted()->color('danger'),
                ]),
            Infolists\Components\Section::make('Respostas')->schema([
                Infolists\Components\RepeatableEntry::make('respostas')->hiddenLabel()->columns(4)->schema([
                    Infolists\Components\TextEntry::make('descricao_registrada')->label('Item')->columnSpan(2),
                    Infolists\Components\TextEntry::make('resultado')->label('Resultado')->badge()
                        ->formatStateUsing(fn (string $state) => FrotaRespostaChecklist::resultadoLabels()[$state] ?? $state)
                        ->color(fn (string $state) => match ($state) {
                            FrotaRespostaChecklist::PROBLEMA => 'danger',
                            FrotaRespostaChecklist::OK => 'success',
                            default => 'gray',
                        }),
                    Infolists\Components\TextEntry::make('valor_numerico')->label('Valor')->placeholder('—')->suffix(fn (FrotaRespostaChecklist $record) => $record->unidade ? ' '.$record->unidade : ''),
                    Infolists\Components\TextEntry::make('observacao')->label('Observação')->placeholder('—')->columnSpanFull(),
                ]),
            ]),
            Infolists\Components\Section::make('Fotos')->collapsible()->schema([
                Infolists\Components\SpatieMediaLibraryImageEntry::make('laterais')->label('Laterais')->collection('laterais')->height(120),
                Infolists\Components\SpatieMediaLibraryImageEntry::make('problemas')->label('Fotos de problemas')->collection('problemas')->height(120),
            ])->columns(2),
            Infolists\Components\Section::make('Assinatura')->collapsible()->schema([
                Infolists\Components\TextEntry::make('assinatura')->hiddenLabel()->html()
                    ->formatStateUsing(fn (?string $state) => $state && str_starts_with($state, 'data:image')
                        ? new HtmlString('<img src="'.e($state).'" alt="Assinatura" style="max-height:120px;background:#27272a;border-radius:8px">')
                        : '—'),
            ]),
        ]);
    }
}
