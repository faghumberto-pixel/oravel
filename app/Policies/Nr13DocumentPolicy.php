<?php

namespace App\Policies;

/**
 * Policy dedicada apenas para o Laravel conseguir identificar o modelo em
 * checagens sem $record (viewAny/create) -- o Gate remove o argumento de
 * classe nessas checagens, então uma Policy compartilhada como DynamicPolicy
 * não consegue saber de qual modelo se trata. Toda a lógica real continua
 * herdada do AbstractPolicy.
 */
class Nr13DocumentPolicy extends AbstractPolicy {}
