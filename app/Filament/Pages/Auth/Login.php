<?php

namespace App\Filament\Pages\Auth;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Casca visual (glassmorphism sobre foto industrial, ver
 * resources/views/filament/pages/auth/login.blade.php) por cima da
 * autenticacao real do Filament -- authenticate()/rate limit/2FA
 * continuam herdados sem alteracao (so authenticate() e sobrescrito, ver
 * comentario no metodo), so trocamos $view + a aparencia dos campos do
 * form. O formulario de Cadastro dessa mesma tela tambem vive aqui
 * (register()) -- cria conta de verdade, mas pendente de aprovacao do
 * admin do tenant (is_approved=false ate alguem aprovar em UserResource).
 */
class Login extends BaseLogin
{
    protected static string $view = 'filament-panels::pages.auth.login';

    /**
     * SimplePage (pai da classe base) usa
     * 'filament-panels::components.layout.simple', que forca um card
     * branco centralizado unico -- vamos direto no layout.base (so
     * <html>/<head>/assets do Filament) pra poder desenhar as duas
     * colunas por conta propria na view.
     */
    protected static string $layout = 'filament-panels::components.layout.base';

    public static function getRouteMiddleware(): array
    {
        return ['guest'];
    }

    public string $registerName = '';

    public string $registerEmail = '';

    public string $registerTenantSlug = '';

    public string $registerPassword = '';

    public string $registerPasswordConfirmation = '';

    public bool $registerTerms = false;

    protected function getLayoutData(): array
    {
        return [];
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('E-mail')
            ->hiddenLabel()
            ->placeholder('E-mail')
            ->prefixIcon('heroicon-o-envelope')
            ->email()
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1, 'class' => 'oravel-auth-input']);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Senha')
            ->hiddenLabel()
            ->placeholder('Senha')
            ->prefixIcon('heroicon-o-lock-closed')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required()
            ->extraInputAttributes(['tabindex' => 2, 'class' => 'oravel-auth-input']);
    }

    /**
     * "Lembrar de mim" sai do form gerenciado pelo Filament -- vira um
     * checkbox simples com wire:model="data.remember" na view, pra poder
     * ficar na mesma linha dos links "Esqueceu a senha?"/"Criar Conta"
     * (layout do spec). authenticate() na classe pai ja le
     * $data['remember'] ?? false, entao continua funcionando sem mudar
     * aquele metodo.
     *
     * @return array<int|string, string|Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->label('LOGIN')
            ->submit('authenticate')
            ->extraAttributes(['class' => 'oravel-auth-submit']);
    }

    /**
     * Copia o corpo curto de BaseLogin::authenticate() -- so acrescenta a
     * checagem de is_approved ANTES da checagem generica de
     * canAccessPanel(), pra mostrar uma mensagem especifica ("aguardando
     * aprovacao") em vez da generica de credenciais invalidas que o pai
     * usa pra qualquer motivo de recusa. canAccessPanel() continua sendo
     * a trava de verdade (User.php) -- essa sobrescrita e' so pela
     * mensagem certa nessa tela.
     */
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        if ($user instanceof User && ! $user->is_approved) {
            Filament::auth()->logout();

            throw ValidationException::withMessages([
                'data.email' => 'Sua conta ainda está aguardando aprovação do administrador da sua empresa.',
            ]);
        }

        if (($user instanceof FilamentUser) && (! $user->canAccessPanel(Filament::getCurrentPanel()))) {
            Filament::auth()->logout();
            $this->throwFailureValidationException();
        }

        session()->regenerate();

        // Técnico comum (não admin, sem departamentos supervisionados) vai direto para "Minhas Ordens de Serviço"
        // Precisa ser $this->redirect() (Livewire), nao redirect()->route() puro: dentro de uma
        // action Livewire, redirect() ja registra o effect de navegacao como side-effect da chamada;
        // o valor de retorno da action nunca vira navegacao, so alimenta o effect "returns" do
        // $wire.call() -- daí o retorno aqui tem que ser null (compativel com ?LoginResponse).
        // [DESABILITADO 2026-09-02: módulo Tarefas travado com modal offline]
        // if ($user instanceof User && ! $user->isAdmin() && empty($user->supervisedDepartmentIds())) {
        //     $this->redirect(route('filament.admin.pages.technician-daily-tasks'));
        //
        //     return null;
        // }

        return app(LoginResponse::class);
    }

    /**
     * Cria a conta de verdade, mas pendente (is_approved=false) --
     * precisa do admin do tenant aprovar em UserResource antes de
     * conseguir logar (authenticate() acima bloqueia com mensagem clara
     * enquanto isso). O tenant e' resolvido pelo "Código da Empresa"
     * (mesmo slug usado em /admin/login hoje) porque essa tela e'
     * compartilhada por todos os tenants -- sem esse campo nao da pra
     * saber a qual empresa o cadastro pertence.
     */
    public function register(): void
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $this->validate([
            'registerName' => ['required', 'string', 'max:255'],
            'registerEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'registerTenantSlug' => ['required', 'string', 'exists:tenants,slug'],
            'registerPassword' => ['required', 'string', 'min:8', 'same:registerPasswordConfirmation'],
            'registerTerms' => ['accepted'],
        ], [], [
            'registerName' => 'nome completo',
            'registerEmail' => 'e-mail',
            'registerTenantSlug' => 'código da empresa',
            'registerPassword' => 'senha',
            'registerTerms' => 'termos de uso',
        ]);

        $tenant = Tenant::where('slug', Str::slug($this->registerTenantSlug))->first();

        $user = User::create([
            'name' => $this->registerName,
            'email' => $this->registerEmail,
            'password' => Hash::make($this->registerPassword),
            'tenant_id' => $tenant->id,
            'is_approved' => false,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->notifyTenantAdmins($tenant, $user);

        $this->reset([
            'registerName',
            'registerEmail',
            'registerTenantSlug',
            'registerPassword',
            'registerPasswordConfirmation',
            'registerTerms',
        ]);

        Notification::make()
            ->title('Cadastro enviado!')
            ->body('Aguarde a aprovação do administrador da sua empresa pra poder entrar.')
            ->success()
            ->send();

        $this->dispatch('oravel-registered');
    }

    /**
     * Mesmo cuidado documentado em EquipmentDamageObserver::notifyRole():
     * nao usar User::role('admin') direto, o Spatie resolve o papel por
     * nome globalmente (ignora tenant_id) -- resolve o Role scoped por
     * tenant primeiro.
     */
    private function notifyTenantAdmins(Tenant $tenant, User $newUser): void
    {
        $adminRole = Role::where('name', 'admin')
            ->where('guard_name', 'web')
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $adminRole) {
            return;
        }

        $admins = User::role($adminRole)->where('tenant_id', $tenant->id)->get();

        if ($admins->isEmpty()) {
            return;
        }

        // ->sendToDatabase() enfileira por baixo dos panos
        // (Filament\Notifications\DatabaseNotification implementa
        // ShouldQueue) -- em qualquer ambiente sem QUEUE_CONNECTION=sync
        // e sem worker de fila rodando, a notificacao simplesmente nunca
        // chega, sem erro nenhum (achado real: reproduzido em DEV
        // forcando QUEUE_CONNECTION=database sem worker, o job ficava
        // parado na tabela jobs pra sempre). sendNow() entrega na hora,
        // sem depender de worker.
        NotificationFacade::sendNow(
            $admins,
            Notification::make()
                ->title('Novo cadastro aguardando aprovação')
                ->body($newUser->name.' ('.$newUser->email.') pediu acesso à sua empresa.')
                ->warning()
                ->toDatabase(),
        );
    }
}
