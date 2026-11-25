<?php

namespace App\Support\Tour;

use App\Domain\Intelligence\Trial\Services\TrialCiclo;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTour;
use App\Support\Pacotes;
use App\Support\Tenancy;
use Filament\Resources\Resource;

/**
 * Passo a passo em pop-up dentro de cada tela (config/tour.php). Descobre qual tour vale para o usuário, quais telas ele pode abrir,
 * qual passo pertence à tela atual e quais telas ele já viu.
 */
class Tour
{
    private function __construct(private User $user, private string $tour, private Tenant $tenant) {}

    /** O tour que vale para o usuário (pelo produto da empresa), ou null. */
    public static function para(?User $user): ?self
    {
        if (! $user) {
            return null;
        }
        $empresa = $user->isSuperAdmin() ? Tenancy::current() : $user->tenant;
        if (! $empresa) {
            return null;
        }
        $produtos = Pacotes::produtosDoTenant($empresa);

        foreach ((array) config('tour.tours') as $chave => $def) {
            if (in_array($def['produto'] ?? null, $produtos, true)) {
                $tour = new self($user, $chave, $empresa);

                return $tour->passos() === [] ? null : $tour;
            }
        }

        return null;
    }

    public function titulo(): string
    {
        return (string) config("tour.tours.{$this->tour}.titulo");
    }

    /** Passos das telas a que o usuário tem acesso (plano e permissão), na ordem do config, já com a URL. @return list<array<string, mixed>> */
    public function passos(): array
    {
        $lista = [];
        foreach ((array) config("tour.tours.{$this->tour}.passos") as $passo) {
            $classe = $passo['tela'] ?? null;
            if (! $classe || ! class_exists($classe) || ! $this->podeAbrir($classe)) {
                continue;
            }
            $lista[] = $passo + ['url' => $classe::getUrl()];
        }

        return $lista;
    }

    /** O passo da tela que está aberta (comparando o caminho da URL), ou null se a tela não faz parte do tour. */
    public function passoDaTela(string $caminho): ?array
    {
        $caminho = trim($caminho, '/');
        foreach ($this->passos() as $p) {
            if (trim((string) parse_url($p['url'], PHP_URL_PATH), '/') === $caminho) {
                return $p;
            }
        }

        return null;
    }

    public function indiceDe(string $chave): int
    {
        foreach ($this->passos() as $i => $p) {
            if ($p['chave'] === $chave) {
                return $i;
            }
        }

        return 0;
    }

    /** Frase extra do passo, só para quem está no teste grátis. */
    public function notaTrial(array $passo): ?string
    {
        return ! empty($passo['nota_trial']) && TrialCiclo::doTenant($this->tenant) ? $passo['nota_trial'] : null;
    }

    // ---- o que o usuário já viu ----

    private function registro(): UserTour
    {
        return UserTour::firstOrCreate(['user_id' => $this->user->id, 'tour' => $this->tour]);
    }

    public function jaViu(string $chave): bool
    {
        return (bool) UserTour::where('user_id', $this->user->id)->where('tour', $this->tour)->first()?->jaViu($chave);
    }

    public function dispensado(): bool
    {
        return (bool) UserTour::where('user_id', $this->user->id)->where('tour', $this->tour)->value('dispensado_em');
    }

    /** O pop-up da tela abre sozinho só na primeira visita, e só se a pessoa não pediu para parar. Super admin nunca abre sozinho. */
    public function abreSozinho(string $chave): bool
    {
        return ! $this->user->isSuperAdmin() && ! $this->dispensado() && ! $this->jaViu($chave);
    }

    public function marcarVisto(string $chave): void
    {
        $r = $this->registro();
        $r->passos_vistos = array_values(array_unique([...(array) $r->passos_vistos, $chave]));
        $r->save();
    }

    public function registrarReabertura(): void
    {
        $this->registro()->increment('reaberturas');
    }

    public function dispensar(): void
    {
        $this->registro()->forceFill(['dispensado_em' => now()])->save();
    }

    private function podeAbrir(string $classe): bool
    {
        try {
            return is_subclass_of($classe, Resource::class) ? (bool) $classe::canViewAny() : (bool) $classe::canAccess();
        } catch (\Throwable) {
            return false;
        }
    }
}
