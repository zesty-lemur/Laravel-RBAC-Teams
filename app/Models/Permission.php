<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Contracts\Permission as PermissionContract;

class Permission extends SpatiePermission implements PermissionContract
{
    /**
    * Get the team that the permission belongs to.
    *
    * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
    */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
