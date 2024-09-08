<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\User;
use App\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Flag to determine if fake data should be used.
     */
    const FAKE_DATA = true;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        switch (self::FAKE_DATA) {
            case true:
                $this->seedFakeData();
                break;
            case false:
                $this->seedRealData();
                break;
        }
    }

    /**
     * Assign a user to a team with a role.
     */
    public function assignToTeamWithRole(User $user, Team $team, Role $role): void
    {
        setPermissionsTeamId($team->id);
        $team->users()->attach($user->id, ['role_id' => $role->id]);
        $user->assignRole($role->name);

        echo "Assigned $user->first_name $user->last_name as $role->name on $team->name" . PHP_EOL;
    }

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


}
