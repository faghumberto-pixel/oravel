<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Concerns\HasCartoesFiltro;
use App\Filament\Concerns\HasPrintAction;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    use HasCartoesFiltro;
    use HasPrintAction;

    protected static string $resource = UserResource::class;

    protected static string $view = 'filament.resources.lista-com-cartoes';

    /** Cards de totais clicaveis: aprovacao, funcao e atividade recente. */
    protected function definicaoCartoes(): array
    {
        return [
            'total' => ['Total', [], 'gray'],
            'aprovados' => ['Aprovados', ['is_approved' => '1'], 'emerald'],
            'pendentes' => ['Aguardando aprovação', ['is_approved' => '0'], 'amber'],
            'admins' => ['Administradores', ['role' => 'admin'], 'blue'],
            'tecnicos' => ['Técnicos', ['role' => 'tecnico'], 'cyan'],
            'colaboradores' => ['Colaboradores', ['role' => 'colaborador'], 'teal'],
            'semana' => ['Ativos na última semana', ['ativo_semana' => true], 'indigo'],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->printAction(),
        ];
    }

    /** Sem widgets no topo (pedido do usuario 2026-11-03): so' os cards de totais clicaveis. */
    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
