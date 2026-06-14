<?php

namespace App\Models\Traits;

// Mantido apenas por compatibilidade de namespace.
// A logica real de isolamento vive em App\Models\Concerns\BelongsToTenant.
trait BelongsToTenant
{
    use \App\Models\Concerns\BelongsToTenant;
}
