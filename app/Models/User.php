<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Crypt;
use App\Traits\HasHashid;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles, HasHashid, SoftDeletes;

    /**
     * The prefix for the hashid.
     *
     * @var string
     */
    const HASHID_PREFIX = 'usr_';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Encrypt / decrypt the first_name attribute.
     *
     * @return Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function firstName(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Crypt::encryptString($value),
            get: fn (string $value) => Crypt::decryptString($value),
        );
    }

    /**
     * Encrypt / decrypt the last_name attribute.
     *
     * @return Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function lastName(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Crypt::encryptString($value),
            get: fn (string $value) => Crypt::decryptString($value),
        );
    }

    /**
     * Encrypt / decrypt the email attribute.
     *
     * @return Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => Crypt::encryptString($value),
            get: fn (string $value) => Crypt::decryptString($value),
        );
    }

     /**
     * Get the teams that the user belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function teams()
    {
        return $this
            ->belongsToMany(Team::class)
            ->using(TeamUser::class)
            ->withPivot(['role_id', 'role_name']);
    }

    /**
     * Get the team that the user is actively viewing.
     *
     * @return \App\Models\Team|null
     */
    public function activeTeam()
    {
        return $this->belongsTo(Team::class, 'active_team_id');
    }

    /**
     * Set the active team for the user.
     *
     * @param  \App\Models\Team  $team
     * @return void
     */
    public function setActiveTeam(Team $team)
    {
        setPermissionsTeamId($team->id);
        $this->active_team_id = $team->id;
        $this->save();
    }

    /**
     * Get the role for the user on the given team.
     *
     * @param  \App\Models\Team  $team
     * @return \App\Models\Role
     */
    public function getRoleForTeam(Team $team)
    {
        return $this->teams()->where('team_id', $team->id)->first()->pivot->role;
    }

    public function assignRoleToTeam(Team $team, Role $role)
    {
        //TODO: verify that that user is authorized to assign the role
        $this->teams()->syncWithoutDetaching([$team->id => ['role_id' => $role->id]]);
    }
}
