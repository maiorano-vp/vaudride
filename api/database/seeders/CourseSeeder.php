<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

final class CourseSeeder extends Seeder
{
    public function run(): void
    {
        Course::query()->updateOrCreate(['slug' => 'marchairuz-biere'], [
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
    }
}
