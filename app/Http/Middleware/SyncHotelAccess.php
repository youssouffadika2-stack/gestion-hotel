<?php

namespace App\Http\Middleware;

use App\Services\VitrineAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** À chaque requête d'un utilisateur connecté (pages et actions Livewire), l'accès de son hôtel est recalculé. */
class SyncHotelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            try {
                app(VitrineAccess::class)->syncFor($user);
            } catch (\Throwable $e) {
                report($e); // base de l'administration injoignable : on ne bloque pas l'utilisateur
            }
        }

        return $next($request);
    }
}
