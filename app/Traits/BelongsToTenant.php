<?php

namespace App\Traits;

use App\Models\Escuela;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the trait.
     */
    protected static function bootBelongsToTenant(): void
    {
        // Agregar escuela_id automáticamente al crear
        static::creating(function ($model) {
            if (!$model->escuela_id && auth()->check()) {
                $model->escuela_id = auth()->user()->escuela_id;
            }
        });

        // Global scope: filtrar automáticamente por escuela_id
        static::addGlobalScope('escuela', function (Builder $builder) {
            if (auth()->check() && auth()->user()->escuela_id) {
                $builder->where('escuela_id', auth()->user()->escuela_id);
            }
        });
    }

    /**
     * Relación con Escuela
     */
    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }
}
