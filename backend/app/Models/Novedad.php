<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Novedad extends Model
{
    protected $fillable = [
        'inspeccion_id',
        'criticidad_id',
        'titulo',
        'descripcion',
        'ubicacion_dentro_elemento',
        'temperatura_detectada',
        'accion_recomendada',
        'estado',
        'fecha_resolucion',
        'resuelta_en_inspeccion_id',
    ];

    protected function casts(): array
    {
        return [
            'temperatura_detectada' => 'decimal:2',
            'fecha_resolucion' => 'datetime',
        ];
    }

    public function scopeForEmpresa(Builder $query, int|string|null $empresaId): Builder
    {
        return $query->whereHas('inspeccion', fn (Builder $query) => $query->forEmpresa($empresaId));
    }

    public function inspeccion(): BelongsTo
    {
        return $this->belongsTo(Inspeccion::class);
    }

    public function criticidad(): BelongsTo
    {
        return $this->belongsTo(Criticidad::class);
    }

    public function resueltaEnInspeccion(): BelongsTo
    {
        return $this->belongsTo(Inspeccion::class, 'resuelta_en_inspeccion_id');
    }
}
