<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialCambio extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'historial_cambios';

    protected $fillable = [
        'tabla',
        'registro_id',
        'usuario_id',
        'accion',
        'datos_antes',
        'datos_despues',
    ];

    protected function casts(): array
    {
        return [
            'datos_antes' => 'array',
            'datos_despues' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
