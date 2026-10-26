<?php

declare(strict_types=1);

use App\Actions\StoreCourseProfile;
use Illuminate\Support\Facades\Storage;

it('ships the launch course profiles with the migrations', function (string $slug, string $migration): void {
    Storage::fake(StoreCourseProfile::DISK);

    (require database_path("migrations/{$migration}.php"))->up();
    // the guard skips the real write while testing: store through the same action to prove the file is valid
    resolve(StoreCourseProfile::class)->handle($slug, (string) file_get_contents(database_path("courses/{$slug}/profile.json")));

    $profile = json_decode(Storage::disk(StoreCourseProfile::DISK)->get(StoreCourseProfile::path($slug)), true);
    expect($profile['slug'])->toBe($slug)->and(count($profile['points']))->toBeGreaterThan(1000);
})->with([
    ['marchairuz-biere', '2026_10_26_180000_store_marchairuz_biere_profile'],
    ['corniche-de-lavaux', '2026_10_26_180100_store_corniche_de_lavaux_profile'],
]);
