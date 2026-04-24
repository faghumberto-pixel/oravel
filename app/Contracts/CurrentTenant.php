// app/Contracts/CurrentTenant.php
<?php

namespace App\Contracts;

use App\Models\Tenant; // Importe o modelo Tenant

interface CurrentTenant
{
    public function get(): ?Tenant;
    public function set(Tenant $tenant): void; // Adicionei o método set aqui também
}