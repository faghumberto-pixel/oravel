<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Pages;

use App\Filament\Resources\MaintenanceOrderResource;
use App\Forms\Components\CameraCapture;
use App\Models\CkExecution;
use App\Models\CkTemplate;
use App\Services\Checklists\ChecklistAplicavel;
use App\Services\Checklists\ChecklistFolhaIA;
use App\Services\Checklists\ChecklistsDaOs;
use App\Services\OsFolhaAtualizacaoService;
use App\Support\CkExecucaoExport;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Passo 2 do fluxo "folha impressa pelo sistema": foto da folha preenchida -> a IA le -> a pessoa revisa -> atualiza a MESMA O.S.
 */
class AtualizarComFolha extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = MaintenanceOrderResource::class;

    protected static string $view = 'filament.resources.maintenance-order.atualizar-com-folha';

    protected static ?string $title = 'Atualizar com a folha preenchida';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $preparo = null;

    public ?string $fotoLida = null;

    /** @var list<string> */
    public array $camposEscolhidos = [];

    public bool $aplicarPecas = true;

    public string $novoStatus = 'manter';

    public bool $confirmaReaplicar = false;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(MaintenanceOrderResource::canEdit($this->record), 403);
        $this->form->fill();
        $this->checklistForm->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            CameraCapture::make('foto')->label('Foto da folha preenchida (folha inteira, reta e bem iluminada)'),
        ])->statePath('data');
    }

    public function jaAplicadaEm(): ?string
    {
        return app(OsFolhaAtualizacaoService::class)->jaAplicadaEm($this->record);
    }

    public function lerComIA(): void
    {
        $foto = $this->data['foto'] ?? null;
        if (! is_string($foto) || $foto === '') {
            Notification::make()->title('Envie a foto da folha primeiro.')->warning()->send();

            return;
        }

        $r = app(OsFolhaAtualizacaoService::class)->preparar($this->record, $foto);
        if (! $r['ok']) {
            Notification::make()->title('Não foi possível ler a folha')->body($r['error'])->danger()->send();

            return;
        }

        $this->preparo = $r['preparo'];
        $this->fotoLida = $foto;
        $this->camposEscolhidos = array_keys($this->preparo['campos']);
        $this->aplicarPecas = true;
        $this->confirmaReaplicar = false;
    }

    public function descartar(): void
    {
        $this->preparo = null;
        $this->fotoLida = null;
        $this->camposEscolhidos = [];
    }

    public function aplicar(): mixed
    {
        abort_unless(MaintenanceOrderResource::canEdit($this->record), 403);

        if (! $this->preparo) {
            return null;
        }
        if ($this->jaAplicadaEm() && ! $this->confirmaReaplicar) {
            Notification::make()->title('Esta O.S. já recebeu uma folha')->body('Marque a confirmação para aplicar de novo (as peças seriam lançadas outra vez).')->warning()->send();

            return null;
        }

        $res = app(OsFolhaAtualizacaoService::class)->aplicar($this->record, $this->preparo, [
            'campos' => $this->camposEscolhidos,
            'pecas' => $this->aplicarPecas,
            'status' => $this->novoStatus === 'manter' ? null : $this->novoStatus,
        ], $this->fotoLida, auth()->user());

        Notification::make()->title('O.S. atualizada com a folha')
            ->body(count($res['campos']).' campo(s) e '.$res['pecas'].' peça(s) gravados.')->success()->send();

        return redirect(MaintenanceOrderResource::getUrl('edit', ['record' => $this->record]));
    }

    // ------------------------------------------------------------------ checklist em papel (leitura pela IA)

    /** Estado do formulario da foto do checklist (separado do formulario da folha da OS). */
    /** @var array<string, mixed> */
    public ?array $ckData = [];

    public ?string $ckTemplateId = null;

    /** @var array<string, array<string, mixed>> item_id => linha em conferencia (n, descricao, tipo, critico, opcoes, unidade, resposta, valor, obs, duvida) */
    public array $ckLinhas = [];

    /** @var list<string> fotos das folhas ja lidas (data URL); a ultima leitura soma as respostas das anteriores */
    public array $ckFolhas = [];

    public string $ckLeitura = '';

    public string $ckObservacoes = '';

    protected function getForms(): array
    {
        return ['form', 'checklistForm'];
    }

    public function checklistForm(Form $form): Form
    {
        return $form->schema([
            CameraCapture::make('foto')->label('Foto da folha do checklist (uma folha por vez, inteira, reta e bem iluminada)'),
        ])->statePath('ckData');
    }

    /** @return Collection<int, CkTemplate> checklists que valem para o equipamento desta OS (so quem pode executar checklist ve a secao) */
    public function ckModelos()
    {
        return Gate::allows('create', CkExecution::class) ? app(ChecklistAplicavel::class)->paraOrdemServico($this->record) : collect();
    }

    public function ckPendentes(): int
    {
        return app(ChecklistsDaOs::class)->pendentesDaOs($this->record);
    }

    public function updatedCkTemplateId(): void
    {
        $this->ckDescartar();
    }

    public function ckDescartar(): void
    {
        $this->ckLinhas = [];
        $this->ckFolhas = [];
        $this->ckLeitura = '';
        $this->ckObservacoes = '';
        $this->ckData = [];
    }

    public function lerChecklistComIA(): void
    {
        abort_unless(MaintenanceOrderResource::canEdit($this->record) && Gate::allows('create', CkExecution::class), 403);

        $template = $this->ckModelos()->firstWhere('id', $this->ckTemplateId);

        if (! $template) {
            Notification::make()->title('Escolha o checklist.')->warning()->send();

            return;
        }

        $foto = $this->ckData['foto'] ?? null;
        $foto = is_array($foto) ? ($foto['image'] ?? null) : $foto;

        if (! is_string($foto) || $foto === '') {
            Notification::make()->title('Tire ou envie a foto da folha do checklist primeiro.')->warning()->send();

            return;
        }

        $servico = app(ChecklistFolhaIA::class);
        $lido = $servico->ler($template, $foto);

        if (! $lido['ok']) {
            Notification::make()->title('Não foi possível ler a folha do checklist')->body($lido['erro'])->danger()->send();

            return;
        }

        if ($this->ckLinhas === []) {
            foreach ($servico->itensNumerados($template) as $n => $item) {
                $this->ckLinhas[$item->id] = [
                    'n' => $n, 'descricao' => $item->descricao, 'tipo' => $item->tipo_resposta, 'critico' => (bool) $item->critico, 'opcoes' => (array) $item->opcoes,
                    'unidade' => $item->unidade, 'resposta' => null, 'valor' => null, 'obs' => null, 'duvida' => false,
                ];
            }
        }

        // Soma esta folha ao que ja foi lido: so preenche o que a IA realmente leu nela (nunca apaga o de outra folha).
        foreach ($lido['itens'] as $itemId => $leitura) {
            if (! isset($this->ckLinhas[$itemId])) {
                continue;
            }

            foreach (['resposta', 'valor', 'obs'] as $campo) {
                if ($leitura[$campo] !== null) {
                    $this->ckLinhas[$itemId][$campo] = $leitura[$campo];
                }
            }

            $this->ckLinhas[$itemId]['duvida'] = $leitura['duvida'] || ($this->ckLinhas[$itemId]['duvida'] && $leitura['resposta'] === null && $leitura['valor'] === null);
        }

        if ($lido['leitura'] !== null && $this->ckLeitura === '') {
            $this->ckLeitura = $lido['leitura'];
        }

        $this->ckFolhas[] = $foto;
        $this->ckData = [];
        $lidos = count(array_filter($this->ckLinhas, fn ($l) => $l['resposta'] !== null || $l['valor'] !== null));
        Notification::make()->title('Folha lida')->body("{$lidos} de ".count($this->ckLinhas).' itens lidos. Confira tudo antes de registrar; se o checklist tem mais de uma folha, leia a próxima.')->success()->send();
    }

    public function registrarChecklistLido(): mixed
    {
        abort_unless(MaintenanceOrderResource::canEdit($this->record) && Gate::allows('create', CkExecution::class), 403);

        $template = $this->ckModelos()->firstWhere('id', $this->ckTemplateId);

        if (! $template || $this->ckLinhas === []) {
            return null;
        }

        $ativo = $this->record->asset;

        try {
            $execucao = app(ChecklistFolhaIA::class)->registrar(
                $ativo, $template,
                collect($this->ckLinhas)->map(fn ($l) => ['resposta' => $l['resposta'], 'valor' => $l['valor'], 'obs' => $l['obs']])->all(),
                $this->ckFolhas, $this->record, $this->ckLeitura !== '' ? $this->ckLeitura : null, $this->ckObservacoes, auth()->user(),
            );
        } catch (ValidationException $e) {
            Notification::make()->title('Falta conferir o checklist')->body(collect($e->errors())->flatten()->first())->danger()->send();

            return null;
        }

        Notification::make()->title('Checklist registrado nesta O.S.')->body($execucao->status === CkExecution::STATUS_REPROVADO ? 'Há item crítico não conforme: o equipamento foi sinalizado.' : 'Resultado: '.CkExecucaoExport::STATUS[$execucao->status])
            ->success()->send();
        $this->ckDescartar();

        return redirect(MaintenanceOrderResource::getUrl('edit', ['record' => $this->record]));
    }

    public function getTitle(): string
    {
        return 'Atualizar a O.S. '.$this->record->os_number.' com a folha preenchida';
    }
}
