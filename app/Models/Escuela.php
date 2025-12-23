<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Escuela extends Model
{
    use HasFactory;

    protected $table = 'escuelas';

    protected $fillable = [
        'nombre',
        'slug',
        'cct',
        'rfc',
        'razon_social',
        'email',
        'telefono',
        'codigo_postal',
        'regimen_fiscal',
        'stripe_account_id',
        'activo',
        'onboarding_completado',
        'onboarding_data',
        'onboarding_completado_at',
        'es_registro_express',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'onboarding_completado' => 'boolean',
        'es_registro_express' => 'boolean',
        'onboarding_data' => 'array',
        'onboarding_completado_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relaciones
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
