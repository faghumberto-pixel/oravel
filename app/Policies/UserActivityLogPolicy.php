<?php

namespace App\Policies;

/**
 * Policy dedicada apenas para o Laravel conseguir identificar o modelo em
 * checagens sem $record (viewAny/create) -- ver AbstractPolicy. Mesmo
 * achado do ChatRoomPolicy: sem policy nomeada, viewAny caia no
 * DynamicPolicy generico, que nao consegue resolver o modelo pra
 * checagens sem $record e pulava a trava comercial (tabela_user_activity_logs)
 * silenciosamente pra qualquer admin de tenant (auditoria de permissoes,
 * 2026-07-08).
 */
class UserActivityLogPolicy extends AbstractPolicy {}
