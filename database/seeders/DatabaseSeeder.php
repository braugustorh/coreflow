<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ShieldSeeder::class,
            SedeSeeder::class,
            ProyectoSeeder::class,
            AdminUserSeeder::class,
            EmployeeUsersSeeder::class,
            ElementSeeder::class,
            AssayMethodSeeder::class,
            StandardSampleSeeder::class,
            SampleSettingSeeder::class,
        ]);
    }
}
