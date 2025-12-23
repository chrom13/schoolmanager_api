<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\RegisterExpressRequest;
use App\Models\Escuela;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;

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
            $usuario = User::create([
                'escuela_id' => $escuela->id,
                'name' => $request->nombre,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'rol' => 'director',
            ]);

            // Generar token
            $token = $usuario->createToken('auth-token')->plainTextToken;

            DB::commit();

            // Enviar email de verificación
            $usuario->sendEmailVerificationNotification();

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
            $usuario = User::create([
                'escuela_id' => $escuela->id,
                'name' => $nombre,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'rol' => 'director',
            ]);

            // Generar token
            $token = $usuario->createToken('auth-token')->plainTextToken;

            DB::commit();

            // Enviar email de verificación
            $usuario->sendEmailVerificationNotification();

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
        $usuario = User::withoutGlobalScope('escuela')
            ->where('email', $request->email)
            ->first();

        // Verificar credenciales
        if (!$usuario || !Hash::check($request->password, $usuario->password)) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        // Las validaciones de soft delete se manejan automáticamente con el trait

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

    /**
     * Enviar link de recuperación de contraseña
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Buscar usuario por email
        $usuario = User::withoutGlobalScope('escuela')
            ->where('email', $request->email)
            ->first();

        if (!$usuario) {
            // Por seguridad, no revelamos si el email existe o no
            return response()->json([
                'message' => 'Si el correo existe, recibirás un enlace para restablecer tu contraseña'
            ]);
        }

        // Generar token
        $token = Str::random(60);

        // Guardar token en la tabla password_reset_tokens
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'email' => $request->email,
                'token' => Hash::make($token),
                'created_at' => now()
            ]
        );

        // TODO: Enviar email con el token
        // Por ahora, en desarrollo, retornamos el token (QUITAR EN PRODUCCIÓN)
        if (config('app.env') === 'local') {
            return response()->json([
                'message' => 'Token generado (solo en desarrollo)',
                'token' => $token,
                'reset_url' => config('app.frontend_url') . '/reset-password?token=' . $token . '&email=' . $request->email
            ]);
        }

        return response()->json([
            'message' => 'Si el correo existe, recibirás un enlace para restablecer tu contraseña'
        ]);
    }

    /**
     * Restablecer contraseña con token
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
            'token' => 'required',
        ]);

        // Buscar el token
        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'message' => 'Token inválido o expirado'
            ], 400);
        }

        // Verificar que el token no haya expirado (60 minutos)
        if (now()->diffInMinutes($passwordReset->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'El token ha expirado'
            ], 400);
        }

        // Verificar el token
        if (!Hash::check($request->token, $passwordReset->token)) {
            return response()->json([
                'message' => 'Token inválido'
            ], 400);
        }

        // Buscar usuario
        $usuario = User::withoutGlobalScope('escuela')
            ->where('email', $request->email)
            ->first();

        if (!$usuario) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // Actualizar contraseña
        $usuario->password = Hash::make($request->password);
        $usuario->save();

        // Eliminar el token usado
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Revocar todos los tokens del usuario por seguridad
        $usuario->tokens()->delete();

        return response()->json([
            'message' => 'Contraseña restablecida exitosamente'
        ]);
    }

    /**
     * Verificar email del usuario
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|integer',
            'hash' => 'required|string',
        ]);

        $usuario = User::withoutGlobalScope('escuela')->findOrFail($request->id);

        if (!hash_equals((string) $request->hash, sha1($usuario->getEmailForVerification()))) {
            return response()->json([
                'message' => 'URL de verificación inválida'
            ], 400);
        }

        if ($usuario->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'El correo electrónico ya ha sido verificado'
            ]);
        }

        $usuario->markEmailAsVerified();

        return response()->json([
            'message' => 'Correo electrónico verificado exitosamente'
        ]);
    }

    /**
     * Reenviar email de verificación
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'El correo electrónico ya ha sido verificado'
            ]);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Email de verificación enviado'
        ]);
    }
}
