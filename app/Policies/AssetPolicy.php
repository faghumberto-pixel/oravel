<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy extends AbstractPolicy
{
    public function viewAny(User $user, $model = null): bool 
    { 
        return $this->check($user, 'viewAny', Asset::class);
    }

    /**
     * AJUSTE AQUI: Remova a restrição 'Asset $asset' se a AbstractPolicy 
     * não a exigir, ou garanta que a AbstractPolicy também exija 'Asset $asset'.
     * O padrão mais seguro é usar o mesmo nome da classe pai.
     */
    public function view(User $user, $model = null): bool 
    { 
        // Se $model for uma instância de Asset, tratamos normalmente
        return $this->check($user, 'view', $model);
    }

    public function create(User $user, $model = null): bool 
    { 
        return $this->check($user, 'create', Asset::class);
    }

    public function update(User $user, $model = null): bool 
    { 
        return $this->check($user, 'update', $model);
    }

    public function delete(User $user, $model = null): bool 
    { 
        return $this->check($user, 'delete', $model);
    }
}