<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoElemento extends Model
{
    public $timestamps = false;

    protected $table = 'tipos_elemento';

    protected $fillable = [
        'codigo',
        'nombre',
        'prefijo_archivo',
        'requiere_tension',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'requiere_tension' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function elementos(): HasMany
    {
        return $this->hasMany(Elemento::class);
    }
}
