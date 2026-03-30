<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Alumno extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'escuela_id',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'curp',
        'fecha_nacimiento',
        'foto_url',
        'genero',
        'activo',
        'telefono',
        'email',
        'direccion',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'activo'           => 'boolean',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
        'deleted_at'       => 'datetime',
    ];

    protected $appends = ['nombre_completo'];

    /**
     * Auto-genera la matrícula al crear un alumno si no se proporcionó.
     * Formato: {escuela_id 3 dígitos}{año 2 dígitos}{secuencia 4 dígitos}
     * Ejemplo: 001260001
     */
    protected static function booted(): void
    {
        static::creating(function (Alumno $alumno) {
            if (empty($alumno->matricula)) {
                $escuelaId = str_pad($alumno->escuela_id, 3, '0', STR_PAD_LEFT);
                $year      = date('y');
                $seq       = Alumno::withTrashed()->where('escuela_id', $alumno->escuela_id)->count() + 1;
                $alumno->matricula = $escuelaId . $year . str_pad($seq, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    /**
     * Inscripción activa del alumno (la más reciente con estado=activa)
     */
    public function inscripcionActiva(): HasOne
    {
        return $this->hasOne(Inscripcion::class)
            ->where('estado', 'activa')
            ->latest();
    }

    /**
     * Relación con todas las Inscripciones
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class);
    }

    /**
     * Relación many-to-many con Grupos a través de Inscripciones
     */
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'inscripciones')
            ->withPivot(['fecha_inscripcion', 'estado', 'observaciones', 'ciclo_escolar_id'])
            ->withTimestamps();
    }

    /**
     * Relación many-to-many con Padres
     */
    public function padres(): BelongsToMany
    {
        return $this->belongsToMany(Padre::class, 'alumno_padre')
            ->withPivot(['parentesco', 'responsable_pagos', 'contacto_emergencia'])
            ->withTimestamps();
    }

    /**
     * Accessor para nombre completo
     */
    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido_paterno} {$this->apellido_materno}");
    }
}
