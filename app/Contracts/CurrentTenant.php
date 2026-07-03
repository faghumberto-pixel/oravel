<?php

namespace App\Contracts;

use App\Models\Tenant;

interface CurrentTenant
{
    public function get(): ?Tenant;
    public function set(Tenant $tenant): void;
}
