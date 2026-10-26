<?php

declare(strict_types=1);

use App\Actions\StoreCourseProfile;
use Illuminate\Database\Migrations\Migration;

// Ships the course profile built by the pipeline (database/courses/corniche-de-lavaux/profile.json) with the deploy
return new class extends Migration
{
    public function up(): void
    {
        // tests write to a faked disk: do not touch the real storage on every database refresh
        if (app()->runningUnitTests()) {
            return;
        }

        resolve(StoreCourseProfile::class)->handle('corniche-de-lavaux', (string) file_get_contents(database_path('courses/corniche-de-lavaux/profile.json')));
    }
};
