<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

/** Inserts the launch courses that are missing; never overwrites what the admin panel edited. */
final class CourseSeeder extends Seeder
{
    public function run(): void
    {
        Course::query()->firstOrCreate(['slug' => 'marchairuz-biere'], [
            'name' => [
                'fr' => 'Col du Marchairuz depuis Bière',
                'de' => 'Col du Marchairuz ab Bière',
                'en' => 'Col du Marchairuz from Bière',
            ],
            'version' => 1,
            'distance_m' => 10_717,
            'elevation_gain_m' => 748,
            'published' => true,
        ]);

        Course::query()->firstOrCreate(['slug' => 'corniche-de-lavaux'], [
            'name' => [
                'fr' => 'Corniche de Lavaux',
                'de' => 'Corniche de Lavaux',
                'en' => 'Corniche de Lavaux',
            ],
            'version' => 1,
            'distance_m' => 15_588,
            'elevation_gain_m' => 348,
            'published' => true,
        ]);
    }
}
