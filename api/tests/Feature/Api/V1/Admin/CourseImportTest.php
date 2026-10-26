<?php

declare(strict_types=1);

use App\Enums\LicenceStatus;
use App\Models\CourseImport;
use App\Models\User;
use Database\Factories\CourseImportFactory;
use Illuminate\Http\UploadedFile;

function admin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

it('keeps the admin endpoints away from guests and regular users', function (): void {
    $this->getJson('/api/v1/admin/course-imports')->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/v1/admin/course-imports')->assertForbidden();
    $this->actingAs(User::factory()->create(), 'sanctum')
        ->postJson('/api/v1/admin/course-imports', ['gpx' => CourseImportFactory::sampleGpx()])->assertForbidden();
});

it('imports GPX sent as text, for example by the browser extension', function (): void {
    $this->actingAs(admin(), 'sanctum')->postJson('/api/v1/admin/course-imports', [
        'gpx' => CourseImportFactory::sampleGpx(),
        'source' => 'komoot',
        'source_url' => 'https://www.komoot.com/tour/123',
        'author' => 'Léa',
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Sample climb')
        ->assertJsonPath('data.source', 'komoot')
        ->assertJsonPath('data.licence_status', 'unknown')
        ->assertJsonPath('data.summary.point_count', 12)
        ->assertJsonCount(12, 'data.preview')
        ->assertJsonPath('data.summary.waypoints.0.name', 'Halfway');

    expect(CourseImport::query()->count())->toBe(1);
});

it('imports an uploaded GPX file', function (): void {
    $file = UploadedFile::fake()->createWithContent('route.gpx', CourseImportFactory::sampleGpx());

    $this->actingAs(admin(), 'sanctum')->postJson('/api/v1/admin/course-imports', ['file' => $file, 'title' => 'My climb'])
        ->assertCreated()->assertJsonPath('data.title', 'My climb')->assertJsonPath('data.source', 'gpx_upload');
});

it('rejects invalid input', function (): void {
    $admin = admin();

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/course-imports', ['gpx' => '<kml/>'])
        ->assertUnprocessable()->assertJsonValidationErrors(['gpx']);
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/course-imports', [])
        ->assertUnprocessable()->assertJsonValidationErrors(['gpx', 'file']);
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/course-imports', ['gpx' => CourseImportFactory::sampleGpx(), 'source_url' => 'http://insecure.test'])
        ->assertUnprocessable()->assertJsonValidationErrors(['source_url']);
});

it('lists imports without the heavy columns and shows one with preview and trimmed result', function (): void {
    $import = CourseImport::factory()->create();
    $admin = admin();

    $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/course-imports')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.preview')->assertJsonMissingPath('data.0.trimmed');

    $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/course-imports/'.$import->id)
        ->assertOk()->assertJsonCount(12, 'data.preview')->assertJsonPath('data.trimmed.distance_m', fn (float $d): bool => abs($d - 1101) < 5);
});

it('saves trim, direction, rights and slug, and previews the trimmed route', function (): void {
    $import = CourseImport::factory()->create();

    $this->actingAs(admin(), 'sanctum')->patchJson('/api/v1/admin/course-imports/'.$import->id, [
        'trim_start_m' => 200,
        'trim_end_m' => 800,
        'reversed' => true,
        'licence_status' => 'own_work',
        'course_slug' => 'sample-climb',
    ])
        ->assertOk()
        ->assertJsonPath('data.licence_status', 'own_work')
        ->assertJsonPath('data.reversed', true)
        ->assertJsonPath('data.trimmed.distance_m', fn (float $d): bool => $d > 500 && $d < 700);

    expect($import->fresh()->licence_status)->toBe(LicenceStatus::OwnWork);
});

it('rejects bad updates', function (): void {
    $import = CourseImport::factory()->create();
    $admin = admin();

    $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/admin/course-imports/'.$import->id, ['trim_start_m' => 500, 'trim_end_m' => 400])
        ->assertUnprocessable()->assertJsonValidationErrors(['trim_end_m']);
    $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/admin/course-imports/'.$import->id, ['course_slug' => 'Not A Slug', 'licence_status' => 'maybe'])
        ->assertUnprocessable()->assertJsonValidationErrors(['course_slug', 'licence_status']);
    $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/admin/course-imports/'.$import->id, ['trim_start_m' => 300, 'trim_end_m' => 310])
        ->assertUnprocessable()->assertJsonValidationErrors(['trim_start_m']);
});
