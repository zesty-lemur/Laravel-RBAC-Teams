<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\Contracts\Role as RoleContract;

class Role extends SpatieRole implements RoleContract
{
    /**
    * Get the team that the role belongs to.
    *
    * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
    */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
