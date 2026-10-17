<?php

namespace App\Filament\Pages;

use App\Models\Tenant;
use App\Models\TenantFiscalSetting;
use App\Services\Fiscal\FiscalException;
use App\Services\Fiscal\FiscalProvider;
use App\Support\Tenancy;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Complemento "Nota fiscal de serviço (NFS-e)": cadastro fiscal da empresa e certificado digital A1. O certificado e a senha vão
 * direto ao emissor e são descartados: o sistema só guarda a validade.
 */
class NotasFiscaisConfig extends Page implements HasForms
{
    use InteractsWithForms;
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Notas fiscais (NFS-e)';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'Notas fiscais de serviço (NFS-e)';

    protected static ?string $slug = 'notas-fiscais-configuracao';

    protected static string $view = 'filament.pages.notas-fiscais-config';

    public ?array $data = [];

    /** @var TemporaryUploadedFile|null */
    public $certificado = null;

    public string $senhaCertificado = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin() && TenantFiscalSetting::moduloLiberado(Tenancy::current()?->id);
    }

    public function mount(): void
    {
        $c = $this->config();

        $this->form->fill($c ? $c->only(array_merge(array_keys(TenantFiscalSetting::OBRIGATORIOS), ['ambiente', 'padrao', 'nome_fantasia', 'inscricao_estadual', 'complemento', 'telefone', 'codigo_tributario_municipio', 'iss_retido', 'emissao_automatica'])) : $this->sugestao());
    }

    public function config(): ?TenantFiscalSetting
    {
        return TenantFiscalSetting::daEmpresa(Tenancy::current()?->id);
    }

    /** Pré-preenche com o que a empresa já informou no cadastro dela na Oravel. */
    private function sugestao(): array
    {
        $t = Tenancy::current() ?? Tenant::withoutGlobalScopes()->find(auth()->user()?->tenant_id);

        return [
            'ambiente' => 'homologacao', 'padrao' => 'municipal', 'iss_retido' => false, 'emissao_automatica' => false,
            'cnpj' => $t?->cpf_cnpj, 'razao_social' => $t?->name, 'inscricao_estadual' => $t?->inscricao_estadual, 'cep' => $t?->cep, 'logradouro' => $t?->logradouro,
            'numero' => $t?->numero, 'bairro' => $t?->bairro, 'municipio' => $t?->cidade, 'uf' => $t?->uf, 'email' => $t?->email_contato, 'telefone' => $t?->telefone,
        ];
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Placeholder::make('situacao')->label('')->content(fn () => $this->situacao()),
            Section::make('Dados da empresa (prestador)')
                ->description('Como constam na prefeitura. O emissor usa estes dados em todas as notas.')
                ->schema([
                    TextInput::make('cnpj')->label('CNPJ')->required()->maxLength(20),
                    TextInput::make('razao_social')->label('Razão social')->required()->maxLength(150),
                    TextInput::make('nome_fantasia')->label('Nome fantasia')->maxLength(150),
                    TextInput::make('inscricao_municipal')->label('Inscrição municipal')->required()->maxLength(30),
                    TextInput::make('inscricao_estadual')->label('Inscrição estadual')->maxLength(30),
                    Select::make('regime_tributario')->label('Regime tributário')->native(false)->required()
                        ->options(['mei' => 'MEI', 'simples' => 'Simples Nacional', 'simples_excesso' => 'Simples Nacional (excesso de sublimite)', 'normal' => 'Lucro presumido ou real']),
                    TextInput::make('cep')->label('CEP')->required()->maxLength(10),
                    TextInput::make('logradouro')->label('Logradouro')->required()->maxLength(150),
                    TextInput::make('numero')->label('Número')->required()->maxLength(20),
                    TextInput::make('complemento')->label('Complemento')->maxLength(100),
                    TextInput::make('bairro')->label('Bairro')->required()->maxLength(100),
                    TextInput::make('municipio')->label('Município')->required()->maxLength(100),
                    TextInput::make('uf')->label('UF')->required()->length(2),
                    TextInput::make('codigo_municipio')->label('Código IBGE do município')->required()->maxLength(10)->helperText('7 dígitos. Ex.: São Paulo = 3550308.'),
                    TextInput::make('email')->label('E-mail da empresa')->email()->required(),
                    TextInput::make('telefone')->label('Telefone')->maxLength(30),
                ])->columns(3),
            Section::make('Como a nota é emitida')
                ->schema([
                    Select::make('padrao')->label('Padrão da prefeitura')->native(false)->required()
                        ->options(['municipal' => 'Municipal (sistema da prefeitura)', 'nacional' => 'NFS-e Nacional'])
                        ->helperText('Se não souber, mantenha Municipal: a Oravel confirma no teste.'),
                    Select::make('ambiente')->label('Ambiente')->native(false)->required()
                        ->options(['homologacao' => 'Teste (homologação): nota sem valor fiscal', 'producao' => 'Produção: nota com valor fiscal']),
                    TextInput::make('item_lista_servico')->label('Item da lista de serviço')->required()->maxLength(20)->helperText('Ex.: 07.02 ou 14.01, conforme o seu serviço.'),
                    TextInput::make('codigo_tributario_municipio')->label('Código de tributação do município')->maxLength(30)->helperText('Só se a sua prefeitura exigir.'),
                    TextInput::make('aliquota_iss')->label('Alíquota do ISS (%)')->numeric()->required()->minValue(0)->maxValue(10),
                    Toggle::make('iss_retido')->label('ISS retido pelo tomador'),
                    Textarea::make('descricao_padrao')->label('Descrição padrão do serviço')->required()->rows(3)->maxLength(1000)
                        ->helperText('Texto que sai na nota. O sistema acrescenta a descrição da conta a receber.')->columnSpanFull(),
                    Toggle::make('emissao_automatica')->label('Emitir a nota sozinho quando a cobrança for paga')
                        ->helperText('Desligado, você emite pelo botão "Emitir NFS-e" em cada conta a receber.'),
                ])->columns(3),
        ]);
    }

    private function situacao(): HtmlString
    {
        $c = $this->config();

        if (! $c || blank($c->provider_company_id)) {
            return new HtmlString('<b>Notas fiscais ainda não ligadas.</b> Preencha os dados, envie o certificado digital A1 e use "Testar conexão e ligar".');
        }

        $cert = $c->certificado_validade ? ' Certificado válido até <b>'.$c->certificado_validade->format('d/m/Y').'</b>.' : ' <b style="color:#b45309">Certificado ainda não enviado.</b>';

        if ($c->enabled) {
            return new HtmlString('<b style="color:#15803d">Ligada</b> em ambiente <b>'.($c->ambiente === 'producao' ? 'de produção' : 'de teste').'</b>.'.$cert);
        }

        return new HtmlString('<b>Desligada.</b>'.$cert.($c->last_error ? ' Último erro: '.e($c->last_error) : ''));
    }

    public function salvar(): void
    {
        $this->gravar();

        Notification::make()->title('Dados fiscais salvos')->success()->send();
    }

    /** Cadastra a empresa no emissor e envia o certificado. O arquivo e a senha são descartados em seguida. */
    public function enviarCertificado(): void
    {
        $this->validate(['certificado' => 'required|file|max:512', 'senhaCertificado' => 'required|string'], [], ['certificado' => 'certificado digital', 'senhaCertificado' => 'senha do certificado']);

        $config = $this->gravar();

        try {
            $r = app(FiscalProvider::class)->sincronizarEmpresa($config, base64_encode($this->certificado->get()), $this->senhaCertificado);
        } catch (FiscalException $e) {
            Notification::make()->title('O emissor recusou o certificado')->body($e->getMessage())->danger()->persistent()->send();

            return;
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Não foi possível falar com o emissor')->body('Tente de novo em instantes.')->danger()->send();

            return;
        } finally {
            $this->certificado?->delete();
            $this->certificado = null;
            $this->senhaCertificado = '';
        }

        $config->update([
            'provider_company_id' => $r['id'], 'token_homologacao' => $r['token_homologacao'] ?? $config->token_homologacao, 'token_producao' => $r['token_producao'] ?? $config->token_producao,
            'certificado_validade' => $r['certificado_validade'] ?? $config->certificado_validade, 'certificado_enviado_em' => now(), 'enabled' => false, 'last_error' => null,
        ]);

        Notification::make()->title('Certificado enviado')->body('Agora use "Testar conexão e ligar".')->success()->send();
    }

    public function testarEAtivar(): void
    {
        $config = $this->gravar();

        if ($falta = $config->faltando()) {
            Notification::make()->title('Faltam dados fiscais')->body('Preencha: '.implode(', ', $falta).'.')->danger()->send();

            return;
        }

        try {
            app(FiscalProvider::class)->testar($config);
        } catch (FiscalException $e) {
            $config->update(['enabled' => false, 'last_test_at' => now(), 'last_test_ok' => false, 'last_error' => mb_substr($e->getMessage(), 0, 300)]);
            Notification::make()->title('Não foi possível ligar')->body($e->getMessage())->danger()->persistent()->send();

            return;
        } catch (\Throwable $e) {
            report($e);
            $config->update(['enabled' => false, 'last_test_at' => now(), 'last_test_ok' => false, 'last_error' => 'Sem conexão com o emissor.']);
            Notification::make()->title('Não foi possível falar com o emissor')->body('Tente de novo em instantes.')->danger()->send();

            return;
        }

        $config->update(['enabled' => true, 'last_test_at' => now(), 'last_test_ok' => true, 'last_error' => null]);

        Notification::make()->title('Notas fiscais ligadas')->body('Conexão ok. Emita pelo botão "Emitir NFS-e" nas contas a receber.')->success()->persistent()->send();
    }

    public function desligar(): void
    {
        $this->config()?->update(['enabled' => false]);

        Notification::make()->title('Notas fiscais desligadas')->success()->send();
    }

    private function gravar(): TenantFiscalSetting
    {
        $tenantId = Tenancy::current()?->id;
        abort_unless($tenantId && auth()->user()?->isAdmin() && TenantFiscalSetting::moduloLiberado($tenantId), 403);

        $d = $this->form->getState();
        $campos = collect($d)->only(array_merge(array_keys(TenantFiscalSetting::OBRIGATORIOS), ['ambiente', 'padrao', 'nome_fantasia', 'inscricao_estadual', 'complemento', 'telefone', 'codigo_tributario_municipio']))->all()
            + ['iss_retido' => (bool) ($d['iss_retido'] ?? false), 'emissao_automatica' => (bool) ($d['emissao_automatica'] ?? false)];
        $campos['cnpj'] = preg_replace('/\D/', '', (string) ($campos['cnpj'] ?? ''));
        $campos['uf'] = strtoupper((string) ($campos['uf'] ?? ''));

        $atual = $this->config();

        return $atual
            ? tap($atual)->update($campos)->refresh()
            : TenantFiscalSetting::withoutGlobalScopes()->create($campos + ['tenant_id' => $tenantId, 'enabled' => false]);
    }
}
