<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Pages;

use App\Filament\Resources\MaintenanceOrderResource;
use App\Forms\Components\CameraCapture;
use App\Services\OsFolhaAtualizacaoService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

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

    public function getTitle(): string
    {
        return 'Atualizar a O.S. '.$this->record->os_number.' com a folha preenchida';
    }
}
