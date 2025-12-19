<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\RegisterExpressRequest;
use App\Models\Escuela;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
     * Registro express - Solo datos mínimos
     */
    public function registerExpress(RegisterExpressRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Generar slug único desde el nombre de la escuela
            $slug = Str::slug($request->nombre_escuela);
            $originalSlug = $slug;
            $counter = 1;

            // Asegurar que el slug sea único
            while (Escuela::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            // Generar CCT temporal único
            $cctTemp = null;
            do {
                $cctTemp = 'TEMP-' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            } while (Escuela::where('cct', $cctTemp)->exists());

            // Inferir nombre del usuario del email si no se proporciona
            $nombre = $request->nombre ?? explode('@', $request->email)[0];

            // Crear escuela con datos mínimos
            $escuela = Escuela::create([
                'nombre' => $request->nombre_escuela,
                'slug' => $slug,
                'cct' => $cctTemp,
                'email' => $request->email, // Temporalmente usar el email del usuario
                'activo' => true,
                'es_registro_express' => true,
                'onboarding_completado' => false,
                'onboarding_data' => [
                    'paso_actual' => 'bienvenida',
                    'fecha_registro' => now()->toIso8601String(),
                ],
            ]);

            // Crear usuario director
            $usuario = Usuario::create([
                'escuela_id' => $escuela->id,
                'nombre' => $nombre,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'rol' => 'director',
                'activo' => true,
            ]);

            // Generar token
            $token = $usuario->createToken('auth-token')->plainTextToken;

            DB::commit();

            // Log para analytics
            Log::info('Registro express exitoso', [
                'escuela_id' => $escuela->id,
                'usuario_id' => $usuario->id,
                'slug' => $slug,
            ]);

            return response()->json([
                'message' => '¡Cuenta creada exitosamente!',
                'data' => [
                    'escuela' => $escuela,
                    'usuario' => $usuario->load('escuela'),
                    'token' => $token,
                    'onboarding_required' => true,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error en registro express', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al crear la cuenta',
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
