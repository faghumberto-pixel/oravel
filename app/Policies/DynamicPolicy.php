<?php

namespace App\Policies;

/**
 * O Porteiro Universal do Oravel.
 * Ele herda toda a lógica de permissões e isolamento de tenant 
 * do AbstractPolicy, protegendo automaticamente qualquer módulo.
 */
class DynamicPolicy extends AbstractPolicy
{
    // A classe é intencionalmente vazia.
    // Toda a inteligência reside no AbstractPolicy que já configuramos.
}
