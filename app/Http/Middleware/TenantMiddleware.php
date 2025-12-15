<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar que el usuario esté autenticado
        if (!auth()->check()) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // Verificar que el usuario tenga escuela_id
        if (!auth()->user()->escuela_id) {
            return response()->json([
                'message' => 'Usuario no asociado a ninguna escuela.'
            ], 403);
        }

        // Agregar tenant_escuela_id al request para uso posterior
        $request->merge([
            'tenant_escuela_id' => auth()->user()->escuela_id
        ]);

        return $next($request);
    }
}
