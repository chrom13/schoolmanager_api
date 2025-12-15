<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Escuela;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Registrar nueva escuela con usuario director
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Crear escuela
            $escuela = Escuela::create([
                'nombre' => $request->nombre_escuela,
                'slug' => $request->slug,
                'cct' => $request->cct,
                'rfc' => $request->rfc,
                'email' => $request->email_escuela,
                'activo' => true,
            ]);

            // Crear usuario director
            $usuario = Usuario::create([
                'escuela_id' => $escuela->id,
                'nombre' => $request->nombre,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'rol' => 'director',
                'activo' => true,
            ]);

            // Generar token
            $token = $usuario->createToken('auth-token')->plainTextToken;

            DB::commit();

            return response()->json([
                'message' => 'Escuela y usuario creados exitosamente',
                'data' => [
                    'escuela' => $escuela,
                    'usuario' => $usuario,
                    'token' => $token,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error al crear la escuela',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login de usuario
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Buscar usuario por email (sin scope de tenant)
        $usuario = Usuario::withoutGlobalScope('escuela')
            ->where('email', $request->email)
            ->first();

        // Verificar credenciales
        if (!$usuario || !Hash::check($request->password, $usuario->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        // Verificar que la escuela esté activa
        if (!$usuario->escuela->activo) {
            return response()->json([
                'message' => 'La escuela está inactiva'
            ], 403);
        }

        // Verificar que el usuario esté activo
        if (!$usuario->activo) {
            return response()->json([
                'message' => 'Usuario inactivo'
            ], 403);
        }

        // Generar token
        $token = $usuario->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'data' => [
                'usuario' => $usuario->load('escuela'),
                'token' => $token,
            ]
        ]);
    }

    /**
     * Logout (revocar token actual)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }

    /**
     * Obtener usuario autenticado
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->load('escuela')
        ]);
    }
}
