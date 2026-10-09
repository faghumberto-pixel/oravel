<?php

namespace App\Filament\Pages;

use App\Mail\TenantAwareTransport;
use App\Models\TenantMailSetting;
use App\Support\Tenancy;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

/**
 * A empresa cadastra a caixa de e-mail DELA. Ativa, tudo que o sistema enviar em nome
 * da empresa sai por essa caixa, com o endereço da empresa e a reputação separada das
 * outras. Sem cadastro (ou desativada) continua valendo a caixa da Oravel.
 * Ver App\Mail\TenantAwareTransport.
 */
class EmailDaEmpresa extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'E-mail da Empresa';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'E-mail da Empresa';

    protected static ?string $slug = 'email-da-empresa';

    protected static string $view = 'filament.pages.email-da-empresa';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $config = $this->config();

        $this->form->fill($config ? [
            'host' => $config->host, 'port' => $config->port, 'security' => $config->security,
            'username' => $config->username, 'from_address' => $config->from_address, 'from_name' => $config->from_name,
        ] : ['port' => 465, 'security' => 'ssl', 'from_name' => Tenancy::current()?->name]);
    }

    public function config(): ?TenantMailSetting
    {
        return TenantMailSetting::withoutGlobalScopes()->where('tenant_id', Tenancy::current()?->id)->first();
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Placeholder::make('situacao')->label('')->content(fn () => $this->situacao()),
            Section::make('Servidor de e-mail da empresa')
                ->description('Dados que o provedor do e-mail da sua empresa informa (Gmail, Titan, Locaweb, Microsoft 365...). Em geral estão na ajuda do provedor, em "configurar e-mail em outro programa" ou "SMTP".')
                ->schema([
                    TextInput::make('host')->label('Servidor (SMTP)')->placeholder('smtp.titan.email')->required()->maxLength(255),
                    TextInput::make('port')->label('Porta')->numeric()->required(),
                    Select::make('security')->label('Segurança')->options(['ssl' => 'SSL (porta 465)', 'tls' => 'TLS (porta 587)', 'none' => 'Nenhuma'])->required()->native(false),
                    TextInput::make('username')->label('Usuário (geralmente o e-mail completo)')->required()->maxLength(255),
                    TextInput::make('password')->label('Senha')->password()->revealable()->autocomplete('new-password')
                        ->required(fn () => ! $this->config())
                        ->helperText(fn () => $this->config() ? 'Deixe em branco para manter a senha atual. Ela fica guardada criptografada e nunca é mostrada.' : 'Fica guardada criptografada e nunca é mostrada de novo.'),
                    TextInput::make('from_address')->label('E-mail que aparece como remetente')->email()->required()->maxLength(255),
                    TextInput::make('from_name')->label('Nome que aparece como remetente')->maxLength(120),
                ])->columns(2),
        ]);
    }

    private function situacao(): HtmlString
    {
        $config = $this->config();

        if (! $config) {
            return new HtmlString('<b>Usando a caixa da Oravel.</b> Os e-mails saem como "'.e(Tenancy::current()?->name ?? 'sua empresa').' via Oravel". Cadastre a caixa da sua empresa abaixo para enviar com o seu próprio endereço.');
        }

        if ($config->enabled) {
            return new HtmlString('<b style="color:#16a34a">Ativo.</b> Os e-mails da empresa saem por <b>'.e($config->from_address).'</b>.');
        }

        $aviso = $config->last_test_ok === false ? ' O último teste falhou: '.e((string) $config->last_error) : ' Faça o teste para ativar.';

        return new HtmlString('<b>Cadastrado, mas desativado.</b> Os e-mails continuam saindo pela caixa da Oravel.'.$aviso);
    }

    /** Salva os dados (sem ativar). */
    public function salvar(): void
    {
        $this->gravar();

        Notification::make()->title('Dados salvos')->body('Faça o teste de conexão para ativar o envio por esta caixa.')->success()->send();
    }

    /** Testa o login no servidor (sem enviar e-mail) e, se der certo, ativa. */
    public function testarEAtivar(): void
    {
        $config = $this->gravar();

        try {
            $transporte = TenantAwareTransport::smtpDa($config);
            $transporte->start();
            $transporte->stop();
        } catch (\Throwable $e) {
            $config->update(['enabled' => false, 'last_test_at' => now(), 'last_test_ok' => false, 'last_error' => mb_substr($e->getMessage(), 0, 300)]);

            Notification::make()->title('Não conseguimos entrar nessa caixa')->body('O servidor recusou os dados. Confira usuário, senha, porta e segurança. Nada foi ativado.')->danger()->persistent()->send();

            return;
        }

        $config->update(['enabled' => true, 'last_test_at' => now(), 'last_test_ok' => true, 'last_error' => null]);

        Notification::make()->title('Pronto! E-mail da empresa ativado')->body('A partir de agora os e-mails saem por '.$config->from_address.'.')->success()->send();
    }

    public function desativar(): void
    {
        $this->config()?->update(['enabled' => false]);

        Notification::make()->title('Desativado')->body('Os e-mails voltam a sair pela caixa da Oravel.')->success()->send();
    }

    private function gravar(): TenantMailSetting
    {
        $tenantId = Tenancy::current()?->id;
        abort_unless($tenantId, 403);

        $dados = $this->form->getState();
        $atual = $this->config();

        $campos = [
            'host' => trim($dados['host']), 'port' => (int) $dados['port'], 'security' => $dados['security'],
            'username' => trim($dados['username']), 'from_address' => trim($dados['from_address']), 'from_name' => $dados['from_name'] ?? null,
        ];

        if (filled($dados['password'] ?? null)) {
            $campos['password'] = $dados['password'];
        }

        // Mudou algum dado da conexão: precisa testar de novo antes de continuar ativo.
        if ($atual && collect(['host', 'port', 'security', 'username'])->contains(fn ($c) => (string) $atual->{$c} !== (string) $campos[$c]) || isset($campos['password'])) {
            $campos['enabled'] = false;
        }

        if ($atual) {
            $atual->update($campos);

            return $atual->refresh();
        }

        return TenantMailSetting::withoutGlobalScopes()->create($campos + ['tenant_id' => $tenantId, 'enabled' => false]);
    }
}
