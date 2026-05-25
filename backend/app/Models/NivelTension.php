<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NivelTension extends Model
{
    public $timestamps = false;

    protected $table = 'niveles_tension';

    protected $fillable = [
        'kv',
        'etiqueta',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'kv' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function elementos(): HasMany
    {
        return $this->hasMany(Elemento::class);
    }
}
