<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\Role;
use App\Traits\HasHashid;

class Team extends Model
{
    use HasFactory, HasHashid, SoftDeletes;

    /**
     * The prefix for the hashid.
     *
     * @var string
     */
    const HASHID_PREFIX = 'team_';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Flag to ignore the boot method.
     */
    protected static $ignoreBoot = false;

    /**
     * Set the ignoreBoot flag.
     *
     * @param bool $ignore
     */
    public static function ignoreBoot($ignore = true)
    {
        self::$ignoreBoot = $ignore;
    }

    /**
     * The "booted" method of the model.
     * Used to add the Super Admin user to the team.
     */
    public static function boot()
    {
        parent::boot();

        self::created(function ($model) {

            if (!self::$ignoreBoot) {
                $session_team_id = getPermissionsTeamId();
                setPermissionsTeamId($model);
                User::find(1)->assignRole('Super Admin');
                setPermissionsTeamId($session_team_id);
            }
        });
    }

    /**
     * Get the users that belong to the team.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this
            ->belongsToMany(User::class)
            ->using(TeamUser::class)
            ->withPivot(['role_id', 'role_name']);
    }
}
