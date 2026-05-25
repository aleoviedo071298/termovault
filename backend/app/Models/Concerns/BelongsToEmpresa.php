<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToEmpresa
{
    public function scopeForEmpresa(Builder $query, int|string|null $empresaId): Builder
    {
        if ($empresaId === null || $empresaId === '') {
            return $query;
        }

        return $query->where($this->getTable().'.empresa_id', (int) $empresaId);
    }
}
