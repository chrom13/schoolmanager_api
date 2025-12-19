<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Padre extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'escuela_id',
        'nombre_completo',
        'email',
        'telefono',
        'rfc',
        'regimen_fiscal',
        'uso_cfdi',
        'codigo_postal',
        'stripe_customer_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relación many-to-many con Alumnos
     */
    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'alumno_padre')
            ->withPivot(['parentesco', 'responsable_pagos', 'contacto_emergencia'])
            ->withTimestamps();
    }
}
