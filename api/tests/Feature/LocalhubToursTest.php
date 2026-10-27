<?php

declare(strict_types=1);

use App\Actions\CreateCourseImport;
use App\Enums\ImportSource;
use App\Enums\LicenceStatus;
use App\Filament\Resources\CourseImports\Pages\ListCourseImports;
use App\Models\CourseImport;
use App\Models\User;
use Database\Factories\CourseImportFactory;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    config(['services.localhub.token' => 'secret', 'services.localhub.url' => 'https://localhub.test']);
    User::factory()->create(['is_admin' => true]);
});

function tour(string $name, ?string $route, bool $published = true): array
{
    return ['name' => ['fr' => $name], 'route' => $route, 'is_published' => $published, 'tenant' => ['name' => 'Vaud Promotion'], 'gpx_total_distance' => 12.5, 'gpx_positive_elevation_gain' => 340.4];
}

it('imports the published tours of a category as imports to review, once', function (): void {
    Http::fake([
        'localhub.test/api/v1/tours?page=1&pageSize=1' => Http::response(['data' => ['tours' => ['facetDistribution' => ['categories' => [['id' => 'cat-velo', 'slug' => 'velo', 'name' => ['fr' => 'Vélo'], 'count' => 3]]]]]]),
        'localhub.test/api/v1/tours*' => Http::response(['data' => ['tours' => ['totalPages' => 1, 'hits' => [
            tour('Route des lacs', 'https://files.test/a.gpx'),
            tour('Sans GPX', null),
            tour('Brouillon', 'https://files.test/b.gpx', published: false),
        ]]]]),
        'files.test/a.gpx' => Http::response(CourseImportFactory::sampleGpx()),
    ]);

    $this->artisan('localhub:tours', ['category' => 'velo'])->assertSuccessful();
    $this->artisan('localhub:tours', ['category' => 'velo'])->assertSuccessful();

    expect(CourseImport::query()->count())->toBe(1);

    $import = CourseImport::query()->sole();
    expect($import->source)->toBe(ImportSource::Localhub)
        ->and($import->title)->toBe('Route des lacs')
        ->and($import->author)->toBe('Vaud Promotion')
        ->and($import->licence_status)->toBe(LicenceStatus::Unknown);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://localhub.test/api/v1/tours?page=1&pageSize=100&filter%5Bcategories%5D=cat-velo');
});

it('gives each import a slug from its title, unique among imports', function (): void {
    $user = User::factory()->create();
    $create = resolve(CreateCourseImport::class);

    $first = $create->handle($user, CourseImportFactory::sampleGpx(), ImportSource::GpxUpload, null, 'Col de la Croix – été', null);
    $second = $create->handle($user, CourseImportFactory::sampleGpx(), ImportSource::GpxUpload, null, 'Col de la Croix - ete', null);

    expect($first->course_slug)->toBe('col-de-la-croix-ete')
        ->and($second->course_slug)->toBe('col-de-la-croix-ete-2');
});

it('lists the imports in the admin and exports a cleared one', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $path = sys_get_temp_dir().'/vaudride-courses-'.uniqid();
    config(['courses.source_path' => $path]);
    $import = CourseImport::factory()->cleared()->create(['title' => 'Route des lacs']);
    $unknown = CourseImport::factory()->create(['course_slug' => 'inconnu']);

    Filament::setCurrentPanel('admin');
    Livewire::actingAs($admin)->test(ListCourseImports::class)
        ->assertCanSeeTableRecords([$import, $unknown])
        ->callTableAction('export', $unknown)
        ->assertNotified()
        ->callTableAction('export', $import);

    expect(File::exists($path.'/sample-climb/route.gpx'))->toBeTrue()
        ->and(File::exists($path.'/inconnu'))->toBeFalse();

    File::deleteDirectory($path);
});
