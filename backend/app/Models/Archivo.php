<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Archivo extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'inspeccion_id',
        'tipo',
        'nombre_original',
        's3_bucket',
        's3_key',
        'tamano_bytes',
        'mime_type',
        'subido_por',
    ];

    public function scopeForEmpresa(Builder $query, int|string|null $empresaId): Builder
    {
        return $query->whereHas('inspeccion', fn (Builder $query) => $query->forEmpresa($empresaId));
    }

    public function inspeccion(): BelongsTo
    {
        return $this->belongsTo(Inspeccion::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'subido_por');
    }
}
