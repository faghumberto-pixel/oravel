<?php

namespace App\Filament\Resources\CkTemplateResource\Pages;

use App\Filament\Resources\CkTemplateResource;
use App\Services\Checklists\ChecklistGestao;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCkTemplate extends EditRecord
{
    protected static string $resource = CkTemplateResource::class;

    /** Impressao digital do conteudo antes de salvar: se mudou, a versao sobe (execucoes antigas guardam snapshot). */
    public ?string $hashAntes = null;

    protected function beforeSave(): void
    {
        $this->hashAntes = $this->getRecord()->hashConteudo();
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if (app(ChecklistGestao::class)->registrarEdicao($record, $this->hashAntes)) {
            $record->refresh();
            Notification::make()->info()->title("Checklist atualizado para a versão {$record->versao}")->body('Execuções já feitas continuam com o texto da época.')->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('duplicar')->label('Duplicar')->icon('heroicon-o-document-duplicate')->color('gray')
                ->visible(fn () => CkTemplateResource::canCreate())
                ->form([Forms\Components\TextInput::make('nome')->label('Nome do novo checklist')->required()->maxLength(255)])
                ->fillForm(fn () => ['nome' => $this->getRecord()->nome.' (cópia)'])
                ->action(function (array $data) {
                    $copia = app(ChecklistGestao::class)->duplicar($this->getRecord(), $data['nome']);
                    Notification::make()->success()->title('Checklist duplicado')->send();
                    $this->redirect(CkTemplateResource::getUrl('edit', ['record' => $copia]));
                }),
            Actions\Action::make('restaurarPadrao')->label('Restaurar padrão')->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible(fn () => CkTemplateResource::canEdit($this->getRecord()) && app(ChecklistGestao::class)->podeRestaurar($this->getRecord()))
                ->requiresConfirmation()->modalHeading('Restaurar o padrão deste checklist?')
                ->modalDescription('Substitui TODAS as seções e itens pelo padrão da Oravel e descarta o que a empresa editou neste checklist. Execuções já feitas não mudam. A ação fica registrada com a justificativa.')
                ->form([Forms\Components\Textarea::make('justificativa')->label('Justificativa')->required()->minLength(5)->rows(2)])
                ->action(function (array $data) {
                    $r = app(ChecklistGestao::class)->restaurarPadrao($this->getRecord(), $data['justificativa'], auth()->user());
                    Notification::make()->success()->title('Padrão restaurado')->body("Versão {$r->versao}.")->send();
                    $this->redirect(CkTemplateResource::getUrl('edit', ['record' => $r]));
                }),
            Actions\Action::make('alternarAtivo')->label(fn () => $this->getRecord()->ativo ? 'Desativar' : 'Ativar')->color('gray')
                ->icon(fn () => $this->getRecord()->ativo ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                ->visible(fn () => CkTemplateResource::canEdit($this->getRecord()))
                ->action(function () {
                    $ativo = app(ChecklistGestao::class)->alternarAtivo($this->getRecord());
                    Notification::make()->success()->title($ativo ? 'Checklist ativado' : 'Checklist desativado')->send();
                    $this->redirect(CkTemplateResource::getUrl('edit', ['record' => $this->getRecord()]));
                }),
            Actions\DeleteAction::make()->label('Arquivar')->modalHeading('Arquivar este checklist?')
                ->modalDescription('Ele sai das listas de execução. As execuções já feitas continuam no histórico.')
                ->successRedirectUrl(CkTemplateResource::getUrl('index')),
        ];
    }
}
