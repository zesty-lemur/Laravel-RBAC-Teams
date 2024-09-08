# Laravel Template with Teams-based RBAC

The guide below details the steps taken to create this template, which is based on a Project Management application with a focus on Teams and Role-Based Access Control (RBAC).

This repository is available under a GNU General Public License v3.0 license.

*Note: The guide contained in this readme was ported from notes kept in Obsidian; there may be some residual formatting errors.*

<hr>

## 1. Initial Installation and Setup

**Stack:** Laravel Breeze with Livewire (Class API) and Alpine

Configure `.env` file with [mailtrap](mailtrap.io) credentials, and remove AWS variables:

```shell
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=****
MAIL_USERNAME=**************
MAIL_PASSWORD=**************
```

Import the icons and images folders to the `public` directory.

Clear the default README contents.

Create a `page-head.blade.php` component in the `resources/views/components` directory:

```php
<head>
    <!-- Meta Tags -->
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <meta content="{{ csrf_token() }}" name="csrf-token">

    <!-- Title -->
    <title>{{ $title ?? config('app.name') }}</title>

    <!-- Favicons -->
    <link href="icons/apple-touch-icon.png" rel="apple-touch-icon" sizes="180x180">
    <link href="icons/favicon-32x32.png" rel="icon" sizes="32x32" type="image/png">
    <link href="icons/favicon-16x16.png" rel="icon" sizes="16x16" type="image/png">
    <link href="icons/site.webmanifest" rel="manifest">
    <link color="#5bbad5" href="icons/safari-pinned-tab.svg" rel="mask-icon">
    <meta content="#da532c" name="msapplication-TileColor">
    <meta content="#ffffff" name="theme-color">

    <!-- Fonts -->
    <link href="https://fonts.bunny.net" rel="preconnect">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
```

Modify the `application-logo.blade.php` component to allow for multiple logo styles:

```php
@props(['style' => ''])

@switch($style)

    @case('bw')
        <img src="{{ asset('images/zl-bw.png') }}" alt="Logo" {{ $attributes->merge(['class' => 'w-24']) }}>
        @break

    @default
        <img src="{{ asset('images/zl.png') }}" alt="Logo" {{ $attributes->merge(['class' => 'w-24']) }}>
        @break

@endswitch
```

Within `welcome.blade.php` replace the `<head>` section with the page-head component `<x-page-head/>`, and create a simple login CTA:

```php
<body class="flex h-screen w-screen flex-col antialiased font-sans">

        <main class="flex h-full w-full items-center justify-center bg-gray-100">

            <!-- Title Card -->
            <div class="flex flex-col items-center justify-center rounded-lg bg-white p-6 shadow-lg">
                <div class="w-4/5 border-b-2 pb-4">
                    <x-application-logo class="mx-auto w-40" />
                </div>
                <h1 class="pt-4 text-center text-xl">Welcome!</h1>

                <div class="flex justify-evenly mx-auto space-x-2 mt-4">
                    <a href="{{ route('login') }}"><x-primary-button class="text-center">Login</x-primary-button></a>
                    <a href="{{ route('register') }}"><x-secondary-button class="text-center">Register</x-secondary-button></a>
                </div>
            </div>

        </main>

    </body>
```

<hr>

## 2. Install Team-based RBAC Package

Install [Spatie's Laravel-Permission](https://github.com/spatie/laravel-permission) package: `composer require spatie/laravel-permission`

Publish the migration and configuration files: `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`

Enable the teams feature in the package config file (`config/permission.php`):

```diff
- 'teams' => false,
+ 'teams' => true,
```

Create a `TeamsPermission` middleware:

`php artisan make:middleware TeamsPermission`

```diff
- public function handle(Request $request, Closure $next): Response
-    {
-        return $next($request);
-    }
+ public function handle(Request $request, Closure $next): Response
+    {
+        if (!empty(auth()->user())) {
+            // session value set on login
+            setPermissionsTeamId(session('team_id'));
+        }
+
+        return $next($request);
+ }
```

***Intelephense Error***
Don't worry if intelephense highlights an `Undefined method 'user'` error on `auth()->user()` - it's erroneous and will work regardless!

Modify the application bootstrap file (`bootstrap\app.php`) to specify the middleware priority order and alias the `Role`, `Permission`, and `RoleOrPermission` middleware:

```diff
withMiddleware(function (Middleware $middleware) {
-        //
+		$middleware->priority([
+            \App\Http\Middleware\TeamsPermission::class,
+        ]);
+
+        $middleware->alias([
+            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
+            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
+            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
+        ]);
    })
```

<hr>

## 3. Configure User Model & Migration

Modify the User model to use first and last names, and implement roles and email verification:

```diff
- // use Illuminate\Contracts\Auth\MustVerifyEmail;
+ use Illuminate\Contracts\Auth\MustVerifyEmail;
+ use Spatie\Permission\Traits\HasRoles;

- class User extends Authenticatable
+ class User extends Authenticatable implements MustVerifyEmail

- use HasFactory, Notifiable;
+ use HasFactory, Notifiable, HasRoles;

protected $fillable = [
-	'name',
+	'first_name',
+	'last_name',
	'email',
	'password',
];
```

Add teams relationships and related functions to the User model:

```diff
+    /**
+     * Get the teams that the user belongs to.
+     *
+     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
+     */
+    public function teams()
+    {
+        return $this
+            ->belongsToMany(Team::class)
+            ->using(TeamUser::class)
+            ->withPivot(['role_id', 'role_name']);
+    }
+
+    /**
+     * Get the team that the user is actively viewing.
+     *
+     * @return \App\Models\Team|null
+     */
+    public function activeTeam()
+    {
+        return $this->belongsTo(Team::class, 'active_team_id');
+    }
+
+    /**
+     * Set the active team for the user.
+     *
+     * @param  \App\Models\Team  $team
+     * @return void
+     */
+    public function setActiveTeam(Team $team)
+    {
+        setPermissionsTeamId($team->id);
+        $this->active_team_id = $team->id;
+        $this->save();
+    }
+
+    /**
+     * Get the role for the user on the given team.
+     *
+     * @param  \App\Models\Team  $team
+     * @return \App\Models\Role
+     */
+    public function getRoleForTeam(Team $team)
+    {
+        return $this->teams()->where('team_id', $team->id)->first()->pivot->role;
+    }
+
+    public function assignRoleToTeam(Team $team, Role $role)
+    {
+        $this->teams()->syncWithoutDetaching([$team->id => ['role_id' => $role->id]]);
+    }
```

***Intelephense Errors***
Don't worry about the `Undefined type 'App\Models\TeamUser'` and `Undefined type 'App\Models\Role'` intelephense errors - we will create those models shortly.

Modify the User migration to use first and last names:

```diff
- $table->string('name');

+ $table->string('first_name');
+ $table->string('last_name');
```

Modify the Register blade to use first and last names:

```diff
// public variables
- public string $name = '';
+ public string $firstName = '';
+ public string $lastName = '';

// validation
- 'name' => ['required', 'string', 'max:255'],
+ 'firstName' => ['required', 'string', 'max:255'],
+ 'lastName' => ['required', 'string', 'max:255'],

// form
- <!-- Name -->
- <div>
-	<x-input-label for="name" :value="__('Name')" />
-	<x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" />
-	<x-input-error :messages="$errors->get('name')" class="mt-2" />
- </div>
+ <!-- First Name -->
+ <div>
+	<x-input-label for="firstName" :value="__('First Name')" />
+	<x-text-input wire:model="firstName" id="firstName" class="block mt-1 w-full" type="text" name="firstName" required autofocus autocomplete="given-name" />
+	<x-input-error :messages="$errors->get('firstName')" class="mt-2" />
+ </div>
+
+ <!-- Last Name -->
+ <div>
+	<x-input-label for="lastName" :value="__('Last Name')" />
+	<x-text-input wire:model="lastName" id="lastName" class="block mt-1 w-full" type="text" name="lastName" required autofocus autocomplete="family-name" />
+	<x-input-error :messages="$errors->get('lastName')" class="mt-2" />
+ </div>
```

***Autocomplete Tokens***
Reference: [MDN Docs](https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/autocomplete)
User agents expect autocomplete fields for first and last names to use `given-name` and `family-name` labels, which are used in the forms, however all other code uses `firstName` / `first_name` and `lastName` / `last_name` for clarity and consistency.

Modify the Update Profile blade to also use first and last names:

```diff
// public variables
- public string $name = '';
+ public string $firstName = '';
+ public string $lastName = '';

// mount function
- $this->name = Auth::user()->name;
+ $this->firstName = Auth::user()->first_name;
+ $this->lastName = Auth::user()->last_name;

// validation
- 'name' => ['required', 'string', 'max:255'],
+ 'firstName' => ['required', 'string', 'max:255'],
+ 'lastName' => ['required', 'string', 'max:255'],

- $this->dispatch('profile-updated', name: $user->name);
+ $this->dispatch('profile-updated', name: $user->first_name);

// form
- <div>
- 	<x-input-label for="name" :value="__('Name')" />
- 	<x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full" required autofocus autocomplete="name" />
- 	<x-input-error class="mt-2" :messages="$errors->get('name')" />
- </div>
+ <div>
+ 	<x-input-label for="firstName" :value="__('First Name')" />
+ 	<x-text-input wire:model="firstName" id="firstName" class="block mt-1 w-full" type="text" name="firstName" required autofocus autocomplete="given-name" />
+ 	<x-input-error :messages="$errors->get('firstName')" class="mt-2" />
+ </div>

+ <div>
+ 	<x-input-label for="lastName" :value="__('Last Name')" />
+ 	<x-text-input wire:model="lastName" id="lastName" class="block mt-1 w-full" type="text" name="lastName" required autofocus autocomplete="family-name" />
+ 	<x-input-error :messages="$errors->get('lastName')" class="mt-2" />
+ </div>
```

<hr>

## 4. Create a Team Model & Migration

`php artisan make:model Team -m`

Add a `name` field to the `create_teams_table` migration:

```diff
Schema::create('teams', function (Blueprint $table) {
	$table->id();
+	$table->string('name');
	$table->timestamps();
});
```

Add a `team_user` pivot table to the migration:

```diff
+ Schema::create('team_user', function (Blueprint $table) {
+	$table->id();
+	$table->foreignId('team_id')->constrained()->cascadeOnDelete();
+	$table->foreignId('user_id')->constrained()->cascadeOnDelete();
+	$table->foreignId('role_id')->constrained()->cascadeOnDelete();
+	$table->string('role_name');
+	$table->timestamps();
+ });
```

Include the new pivot table in the `down` method:

```diff
public function down(): void
{
	Schema::dropIfExists('teams');
+	Schema::dropIfExists('team_user)
}
```

Build-out the `Team` model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\Role;

class Team extends Model
{
    use HasFactory;

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
```

***`boot` and `ignoreBoot`***
The `boot` function is used to assign the `Super Admin` user to all new teams when they are created. This must be disabled during database seeding to avoid errors, so the `ignoreBoot` function is used to set and unset the `ignoreBoot` flag when the seeder is running.

***Intelephense Error***
Don't worry about the `Undefined type 'App\Models\TeamUser'` intelephense error - we will create that model shortly.

Create a `TeamUser` pivot model: `php artisan make:model TeamUser --pivot`

Build out the `TeamUser` model:

```diff
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
+ use App\Models\Team;
+ use App\Models\User;
+ use App\Models\Role;

class TeamUser extends Pivot
{
-    //
+    protected $fillable = [
+        'team_id',
+        'user_id',
+        'role_id'
+    ];
+
+    /**
+     * The "booted" method of the model.
+     * 
+     * @return void
+     */
+    protected static function boot()
+    {
+        parent::boot();
+
+        static::creating(function ($teamUser) {
+            $team = Team::findOrFailByHashid($teamUser->team_id);
+            $user = User::findOrFailByHashid($teamUser->user_id);
+            $role = Role::find($teamUser->role_id);
+            $teamUser->role_name = $role->name;
+            $teamUser->created_at = now();
+        });
+
+        static::updating(function ($teamUser) {
+            $team = Team::findOrFailByHashid($teamUser->team_id);
+            $user = User::findOrFailByHashid($teamUser->user_id);
+            $role = Role::find($teamUser->role_id);
+            $teamUser->role_name = $role->name;
+            $teamUser->updated_at = now();
+        });
+    }
}
```

<hr>

## 5. Extend the Role & Permission Models

Create Role and Permission models:

```pwsh
php artisan make:model Role
php artisan make:model Permission
```

Replace the default Role model scaffolding to extend the Spatie Role model:

```diff
<?php

namespace App\Models;

- use Illuminate\Database\Eloquent\Factories\HasFactory;
- use Illuminate\Database\Eloquent\Model;
- 
- class Role extends Model
- {
-     use HasFactory;
- }
+ use Spatie\Permission\Models\Role as SpatieRole;
+ use Spatie\Permission\Contracts\Role as RoleContract;
+ 
+ class Role extends SpatieRole implements RoleContract
+ {
+     /**
+     * Get the team that the role belongs to.
+     *
+     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
+     */
+     public function team()
+     {
+         return $this->belongsTo(Team::class);
+     }
+ }
```

Replace the default Permission model scaffolding to extend the Spatie Permission model:

```diff
<?php

namespace App\Models;

- use Illuminate\Database\Eloquent\Factories\HasFactory;
- use Illuminate\Database\Eloquent\Model;
- 
- class Permission extends Model
- {
-     use HasFactory;
- }
+ use Spatie\Permission\Models\Permission as SpatiePermission;
+ use Spatie\Permission\Contracts\Permission as PermissionContract;
+ 
+ class Permission extends SpatiePermission implements PermissionContract
+ {
+     /**
+     * Get the team that the permission belongs to.
+     *
+     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
+     */
+     public function team()
+     {
+         return $this->belongsTo(Team::class);
+     }
+ }
```

Update the `config/permission.php` file to use these extended models:

```diff
- 'permission' => Spatie\Permission\Models\Permission::class,
+ 'permission' => App\Models\Permission::class,
- 'role' => Spatie\Permission\Models\Role::class,
+ 'role' => App\Models\Role::class,
```

<hr>

## 6. Basic Security

### Replacing Incrementing IDs

Incrementing ID values for primary keys are the default, and make database management simple, however there are times when we don't want the user to know certain information that this might expose - for example, that they are user number 15, or that they are working on team 2.

To get around this, we have a couple of options:

1. Replace incrementing IDs with UUIDs; or,
2. Use Hashids to obfuscate the actual ID number.

#### Option 1 - UUIDs

Since Laravel version 9.30, the `HasUuids` trait has been included for use. This presents a simple way to implement UUIDs as primary keys, however I've found some issues when using UUIDs with Spatie's `laravel-permission` package. For that reason, we'll use [[#Option 2 - Hashids]].

#### Option 2 - Hashids

***Reference***
This section uses the *excellent* write-up by [Julien Bourdea](https://www.julienbourdeau.com/laravel-hashid/) for guidance.

***Important***
Using `hashids` is **not** an encryption measure - it is simply 'security through obfuscation'. This means that, while ID values presented to the user will appear random and complex, it cannot be used to store sensitive data. We use it here to reduce the opportunity for users to guess the next value in a sequence (such as guessing that if a User entity has an `id === 5` then the next entity will have an `id === 6`). This combines with effective RBAC to build additional layers into the app's security configuration. Secure data encryption will be implemented shortly.

Install [Vinkla's Hashids](https://github.com/vinkla/hashids) package: `composer require hashids/hashids`

Modify the `app/Providers/AppServiceProvider.php` file to register the function definition for `hashids`:

```diff
+ use Hashids\Hashids;

public function register(): void
{
-	//
+	$this->app->singleton('hashid', function () {
+		return new Hashids(config('app.key'), 8);
+	});
}
```

Note that we use the App Key (set in the `.env` file) as the salt for the Hashids object.

Create a new directory and custom façade (`app/Support/Facade/Hashid.php`):

```php
<?php
 
namespace App\Support\Facade;
 
use Illuminate\Support\Facades\Facade;
 
class Hashid extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'hashid';
    }
}
```

Create a new trait to use with models: `php artisan make:trait Traits/HasHashid`:

```php
<?php
 
namespace App\Models;
 
use App\Support\Facade\Hashid;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
 
trait HasHashid
{
    public function getHashidAttribute()
    {
        return static::HASHID_PREFIX.$this->hashid_without_prefix;
    }
 
    public function getHashidWithoutPrefixAttribute()
    {
        return Hashid::encode($this->id); 
    }

	public static function findOrFailByHashid($hid) 
    {
	    if (!Str::startsWith($hid, static::HASHID_PREFIX)) {
            return static::findOrFail($hid);
        }
        
        $hash = Str::after($hid, static::HASHID_PREFIX);
        $ids = Hashid::decode($hash);
 
        if (empty($ids)) {
            throw new ModelNotFoundException();
        }
 
        return static::findOrFail($ids[0]);
    }
}
```

***Intelephense Error***
Don't worry about the `Undefined class constant 'HASHID_PREFIX'` intelephense error - this will be added to the models using the trait.

***Modified `findOrfailByHashid` Method***
If you've checked out the reference linked above, you might notice that the `findOrFailByHashid` method here includes an extra `if` condition. For some god-forsaken reason, this method seems to be called implicitly when passing a type-hinted user model (`myFunc(User $user)`) and thus throws an error when it can't decode a numeric ID. To combat this (and after pulling my hair out for several hours trying to find the cause) I've added a simple check to skip the decoding if the `$hid` parameter doesn't look like a hashid.

Modify the User model to use the new `HasHashid` trait:

```diff
+ use App\Traits\HasHashid;

- use HasFactory, Notifiable, HasRoles;
+ use HasFactory, Notifiable, HasRoles, HasHashid;
+
+ /**
+ * The prefix for the hashid.
+ *
+ * @var string
+ */
+ const HASHID_PREFIX = 'usr_';
```

Modify the Team model to use the same trait:

```diff
+ use App\Traits\HasHashid;

- use HasFactory;
+ use HasFactory, HasHashid;
+ +
+ /**
+ * The prefix for the hashid.
+ *
+ * @var string
+ */
+ const HASHID_PREFIX = 'team_';
```

Finally, modify the `AppServiceProvider.php` file again, this time adding a route binding for each of the models:

```diff
use Illuminate\Support\ServiceProvider;
+ use Illuminate\Support\Facades\Route;
use Hashids\Hashids;
+ use App\Models\User;
+ use App\Models\Team;

public function boot(): void
{
-	//
+ Route::bind('user_hashid', function ($value) {
+     return User::findOrFailByHashid($value);
+ });
+ 
+ Route::bind('team_hashid', function ($value) {
+     return Team::findOrFailByHashid($value);
+ });
}
```

***Important!***
Note that you will need to add a new route binding for each model that implements the `HasHashid` trait, otherwise the routes will not be resolvable!

### Encrypting Data

The second part of this basic security implementation is encrypting the data that will be stored in the database.

Ensure that the `.env.` file has a value set for the `APP_KEY` variable. If it doesn't, run `php artisan key:generate` to create one.

Modify the User model to handle encryption and decryption of attributes:

```diff
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
+ use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
+ use Illuminate\Support\Facades\Crypt;
use App\Traits\HasHashid;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles, HasHashid;
    ...
+    /**
+     * Encrypt / decrypt the first_name attribute.
+     * 
+     * @return Illuminate\Database\Eloquent\Casts\Attribute
+     */
+    protected function firstName(): Attribute
+    {
+        return Attribute::make(
+            set: fn (string $value) => Crypt::encryptString($value),
+            get: fn (string $value) => Crypt::decryptString($value),
+        );
+    }
+
+    /**
+     * Encrypt / decrypt the last_name attribute.
+     * 
+     * @return Illuminate\Database\Eloquent\Casts\Attribute
+     */
+    protected function lastName(): Attribute
+    {
+        return Attribute::make(
+            set: fn (string $value) => Crypt::encryptString($value),
+            get: fn (string $value) => Crypt::decryptString($value),
+        );
+    }
	...
```

These [Accessors and Mutators](https://laravel.com/docs/11.x/eloquent-mutators#accessors-and-mutators) automatically handle the encryption and decryption of sensitive assets (in this case, the user's name and email address). Similar Accessors and Mutators (known in some other languages as 'Getters' and 'Setters') will be defined in other models throughout the project.

Note that the email attribute isn't encrypted. Laravel's default login feature attempts to match the user-provided email string to one in the Users table; if the latter is encrypted, the only way to do this is to have the login-logic retrieve and decrypt all email addresses, comparing each one to that provided by the user.

An alternative would be to use Usernames to log in, however this would add more complexity to the application and isn't required for this example.

By default, Laravel uses [AES-256-CBC](https://docs.anchormydata.com/docs/what-is-aes-256-cbc) encryption, however it is possible to set a different encryption algorithm by publishing the `vendor\laravel\framework\src\Illuminate\Encryption\Encrypter.php` file. The supported ciphers are: AES-128-CBC, AES-256-CBC, AES-128-GCM, and AES-256-GCM.

<hr>

## 7. Configure Soft-Deletes

***Soft-Deleting***
When models are soft deleted, they are not actually removed from your database. Instead, a `deleted_at` attribute is set on the model indicating the date and time at which the model was "deleted".

Modify both the User and Team models to use the `SoftDeletes` trait:

```diff
// app/Models/User
...
+ use Illuminate\Database\Eloquent\SoftDeletes;
...
- use HasFactory, Notifiable, HasRoles, HasHashid;
+ use HasFactory, Notifiable, HasRoles, HasHashid, SoftDeletes;
```

```diff
// app/Models/Team
...
+ use Illuminate\Database\Eloquent\SoftDeletes;
...
- use HasFactory, Notifiable, HasRoles, HasHashid;
+ use HasFactory, HasHashid, SoftDeletes;
```

Modify the migrations for both models to include a `deleted_at` column (using Laravel's schema builder):

```diff
// create_users_table
...
$table->timestamps();
+ $table->softDeletes();
```

```diff
// create_teams_table
...
$table->timestamps();
+ $table->softDeletes();
```

This will ensure that when the `delete` method is called on one of these models, the `deleted_at` column will be timestamped and the model will be ignored from most queries, but the record will remain in the database for record-keeping.

We'll configure restoration, permanent deletes etc for users with the correct permissions later.

More information on [Soft Deletes](https://laravel.com/docs/11.x/eloquent#soft-deleting) can be found in the Laravel docs.

<hr>

## 8. Initial Roles & Permissions

We'll configure three initial roles, with varying permission levels:

- **Super Admin** - all permissions;
- **Staff Member** - read users and manage own requirements;
- **Project Manager** - manage own teams, requirements, and projects; and,
- **Customer** - read-only permissions.

These will be modified as the project grows.

Create a new seeder: `php artisan make:seeder PermissionRoleSeeder`

Build-out the seeder scaffolding:

>This can be a fairly complex file for developers new to permissions, so we'll build it in steps with explanatory notes where appropriate. The Complete Code is available at the end of this section.

```diff
<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
+ use Illuminate\Support\Str;
+ use App\Models\Permission;
+ use App\Models\Role;
```

```php
/**
 * Define permissions and their sub-permissions.
 */
const PERMISSIONS = [
        // Super Admin
        'super admin' => ['force delete'],
        // User Management
        'manage users' => ['create users', 'read users', 'update users', 'delete users'],
        // Team Management
        'manage own teams' => ['create own teams', 'read own teams', 'update own teams', 'delete own teams'],
        'manage all teams' => ['read all teams', 'update all teams', 'delete all teams'],
        // Requirement Management
        'manage own requirements' => ['create own requirements', 'read own requirements', 'update own requirements', 'delete own requirements'],
        'manage all requirements' => ['read all requirements', 'update all requirements', 'delete all requirements'],
        // Project Management
        'manage own projects' => ['create own projects', 'read own projects', 'update own projects', 'delete own projects'],
        'manage all projects' => ['read all projects', 'update all projects', 'delete all projects'],
    ];
```

Here we define a class constant with both main and sub-permissions; this provides some benefits:

- It's easier to see all permissions in one place;
- Permissions can be assigned *with dependencies* (`manage users`) or *individually* (`read users`)

Next we'll create some helper methods:

```php
/**
 * Assign permissions to roles with dependent permissions.
 */
private function assignPermissionsWithDependencies($roleName, $permissions)
{
	$role = Role::create(['name' => $roleName]);
	foreach ($permissions as $permission) {
		$role->givePermissionTo($permission);

		// Assign dependent permissions
		$dependentPermissions = $this->getDependentPermissions($permission);
		foreach ($dependentPermissions as $dependentPermission) {
			$role->givePermissionTo($dependentPermission);
		}
	}
}
```

```php
/**
 * Get the dependent permissions of a permission.
 */
private function getDependentPermissions($permission)
{
	return self::PERMISSIONS[$permission] ?? [];
}
```

Within the `run` method of the class, we need to clear the cached roles and permissions before creating new ones:

```diff
public function run(): void
{
+	/**
+	 * Clear the cache of permissions.
+	 */
+	app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
```

Then create the permissions from those defined in the `PERMISSIONS` constant:

```php
/**
 * Create permissions and their sub-permissions.
 */
foreach (self::PERMISSIONS as $main => $subs) {
	Permission::create(['name' => $main]);
	foreach ($subs as $sub) {
		Permission::create(['name' => $sub]);
	}
}
```

Create the four basic roles:

```php
/**
 * Create a global Super Admin role.
 */
Role::create(['name' => 'Super Admin']);

/**
 * Create a Project Manager role.
 */
$this->assignPermissionsWithDependencies('Project Manager', [
	'manage own teams',
	'manage own requirements',
	'manage own projects',
]);

/**
 * Create a global Staff role
 * and assign permissions to it.
 */
$this->assignPermissionsWithDependencies('Staff', [
	'read users',
	'manage own requirements',
]);

/**
 * Create a global Customer role
 * and assign permissions to it.
 */
$this->assignPermissionsWithDependencies('Customer', [
	'manage own requirements',
	'read all requirements',
]);
```

Note that we didn't assign any permissions to the Super Admin role. This will be handled using a Gate method in the next section.

### Complete Code

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Permission;
use App\Models\Role;

class PermissionRoleSeeder extends Seeder
{
    /**
     * Define permissions and their sub-permissions.
     */
    const PERMISSIONS = [
        'force delete',
        'manage users' => ['create users', 'read users', 'update users', 'delete users'],
        'manage teams' => ['create teams', 'read teams', 'update teams', 'delete teams'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /**
         * Clear the cache of permissions.
         */
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        /**
         * Create permissions and their sub-permissions.
         */
        foreach (self::PERMISSIONS as $main => $subs) {
            Permission::create(['name' => $main]);
            foreach ($subs as $sub) {
                Permission::create(['name' => $sub]);
            }
        }

        /**
         * Create a global Super Admin role.
         */
        Role::create(['name' => 'Super Admin']);

        /**
         * Create a global Staff role
         * and assign permissions to it.
         */
        $this->assignPermissionsWithDependencies('Staff', [
            'manage users',
            'manage teams',
        ]);

        /**
         * Create a global Customer role
         * and assign permissions to it.
         */
        $this->assignPermissionsWithDependencies('Customer', [
            'read users',
            'read teams',
        ]);
    }

    /**
     * Assign permissions to roles with dependent permissions.
     */
    private function assignPermissionsWithDependencies($roleName, $permissions)
    {
        $role = Role::create(['name' => $roleName]);
        foreach ($permissions as $permission) {
            $role->givePermissionTo($permission);

            // Assign dependent permissions
            $dependentPermissions = $this->getDependentPermissions($permission);
            foreach ($dependentPermissions as $dependentPermission) {
                $role->givePermissionTo($dependentPermission);
            }
        }
    }

    /**
     * Get the dependent permissions of a permission.
     */
    private function getDependentPermissions($permission)
    {
        return self::PERMISSIONS[$permission] ?? [];
    }
}
```
<hr>

## 9. Configuring Super Admin Access

***From the Spatie docs***
>We strongly recommend that a Super-Admin be handled by setting a global `Gate::before` or `Gate::after` rule which checks for the desired role.
>
>Then you can implement the best-practice of primarily using permission-based controls (`@can` and `$user->can`, etc) throughout your app, without always having to check for "is this a super-admin" everywhere. **Best not to use role-checking (ie: `hasRole`) (except here in Gate/Policy rules) when you have Super Admin features like this.**

Modify the `app/Providers/AppServiceProvider` file:

```diff
...
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
+ use Illuminate\Support\Facades\Gate;
use Hashids\Hashids;
use App\Models\User;
use App\Models\Team;
...
    public function boot(): void
    {
+	    /**
+        * Implicitly grant all permissions to the Super Admin role
+        * and allow the Super Admin role to bypass all policies.
+        */
+        Gate::before(function (User $user, string $ability) {
+            return $user->hasRole('Super Admin');
+        });
```

Using `Gate::after` ensures that, while the Super Admin role will be able to bypass all permission checks, it **won't** be able to perform actions that *no user* should be able to perform.

<hr>

## 10. Seeding Users & Teams

For development, we'll need a few users in the database so that we can verify our security configurations work.

Modify the User factory to use first and last names, and generate a matching email address:

```diff
public function definition(): array
{
+	$firstName = fake()->firstName();
+   $lastName = fake()->lastName();
+   $email = strtolower($firstName . '.' . $lastName . (string) rand(100,999) . '@example.com');

	return [
-		'name' => fake()->name(),
+		'first_name' => $firstName,
+		'last_name' => $lastName,
-		'email' => fake()->unique()->safeEmail(),
+		'email' => $email,
		'email_verified_at' => now(),
		'password' => static::$password ??= Hash::make('password'),
		'remember_token' => Str::random(10),
	];
}
```

Create a Team factory (`php artisan make:factory TeamFactory`) and add a `name` field:

```diff
public function definition(): array
{
	return [
-		//
+		'name' => fake()->unique()->company(),
	];
}
```

Create a User seeder (`php artisan make:seeder UserSeeder`) and a Team seeder (`php artisan make:seeder TeamSeeder`).

Build out the User seeder scaffolding:

```diff
<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
+ use App\Models\Team;
+ use App\Models\User;
+ use App\Models\Role;

class UserSeeder extends Seeder
{
+	/**
+     * Flag to determine if fake data should be used.
+     */
+    const FAKE_DATA = true;
+
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
-        //
+		switch (self::FAKE_DATA) {
+            case true:
+                $this->seedFakeData();
+                break;
+            case false:
+                $this->seedRealData();
+                break;
+        }
    }
}
```

Here, we've included the Team, User, and Role models, added a flag for easy switching between fake and real seed data, and included a switch statement for the same in the `run` method.

Before we build the `seedFakeData` method, and add a placeholder for the `seedRealData` method, we need to include a helper method for assigning users to teams with roles:

```php
public function assignToTeamWithRole(User $user, Team $team, Role $role): void
{
	$team->users()->attach($user->id, ['role_id' => $role->id]);

	setPermissionsTeamId($team->id);
	$user->assignRole($role->name);
	$team->users()->attach($user->id, ['role_id' => $role->id]);

	echo "Assigned $user->first_name $user->last_name as $role->name on $team->name" . PHP_EOL;
}
```

***Important: `setPermissionsTeamId`***
Note that this method uses the `setPermissionsTeamId` function from the `laravel-permission` package - this is needed to ensure that the role is assigned to the correct team. Without it, the `team_user` pivot table could show one team (for example, `team-a`) while the role will actually be assigned to a different team (`team-b`).

Now we can add the other two methods:

```php
/**
 * Seed fake data for development.
 */
private function seedFakeData(): void     
{
	// Create a Super Admin user
	$superAdminUser = User::factory()->create([
		'first_name' => 'Super',
		'last_name' => 'Admin',
		'email' => 'super.admin123@example.com',
	]);
	
	// Give the Super Admin user the Super Admin role on all teams
	foreach (Team::all() as $team) {
		$this->assignToTeamWithRole($superAdminUser, $team, Role::findByName('Super Admin'));
	}

	// Create 10 Staff users and assign them to the Staff team
        $staffUsers = User::factory(10)->create()->each(function ($user) {
            $this->assignToTeamWithRole($user, Team::where('name', 'Staff')->first(), Role::findByName('Staff'));
        });

        // Assign 5 Staff users as Project Managers on teams
        $teamId = 3;
        $projectManagers = $staffUsers->random(5);
        foreach ($projectManagers as $user) {
            $team = Team::find($teamId);
            $this->assignToTeamWithRole($user, $team, Role::findByName('Project Manager'));
            $teamId++;
        }

        // Create 5 Customer users, echoeing their names and emails
        $staffUsers = User::factory(10)->create()->each(function ($user) {
            $this->assignToTeamWithRole($user, Team::where('name', 'Customers')->first(), Role::findByName('Customer'));
        });
}

/**
 * Seed real data for production.
 */
private function seedRealData(): void
{
	//
}
```

If you have real data to include in the seeder, you can add it to the `seedRealData` method and swap the flag now; otherwise leave it empty.

Next, modify the Team seeder scaffolding in a similar way:

```diff
<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
+ use App\Models\Team;

class TeamSeeder extends Seeder
{
+    /**
+     * Flag to determine if fake data should be used.
+     */
+    const FAKE_DATA = true;
+
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
-        //
+        switch (self::FAKE_DATA) {
+            case true:
+                $this->seedFakeData();
+                break;
+            case false:
+                $this->seedRealData();
+                break;
+        }
    }
+
+    private function seedFakeData(): void
+    {
+        // Create 7 teams, ignoring the boot method
+        // 1 team each for Staff and Customers
+        // 5 random teams to represent projects
+        Team::ignoreBoot(true);
+        Team::factory()->create(['name' => 'Staff']);
+        Team::factory()->create(['name' => 'Customers']);
+        Team::factory(5)->create();
+        echo "Teams created successfully" . PHP_EOL;
+        Team::ignoreBoot(false);
+    }
+
+    private function seedRealData(): void
+    {
+        //
+    }
}

```

Finally, modify the `database/seeders/DatabaseSeeder` file to call the PermissionRole, Team, and User seeders:

```diff
<?php

namespace Database\Seeders;

- // use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
- use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
-        // User::factory(10)->create();
-
-        User::factory()->create([
-            'name' => 'Test User',
-            'email' => 'test@example.com',
-        ]);
+		$this->call([
+            PermissionRoleSeeder::class,
+            TeamSeeder::class,
+            UserSeeder::class,
+        ]);
    }
}
```

***Seeder Order***
Note the order in which we call the seeders - `PermissionRoleSeeder`, then `TeamSeeder`, then `UserSeeder`. This is important as the `UserSeeder` depends on the existence of Teams in the database; if it is called before the teams are seeded, an error will be thrown.

Now that the basic configuration is complete, run the migrations and seed the database:

```pwsh
php artisan migrate:fresh --seed
```
