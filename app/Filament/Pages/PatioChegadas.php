<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\PatioEntry;
use App\Support\Tenancy;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Fila de desmobilizacoes aguardando o Laudo de Recebimento (checklist
 * estruturado da chegada fisica no patio, ver EquipmentPatioArrivalMobile
 * -- mesmo padrao do checklist de desmobilizacao, so' que pra chegada).
 * Antes desta tela virar um checklist de verdade, era so um botao +
 * campo de texto livre; o registro/liberacao do ativo ficava tudo no
 * mesmo passo. Agora o EquipmentPatioArrival nasce em rascunho assim que
 * o Laudo comeca (ver mount() do componente mobile), e so' fica
 * "concluido" (completed_at preenchido) quando o checklist chega a 100%
 * -- entao a fila aqui precisa continuar mostrando quem tem um laudo em
 * andamento, nao so' quem nunca comecou nenhum.
 */
class PatioChegadas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Chegadas no Pátio';

    protected static ?string $title = 'Chegadas e Saídas no Pátio';

    protected static ?string $navigationGroup = 'Logística';

    protected static string $view = 'filament.pages.patio-chegadas';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', EquipmentMovement::class);
    }

    public function getPendingProperty(): Collection
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return collect();
        }

        return EquipmentMovement::where('tenant_id', $tenant->id)
            ->where('type', EquipmentMovement::TYPE_DESMOBILIZACAO)
            ->where('status', EquipmentMovement::STATUS_CONCLUIDO)
            ->where(function ($query) {
                $query->whereDoesntHave('patioArrival')
                    ->orWhereHas('patioArrival', fn ($q) => $q->whereNull('completed_at'));
            })
            ->with(['asset', 'maintenanceOrder.client', 'patioArrival.items'])
            ->latest('completed_at')
            ->get();
    }

    /**
     * Registro de portaria (PatioEntry) -- diferente da fila acima, que e'
     * so' sobre desmobilizacoes ja concluidas aguardando laudo. Aqui e'
     * qualquer veiculo que chegou (visita/fornecedor/entrega/mobilizacao/
     * desmobilizacao), mais recentes primeiro.
     */
    public function getEntriesProperty(): Collection
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return collect();
        }

        return PatioEntry::where('tenant_id', $tenant->id)
            ->with(['asset', 'registeredBy'])
            ->latest('arrived_at')
            ->limit(50)
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->patioEntryAction(PatioEntry::DIRECTION_ENTRADA, 'adicionar_chegada', 'Adicionar Chegada', 'Registrar Chegada no Pátio'),
            $this->patioEntryAction(PatioEntry::DIRECTION_SAIDA, 'registrar_saida', 'Registrar Saída', 'Registrar Saída do Pátio'),
        ];
    }

    /**
     * Mesmo form serve pras duas direcoes -- so' muda o rotulo, o preset
     * de "direction" e quais opcoes de "reason" ficam disponiveis
     * (PatioEntry::reasonLabelsForDirection()).
     */
    private function patioEntryAction(string $direction, string $name, string $label, string $modalHeading): Actions\Action
    {
        return Actions\Action::make($name)
            ->label($label)
            ->icon($direction === PatioEntry::DIRECTION_ENTRADA ? 'heroicon-o-arrow-down-circle' : 'heroicon-o-arrow-up-circle')
            ->color($direction === PatioEntry::DIRECTION_ENTRADA ? 'success' : 'warning')
            ->modalHeading($modalHeading)
            ->form([
                Forms\Components\Hidden::make('direction')->default($direction),
                Forms\Components\TextInput::make('plate')
                    ->label('Placa')
                    ->placeholder('ABC-1234'),
                Forms\Components\Toggle::make('is_company_vehicle')
                    ->label('Veículo é da empresa?'),
                Forms\Components\TextInput::make('driver_name')
                    ->label('Nome do Motorista'),
                Forms\Components\TextInput::make('driver_document')
                    ->label('Documento (RG/CPF/CNH)'),
                Forms\Components\TextInput::make('origin')
                    ->label($direction === PatioEntry::DIRECTION_ENTRADA ? 'Origem' : 'Destino')
                    ->placeholder($direction === PatioEntry::DIRECTION_ENTRADA ? 'De onde vem' : 'Para onde vai'),
                Forms\Components\Select::make('reason')
                    ->label('Motivo')
                    ->options(PatioEntry::reasonLabelsForDirection($direction))
                    ->live()
                    ->required(),
                Forms\Components\Textarea::make('reason_detail')
                    ->label('Detalhe')
                    ->visible(fn (Get $get) => $get('reason') === PatioEntry::REASON_OUTRO)
                    ->required(fn (Get $get) => $get('reason') === PatioEntry::REASON_OUTRO)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('brings_equipment')
                    ->label('Traz equipamento?')
                    ->live(),
                Forms\Components\Toggle::make('equipment_is_company')
                    ->label('O equipamento é da empresa?')
                    ->visible(fn (Get $get) => (bool) $get('brings_equipment')),

                // So' quando o motivo e' desmobilizacao: consulta pelo
                // patrimonio se existe uma mobilizacao registrada pra
                // esse Ativo (Asset::latestMobilizacao(), novo).
                Forms\Components\TextInput::make('patrimonio_busca')
                    ->label('Patrimônio do Equipamento')
                    ->placeholder('Digite o patrimônio pra consultar')
                    ->live(onBlur: true)
                    ->dehydrated(false)
                    ->visible(fn (Get $get) => $get('reason') === PatioEntry::REASON_DESMOBILIZACAO)
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        if (! $state) {
                            return;
                        }

                        $asset = Asset::search($state)->first();

                        if (! $asset) {
                            $set('asset_id', null);
                            Notification::make()->title('Nenhum Ativo encontrado com esse patrimônio.')->warning()->send();

                            return;
                        }

                        $mobilizacao = $asset->latestMobilizacao();

                        if (! $mobilizacao) {
                            $set('asset_id', null);
                            Notification::make()
                                ->title('Ativo encontrado, mas sem mobilização registrada.')
                                ->body("{$asset->patrimonio} — {$asset->name}")
                                ->warning()
                                ->send();

                            return;
                        }

                        $set('asset_id', $asset->id);
                        Notification::make()
                            ->title('Mobilização encontrada.')
                            ->body("{$asset->patrimonio} — {$asset->name}, mobilizado em ".$mobilizacao->completed_at?->format('d/m/Y'))
                            ->success()
                            ->send();
                    }),
                Forms\Components\Hidden::make('asset_id'),
            ])
            ->action(function (array $data) {
                PatioEntry::create([
                    ...$data,
                    'tenant_id' => Tenancy::current()?->id,
                    'arrived_at' => now(),
                    'registered_by_user_id' => auth()->id(),
                ]);

                Notification::make()->title('Registro salvo.')->success()->send();
            });
    }
}
