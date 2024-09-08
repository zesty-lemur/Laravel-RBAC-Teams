<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Team;

class TeamSeeder extends Seeder
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
     * Seed the database with fake data.
     */
    private function seedFakeData(): void
    {
        // Create 7 teams, ignoring the boot method
        // 1 team each for Staff and Customers
        // 5 random teams to represent projects
        Team::ignoreBoot(true);
        Team::factory()->create(['name' => 'Staff']);
        Team::factory()->create(['name' => 'Customers']);
        Team::factory(5)->create();
        echo "Teams created successfully" . PHP_EOL;
        Team::ignoreBoot(false);
    }

    /**
     * Seed the database with real data.
     */
    private function seedRealData(): void
    {
        //
    }
}
