<?php

namespace App\Filament\Pages;

use App\Models\AvisoResponsavel;
use App\Models\User;
use App\Support\EventosAviso;
use App\Support\Tenancy;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

/**
 * A empresa escolhe QUEM (pessoas) recebe cada aviso do sistema. Nem toda empresa
 * tem Comercial, Suprimentos etc., então os avisos não dependem de departamento:
 * ver App\Services\DestinatariosAvisos para a regra de quem recebe quando
 * ninguém foi escolhido.
 */
class ResponsaveisAvisos extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Responsáveis pelos Avisos';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Responsáveis pelos Avisos';

    protected static ?string $slug = 'responsaveis-avisos';

    protected static string $view = 'filament.pages.responsaveis-avisos';

    /** @var array<string, array<int, string>> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $tenantId = Tenancy::current()?->id;

        $escolhidos = AvisoResponsavel::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get()
            ->groupBy('evento')
            ->map(fn ($itens) => $itens->pluck('user_id')->all())
            ->all();

        $this->form->fill(['evento' => $escolhidos]);
    }

    public function form(Form $form): Form
    {
        $usuarios = User::withoutGlobalScopes()
            ->where('tenant_id', Tenancy::current()?->id)
            ->where('is_approved', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        $campos = [];
        foreach (EventosAviso::todos() as $chave => $evento) {
            $campos[] = Select::make("evento.{$chave}")
                ->label($evento['label'])
                ->helperText($evento['ajuda'])
                ->options($usuarios)
                ->multiple()
                ->searchable()
                ->placeholder('Ninguém escolhido: usa o padrão');
        }

        return $form
            ->statePath('data')
            ->schema([
                Placeholder::make('explicacao')
                    ->label('')
                    ->content(new HtmlString(
                        'Escolha as <b>pessoas</b> que devem receber cada aviso. Quando nenhuma for escolhida, o aviso vai para quem tem o papel que o sistema '
                        .'já usava (ex.: Comercial) e, se também não houver, para os administradores da empresa. Assim nenhum aviso se perde.'
                    )),
                Section::make('Quem recebe cada aviso')->schema($campos)->columns(2),
            ]);
    }

    public function save(): void
    {
        $tenantId = Tenancy::current()?->id;
        abort_unless($tenantId, 403);

        $dados = $this->form->getState()['evento'] ?? [];
        $validos = User::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id')->all();

        foreach (array_keys(EventosAviso::todos()) as $evento) {
            $ids = array_values(array_intersect((array) ($dados[$evento] ?? []), $validos));

            AvisoResponsavel::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('evento', $evento)->whereNotIn('user_id', $ids)->delete();

            foreach ($ids as $id) {
                AvisoResponsavel::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenantId, 'evento' => $evento, 'user_id' => $id]);
            }
        }

        Notification::make()->title('Responsáveis salvos')->success()->send();
    }
}
