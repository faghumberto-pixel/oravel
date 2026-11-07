<?php

namespace App\Filament\Resources\SlaPolicyResource\Pages;

use App\Filament\Resources\SlaPolicyResource;
use App\Services\Sla\SlaCalendar;
use App\Services\Sla\SlaService;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSlaPolicies extends ListRecords
{
    protected static string $resource = SlaPolicyResource::class;

    private const DIAS = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('configurar')->label('Pausas, alerta e horário')->icon('heroicon-o-cog-6-tooth')->color('gray')
                ->modalHeading('Configuração do SLA')->modalWidth('3xl')
                ->fillForm(fn () => $this->valoresAtuais())
                ->form($this->formulario())
                ->action(function (array $data): void {
                    $horarios = [];
                    foreach (self::DIAS as $n => $_) {
                        if (! empty($data["dia_{$n}"])) {
                            $horarios[$n] = [substr((string) ($data["ini_{$n}"] ?? '08:00'), 0, 5), substr((string) ($data["fim_{$n}"] ?? '18:00'), 0, 5)];
                        }
                    }

                    SlaService::salvarSettings(
                        (string) Tenancy::current()?->id,
                        $data['pause_statuses'] ?? [],
                        (int) $data['warn_pct'],
                        (int) $data['escalate_pct'],
                        ['modo' => $data['calendar_mode'] ?? SlaCalendar::MODO_24X7, 'timezone' => $data['timezone'] ?? 'America/Sao_Paulo', 'horarios' => $horarios ?: null],
                    );
                    Notification::make()->title('Configuração de SLA salva')->success()->send();
                }),
            Actions\CreateAction::make(),
        ];
    }

    /** @return array<string, mixed> */
    private function valoresAtuais(): array
    {
        $s = SlaService::settings((string) Tenancy::current()?->id);
        $janelas = SlaCalendar::normalizarJanelas($s->business_hours);
        $valores = [
            'pause_statuses' => $s->pause_statuses, 'warn_pct' => $s->warn_pct, 'escalate_pct' => $s->escalate_pct,
            'calendar_mode' => $s->calendar_mode ?: SlaCalendar::MODO_24X7, 'timezone' => $s->timezone ?: 'America/Sao_Paulo',
        ];

        foreach (self::DIAS as $n => $_) {
            $j = $janelas[$n][0] ?? null;
            $valores["dia_{$n}"] = $j !== null;
            $valores["ini_{$n}"] = $j[0] ?? '08:00';
            $valores["fim_{$n}"] = $j[1] ?? '18:00';
        }

        return $valores;
    }

    /** @return array<int, mixed> */
    private function formulario(): array
    {
        $campos = [
            Forms\Components\Section::make('Pausa e alertas')->schema([
                Forms\Components\CheckboxList::make('pause_statuses')->label('Status que pausam o relógio')
                    ->helperText('Enquanto a OS estiver em um destes status do Kanban, o prazo não corre (ex.: aguardando peça).')
                    ->options(SlaService::STATUS_PAUSA_OPCOES),
                Forms\Components\TextInput::make('warn_pct')->label('Fica "em risco" a partir de')->numeric()->minValue(1)->maxValue(99)->suffix('% do prazo')->required(),
                Forms\Components\TextInput::make('escalate_pct')->label('Escalona (avisa a gerência) a partir de')->numeric()->minValue(100)->maxValue(1000)->suffix('% do prazo')->required()
                    ->helperText('Passou do prazo e continua sem solução até este percentual: aviso de escalonamento.'),
            ])->columns(1),
            Forms\Components\Section::make('Calendário do SLA')
                ->description('Vale para as O.S. abertas daqui para frente: as que já estão abertas mantêm o prazo que já foi calculado.')
                ->schema([
                    Forms\Components\Radio::make('calendar_mode')->label('Como o prazo corre')->live()->required()
                        ->options([SlaCalendar::MODO_24X7 => '24 horas por dia, todos os dias', SlaCalendar::MODO_COMERCIAL => 'Só no horário comercial (dias e horários abaixo, sem feriados)']),
                    Forms\Components\Select::make('timezone')->label('Fuso horário')->searchable()->required()
                        ->options(collect(\DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, 'BR'))->mapWithKeys(fn ($z) => [$z => $z])->all())
                        ->visible(fn (Forms\Get $get) => $get('calendar_mode') === SlaCalendar::MODO_COMERCIAL),
                ]),
        ];

        $dias = [];
        foreach (self::DIAS as $n => $nome) {
            $dias[] = Forms\Components\Grid::make(7)->schema([
                Forms\Components\Toggle::make("dia_{$n}")->label($nome)->live()->columnSpan(3),
                Forms\Components\TimePicker::make("ini_{$n}")->label('Início')->seconds(false)->format('H:i')->columnSpan(2)
                    ->disabled(fn (Forms\Get $get) => ! $get("dia_{$n}")),
                Forms\Components\TimePicker::make("fim_{$n}")->label('Fim')->seconds(false)->format('H:i')->columnSpan(2)
                    ->disabled(fn (Forms\Get $get) => ! $get("dia_{$n}"))
                    ->rules([fn (Forms\Get $get) => function (string $attr, $valor, \Closure $falha) use ($get, $n) {
                        if ($get("dia_{$n}") && substr((string) $valor, 0, 5) <= substr((string) $get("ini_{$n}"), 0, 5)) {
                            $falha('O fim precisa ser depois do início.');
                        }
                    }]),
            ]);
        }

        $campos[] = Forms\Components\Section::make('Dias e horários de expediente')->schema($dias)
            ->visible(fn (Forms\Get $get) => $get('calendar_mode') === SlaCalendar::MODO_COMERCIAL);

        return $campos;
    }
}
