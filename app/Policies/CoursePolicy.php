<?php

namespace App\Policies;

/**
 * Policy dedicada apenas para o Laravel identificar o modelo em checagens sem
 * $record (viewAny/create) -- ver AbstractPolicy e o comentario em ClientPolicy.
 */
class CoursePolicy extends AbstractPolicy {}
