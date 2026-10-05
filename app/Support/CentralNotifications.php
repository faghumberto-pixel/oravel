<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Avisos da OPERAÇÃO do SaaS (pagamento/implantação/checkout/assinatura/acesso de clientes)
 * pertencem só à Central. Marcados com viewData.scope=central; os antigos, sem a marca,
 * são reconhecidos pelo link para /central/. Usado pelo sino do app e pela Auditoria de
 * Notificações para nunca mostrá-los fora da Central.
 */
class CentralNotifications
{
    public static function exclude(Builder|Relation $query): Builder|Relation
    {
        return $query
            ->whereRaw("coalesce(data::json->'viewData'->>'scope', '') <> 'central'")
            ->whereRaw('data::text not like ?', ['%/central/%'])
            ->whereRaw('data::text not like ?', ['%\\/central\\/%']);
    }
}
