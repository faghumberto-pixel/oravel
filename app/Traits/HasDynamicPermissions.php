<?php

namespace App\Traits;

trait HasDynamicPermissions
{
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        
        return $user->isAdmin() || $user->checkPermission('viewAny', static::getModel());
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        return $user->isAdmin() || $user->checkPermission('view', static::getModel());
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        return $user->isAdmin() || $user->checkPermission('create', static::getModel());
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        return $user->isAdmin() || $user->checkPermission('update', static::getModel());
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        return $user->isAdmin() || $user->checkPermission('delete', static::getModel());
    }
}