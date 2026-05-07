<?php

namespace App\Observers;

use App\Models\MaterialCategory;
use Illuminate\Support\Facades\Auth;

class MaterialCategoryObserver
{
    /**
     * Handle the MaterialCategory "creating" event.
     * Este evento é disparado antes de o registro ser inserido no banco.
     */
    public function creating(MaterialCategory $materialCategory): void
    {
        // Garante que o tenant_id seja preenchido se estiver nulo
        if (empty($materialCategory->tenant_id) && Auth::check()) {
            $materialCategory->tenant_id = Auth::user()->tenant_id;
        }
    }
}