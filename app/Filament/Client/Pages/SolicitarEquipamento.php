<?php

namespace App\Filament\Client\Pages;

use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\SolicitacaoLocacao;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;

/**
 * Cliente pede equipamento novo (sem contrato fechado ainda) -- vira uma
 * SolicitacaoLocacao com data_saida_prevista (a trava de negócio em
 * SolicitacaoLocacao::booted() exige contract_id OU data_saida_prevista;
 * aqui é sempre a segunda, já que o portal nunca fecha contrato sozinho).
 * Sem user_id de vendedor -- coluna aceita nulo (é assim nas Solicitacoes
 * abertas via ReservasUrgentes/comandos internos também).
 */
class SolicitarEquipamento extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationLabel = 'Solicitar Equipamento';

    protected static string $view = 'filament.client.pages.solicitar-equipamento';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        /** @var Client $client */
        $client = $this->guard()->user();

        return $form
            ->schema([
                Forms\Components\Select::make('category_id')
                    ->label('Categoria do Equipamento')
                    ->options(AssetCategory::where('tenant_id', $client->tenant_id)->pluck('name', 'id'))
                    ->required(),
                Forms\Components\DatePicker::make('data_saida_prevista')
                    ->label('Prazo desejado')
                    ->required(),
                Forms\Components\Textarea::make('observations')
                    ->label('Observações')
                    ->rows(3),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        /** @var Client $client */
        $client = $this->guard()->user();

        $state = $this->form->getState();

        // solicitacoes_locacao.user_id é NOT NULL (sempre um vendedor
        // responsável) -- pedido feito pelo portal não tem vendedor
        // envolvido ainda, então cai no primeiro admin do tenant, que
        // reatribui manualmente se preciso.
        $responsavel = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $client->tenant_id)
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->first();

        SolicitacaoLocacao::create([
            'tenant_id' => $client->tenant_id,
            'user_id' => $responsavel?->id,
            'customer_id' => $client->id,
            'category_id' => $state['category_id'],
            'purpose' => 'Solicitação enviada pelo Portal do Cliente.',
            'data_saida_prevista' => $state['data_saida_prevista'],
            'observations' => $state['observations'] ?? null,
            'status_comercial' => 'proposta_em_andamento',
        ]);

        $this->form->fill();

        Notification::make()
            ->title('Solicitação enviada')
            ->body('A locadora vai analisar e entrar em contato.')
            ->success()
            ->send();
    }

    private function guard(): Guard
    {
        return Auth::guard('client');
    }
}
