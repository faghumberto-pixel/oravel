<?php

namespace App\Policies;

use App\Models\LandingPageLead;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LandingPageLeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, LandingPageLead $landingPageLead): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, LandingPageLead $landingPageLead): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, LandingPageLead $landingPageLead): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, LandingPageLead $landingPageLead): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, LandingPageLead $landingPageLead): bool
    {
        return $user->isSuperAdmin();
    }
}
