<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use App\Models\Team;
use App\Models\User;
use App\Models\Role;

class TeamUser extends Pivot
{
    protected $fillable = [
        'team_id',
        'user_id',
        'role_id',
        'role_name'
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($teamUser) {
            $team = Team::findOrFailByHashid($teamUser->team_id);
            $user = User::findOrFailByHashid($teamUser->user_id);
            $role = Role::find($teamUser->role_id);
            $teamUser->role_name = $role->name;
            $teamUser->created_at = now();
        });

        static::updating(function ($teamUser) {
            $team = Team::findOrFailByHashid($teamUser->team_id);
            $user = User::findOrFailByHashid($teamUser->user_id);
            $role = Role::find($teamUser->role_id);
            $teamUser->role_name = $role->name;
            $teamUser->updated_at = now();
        });
    }
}
