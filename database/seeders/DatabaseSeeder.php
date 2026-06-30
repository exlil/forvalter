<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // The two owners. There is no public registration — accounts are
        // provisioned here. Replace these and set real passwords before use.
        User::updateOrCreate(
            ['email' => 'fredrik.rossland@maksimer.com'],
            ['name' => 'Fredrik Rossland', 'password' => 'password']
        );

        User::updateOrCreate(
            ['email' => 'eier2@forvalter.local'],
            ['name' => 'Eier 2', 'password' => 'password']
        );

        User::updateOrCreate(
            ['email' => 'fredrik@ross.land'],
            ['name' => 'Fredrik', 'password' => 'fredrik']
        );

        $this->call([
            TaxYearSeeder::class,
            DemoSeeder::class,
            TripFavoriteSeeder::class,
        ]);
    }
}
