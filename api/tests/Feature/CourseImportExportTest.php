<?php

declare(strict_types=1);

use App\Models\CourseImport;
use App\Models\User;
use App\Services\GpxRoute;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->coursesPath = sys_get_temp_dir().'/vaudride-courses-'.bin2hex(random_bytes(4));
    config(['courses.source_path' => $this->coursesPath]);
});

afterEach(function (): void {
    File::deleteDirectory($this->coursesPath);
});

it('exports a reviewed import for the pipeline', function (): void {
    $import = CourseImport::factory()->cleared()->create(['title' => 'Col de Test', 'author' => 'Léa', 'reversed' => true, 'trim_start_m' => 100.0, 'trim_end_m' => 900.0]);

    $this->artisan('course:export', ['import' => $import->id])->assertSuccessful();

    $dir = $this->coursesPath.'/sample-climb';
    $route = GpxRoute::parse((string) file_get_contents($dir.'/route.gpx'));
    expect($route->points[0]['lat'])->toBeGreaterThan($route->points[array_key_last($route->points)]['lat']) // reversed
        ->and($route->distanceM())->toBeLessThan(900)
        ->and((string) file_get_contents($dir.'/overrides.yaml'))->toContain('name: "Col de Test"')
        ->and((string) file_get_contents($dir.'/source.yaml'))->toContain('licence_status: "own_work"')->toContain('author: "Léa"')
        ->and($import->fresh()->exported_at)->not->toBeNull();
});

it('refuses routes with unknown rights unless forced', function (): void {
    $import = CourseImport::factory()->create(['course_slug' => 'sample-climb']);

    $this->artisan('course:export', ['import' => $import->id])->assertFailed();
    expect(File::exists($this->coursesPath.'/sample-climb/route.gpx'))->toBeFalse();

    $this->artisan('course:export', ['import' => $import->id, '--force' => true])->assertSuccessful();
    expect(File::exists($this->coursesPath.'/sample-climb/route.gpx'))->toBeTrue();
});

it('needs a slug and never overwrites an existing overrides file', function (): void {
    $noSlug = CourseImport::factory()->create(['licence_status' => 'own_work']);
    $this->artisan('course:export', ['import' => $noSlug->id])->assertFailed();

    $import = CourseImport::factory()->cleared()->create();
    File::ensureDirectoryExists($this->coursesPath.'/sample-climb');
    File::put($this->coursesPath.'/sample-climb/overrides.yaml', "name: Hand edited\n");

    $this->artisan('course:export', ['import' => $import->id])->assertSuccessful();

    expect((string) file_get_contents($this->coursesPath.'/sample-climb/overrides.yaml'))->toBe("name: Hand edited\n");
});

it('fails on an unknown import id', function (): void {
    $this->artisan('course:export', ['import' => 'nope'])->assertFailed();
});

it('promotes a user to admin', function (): void {
    $user = User::factory()->create(['email' => 'lea@example.com']);

    $this->artisan('user:make-admin', ['email' => 'lea@example.com'])->assertSuccessful();
    expect($user->fresh()->is_admin)->toBeTrue();

    $this->artisan('user:make-admin', ['email' => 'nobody@example.com'])->assertFailed();
});
