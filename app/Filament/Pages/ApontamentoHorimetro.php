<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\HorimeterReading;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;

/**
 * Apontamento manual de horímetro (sem telemetria) -- ação rápida, fora do
 * fluxo de uma O.S. A trava real de reset/salto vive em
 * HorimeterReadingObserver; aqui só antecipamos visualmente quando o
 * checkbox de confirmação provavelmente vai ser necessário, pra não
 * surpreender o usuário só no submit.
 */
class ApontamentoHorimetro extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'PCM - Manutenção';
    protected static ?string $navigationLabel = 'Apontamento de Horímetro';

    protected static ?string $title = 'Apontamento de Horímetro';

    protected static string $view = 'filament.pages.apontamento-horimetro';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('create', HorimeterReading::class);
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    private static function ultimaLeitura(?string $assetId): ?HorimeterReading
    {
        if (! $assetId) {
            return null;
        }

        return HorimeterReading::query()->latestForAsset($assetId)->first();
    }

    /**
     * Mesma regra do HorimeterReadingObserver, só que aqui é "e se" (pra
     * decidir se mostra o checkbox), não uma trava -- o Observer que
     * decide de verdade no save.
     */
    private static function precisaConfirmarReset(Get $get): bool
    {
        $ultima = self::ultimaLeitura($get('asset_id'));

        if (! $ultima || ! is_numeric($get('reading'))) {
            return false;
        }

        $atual = (float) $get('reading');
        $anterior = (float) $ultima->reading;
        $threshold = (float) config('oravel.horimeter_jump_threshold', 500);

        return $atual < $anterior || ($atual - $anterior) > $threshold;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('asset_id')
                ->label('Ativo')
                ->options(fn () => Asset::selectOptions())
                ->searchable()
                ->preload()
                ->required()
                ->live(),

            Placeholder::make('ultima_leitura')
                ->label('Última leitura registrada')
                ->content(function (Get $get) {
                    $ultima = self::ultimaLeitura($get('asset_id'));

                    if (! $get('asset_id')) {
                        return 'Selecione um ativo.';
                    }

                    if (! $ultima) {
                        return 'Nenhum apontamento registrado ainda pra este ativo.';
                    }

                    return number_format((float) $ultima->reading, 2, ',', '.')
                        .'h, em '.$ultima->recorded_at->format('d/m/Y H:i')
                        .' ('.HorimeterReading::sourceLabels()[$ultima->source].')';
                })
                ->visible(fn (Get $get) => (bool) $get('asset_id')),

            TextInput::make('reading')
                ->label('Leitura atual (horas)')
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->required()
                ->live(onBlur: true),

            Toggle::make('reset_confirmed')
                ->label('Confirmo que este valor está correto')
                ->helperText('Leitura menor que a anterior, ou salto grande demais -- confirme pra prosseguir mesmo assim.')
                ->visible(fn (Get $get) => self::precisaConfirmarReset($get)),

            // capture=environment abre a camera direto, entao aqui a foto e'
            // sempre em resolucao cheia de celular -- mesmo estouro dos tetos de
            // upload do servidor descrito em MaintenanceOrderResource (aba
            // "Vistoria / Checklist"). Resize no navegador.
            FileUpload::make('photo_path')
                ->label('Foto do painel (opcional)')
                ->image()
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth('1600')
                ->imageResizeTargetHeight('1600')
                ->imageResizeUpscale(false)
                ->directory('horimeter-readings')
                ->extraInputAttributes(['capture' => 'environment']),

            Textarea::make('notes')
                ->label('Observações')
                ->rows(3),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        try {
            HorimeterReading::create([
                'asset_id' => $data['asset_id'],
                'reading' => $data['reading'],
                'recorded_at' => now(),
                'recorded_by' => auth()->id(),
                'source' => HorimeterReading::SOURCE_MANUAL,
                'reset_confirmed' => (bool) ($data['reset_confirmed'] ?? false),
                'notes' => $data['notes'] ?? null,
                'photo_path' => $data['photo_path'] ?? null,
            ]);
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Não foi possível registrar o apontamento')
                ->body(collect($e->errors())->flatten()->implode(' '))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Apontamento de horímetro registrado')
            ->success()
            ->send();

        $this->form->fill();
    }
}
