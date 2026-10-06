<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrotaColetaOleoUsadoResource\Pages;
use App\Models\FrotaColetaOleoUsado;
use App\Models\FrotaTrocaOleo;
use App\Services\Frota\OleoService;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/** Coleta do óleo usado (destinação legal): liga a coleta às trocas que geraram o óleo. */
class FrotaColetaOleoUsadoResource extends BaseResource
{
    protected static ?string $model = FrotaColetaOleoUsado::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Coleta de óleo usado';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'Coleta de óleo usado';

    protected static ?string $pluralModelLabel = 'Coletas de óleo usado';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('coletada_em', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('registrar_coleta')->label('Registrar coleta')->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\DatePicker::make('coletada_em')->label('Data da coleta')->required()->default(now()),
                        Forms\Components\TextInput::make('empresa_coletora')->label('Empresa coletora')->required()->maxLength(191),
                        Forms\Components\TextInput::make('numero_documento')->label('Nº do certificado / documento')->maxLength(100),
                        Forms\Components\Select::make('trocas')->label('Trocas coletadas (sem destinação)')->multiple()->required()->native(false)
                            ->options(fn () => FrotaTrocaOleo::query()->pendenteDeDestinacao()->with('veiculo')->orderBy('realizado_em')->get()
                                ->mapWithKeys(fn (FrotaTrocaOleo $t) => [$t->id => ($t->veiculo?->placa ?? $t->veiculo?->name).' — '.$t->realizado_em->format('d/m/Y').' — '.(int) $t->litros.' L'])->all()),
                        Forms\Components\Textarea::make('observacoes')->label('Observações')->rows(2),
                    ])
                    ->action(function (array $data) {
                        try {
                            $trocas = $data['trocas'];
                            unset($data['trocas']);
                            app(OleoService::class)->registrarColeta($data, $trocas, (string) Tenancy::current()?->id);
                            Notification::make()->title('Coleta registrada')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
                        }
                    }),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('coletada_em')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('empresa_coletora')->label('Empresa')->searchable(),
                Tables\Columns\TextColumn::make('litros')->label('Litros')->suffix(' L'),
                Tables\Columns\TextColumn::make('numero_documento')->label('Documento')->placeholder('—'),
                Tables\Columns\TextColumn::make('trocas_count')->label('Trocas')->counts('trocas'),
            ])
            ->emptyStateHeading('Nenhuma coleta registrada');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListFrotaColetasOleoUsado::route('/')];
    }
}
