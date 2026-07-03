<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        // Ignora completamente qualquer requisição do Livewire
        if (
            $request->hasHeader('X-Livewire') ||
            $request->hasHeader('X-Livewire-Method') ||
            $request->hasHeader('X-Livewire-Component-Name') ||
            str_starts_with($request->path(), 'livewire/')
        ) {
            return $next($request);
        }

        // Atualização condicional (apenas a cada minuto)
        if (Auth::check()) {
            $now = Carbon::now('America/Sao_Paulo');
            DB::table('users')
                ->where('id', Auth::id())
                ->where(function ($query) use ($now) {
                    $query->whereNull('last_seen')
                          ->orWhere('last_seen', '<', $now->copy()->subMinute());
                })
                ->update(['last_seen' => $now]);
        }

        return $next($request);
    }
}