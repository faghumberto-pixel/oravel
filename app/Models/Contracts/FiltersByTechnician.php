<?php

namespace App\Models\Contracts;

/**
 * Marca models cuja TenantScope também deve aplicar
 * o filtro adicional por technician_id quando o usuário
 * autenticado possuir a role "Técnico de Manutenção Mecânica".
 *
 * Models que NÃO possuem a coluna technician_id (ex: ChatMessage)
 * não devem implementar esta interface.
 */
interface FiltersByTechnician
{
}
