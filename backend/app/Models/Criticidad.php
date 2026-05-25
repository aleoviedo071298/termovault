<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Criticidad extends Model
{
    public $timestamps = false;

    protected $table = 'criticidades';

    protected $fillable = [
        'nivel',
        'nombre',
        'color',
    ];

    public function elementos(): HasMany
    {
        return $this->hasMany(Elemento::class);
    }

    public function novedades(): HasMany
    {
        return $this->hasMany(Novedad::class);
    }
}
