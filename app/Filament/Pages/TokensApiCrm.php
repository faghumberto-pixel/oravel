<?php

namespace App\Filament\Pages;

use App\Models\CrmLead;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tokens pessoais da API do CRM (extensão do WhatsApp e integrações). Só são
 * emitidos aqui, dentro do painel (com a sessão/2FA do usuário): a API não tem
 * login por senha. O texto do token aparece uma única vez.
 */
class TokensApiCrm extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Integração / API';

    protected static ?string $title = 'Tokens da API do CRM';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static ?int $navigationSort = 31;

    protected static string $view = 'filament.pages.tokens-api-crm';

    public string $nome = '';

    public int $validadeDias = 90;

    public bool $podeLer = true;

    public bool $podeEscrever = true;

    public ?string $tokenNovo = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', CrmLead::class);
    }

    public static function validades(): array
    {
        return [30 => '30 dias', 90 => '90 dias', 180 => '180 dias', 365 => '1 ano'];
    }

    public function criar(): void
    {
        $this->validate([
            'nome' => ['required', 'string', 'max:80'],
            'validadeDias' => ['required', 'in:'.implode(',', array_keys(self::validades()))],
        ]);

        $habilidades = array_values(array_filter([$this->podeLer ? 'crm:read' : null, $this->podeEscrever ? 'crm:write' : null]));

        if (! $habilidades) {
            Notification::make()->title('Marque pelo menos uma permissão para o token.')->warning()->send();

            return;
        }

        // Escrever sem ler não faz sentido para a extensão: write implica read.
        if (in_array('crm:write', $habilidades, true) && ! in_array('crm:read', $habilidades, true)) {
            array_unshift($habilidades, 'crm:read');
        }

        $this->tokenNovo = auth()->user()->createToken($this->nome, $habilidades, now()->addDays($this->validadeDias))->plainTextToken;
        $this->reset('nome');
    }

    public function fecharToken(): void
    {
        $this->tokenNovo = null;
    }

    public function revogar(int $tokenId): void
    {
        $token = $this->tokensVisiveis()->firstWhere('id', $tokenId);

        if (! $token) {
            Notification::make()->title('Token não encontrado.')->danger()->send();

            return;
        }

        $token->delete();
        Notification::make()->title('Token revogado.')->success()->send();
    }

    /** Próprios tokens; o admin do tenant vê (e revoga) os de todos os usuários da empresa. */
    public function tokensVisiveis()
    {
        $user = auth()->user();

        $query = PersonalAccessToken::query()->where('tokenable_type', $user->getMorphClass())->latest();

        $user->isAdmin()
            ? $query->whereIn('tokenable_id', User::query()->where('tenant_id', $user->tenant_id)->select('id'))
            : $query->where('tokenable_id', $user->id);

        return $query->get();
    }

    public function nomeDoDono(string $userId): string
    {
        return User::withoutGlobalScopes()->whereKey($userId)->value('name') ?? '—';
    }
}
