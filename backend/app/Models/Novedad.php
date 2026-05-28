<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Novedad extends Model
{
    public const ESTADO_ABIERTA = 'abierta';
    public const ESTADO_RESUELTA = 'resuelta';

    public static function getEstados(): array
    {
        return [
            self::ESTADO_ABIERTA,
            self::ESTADO_RESUELTA,
        ];
    }

    protected $table = 'novedades';

    protected $fillable = [
        'inspeccion_id',
        'criticidad_id',
        'titulo',
        'descripcion',
        'ubicacion_dentro_elemento',
        'temperatura_detectada',
        'accion_recomendada',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'temperatura_detectada' => 'decimal:2',
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
}
