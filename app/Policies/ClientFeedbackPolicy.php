<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissão feedback_cliente + mesmo tenant).
 */
class ClientFeedbackPolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
