<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use App\Models\Branch;

trait BelongsToBranchTrait
{
    public static function bootBelongsToBranchTrait()
    {
        // Aplicar Scope Global automáticamente
        static::addGlobalScope('branch_scope', function (Builder $builder) {
            $branchId = Context::get('branch_id');

            // Solo filtramos si hay un contexto y NO somos super-admin (opcional)
            // O si queremos permitir ver todo, podemos crear un método 'withoutBranchScope()'
            if ($branchId) {
                $builder->where('branch_id', $branchId);
            }
        });

        // Asignar automáticamente el branch_id al crear registros
        static::creating(function (Model $model) {
            $branchId = Context::get('branch_id');
            if ($branchId && !$model->branch_id) {
                $model->branch_id = $branchId;
            }
        });
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
