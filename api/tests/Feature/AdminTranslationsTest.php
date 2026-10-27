<?php

declare(strict_types=1);

use App\Actions\FindMissingTranslations;
use App\Filament\Widgets\IncompleteTranslationsTable;
use App\Filament\Widgets\TranslationStats;
use App\Models\Course;
use App\Models\Poi;
use App\Models\Segment;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('counts the texts that lack German or English, across the course, its segments and its places', function (): void {
    $course = Course::factory()->create([
        'slug' => 'lavaux',
        'published' => true,
        'name' => ['fr' => 'Lavaux', 'de' => 'Lavaux', 'en' => 'Lavaux'],
        'description' => ['fr' => 'Les vignes', 'de' => 'Die Reben'], // no English
    ]);
    Segment::factory()->for($course)->create(['published' => true, 'name' => ['fr' => 'Montée']]); // neither
    Poi::factory()->for($course)->create(['published' => true, 'title' => ['fr' => 'Château', 'en' => 'Castle'], 'description' => null]); // no German

    $report = app(FindMissingTranslations::class)->handle();

    expect($report['locales']['de'])->toBe(['total' => 4, 'missing' => 2]) // segment name, place title
        ->and($report['locales']['en'])->toBe(['total' => 4, 'missing' => 2]) // description, segment name
        ->and($report['courses'])->toHaveCount(1)
        ->and($report['courses'][0])->toMatchArray(['slug' => 'lavaux', 'total' => 4, 'missing' => ['de' => 2, 'en' => 2]]);
});

it('leaves out what is not published, what has no French text, and courses that are complete', function (): void {
    $done = Course::factory()->create(['published' => true, 'name' => ['fr' => 'Fini', 'de' => 'Fertig', 'en' => 'Done'], 'description' => null]);
    Segment::factory()->for($done)->create(['published' => false, 'name' => ['fr' => 'Brouillon']]);
    Poi::factory()->for($done)->create(['published' => true, 'title' => ['fr' => 'Lac', 'de' => 'See', 'en' => 'Lake'], 'description' => ['de' => 'nur Deutsch']]);
    Course::factory()->create(['published' => false, 'name' => ['fr' => 'Caché']]);

    $report = app(FindMissingTranslations::class)->handle();

    expect($report['courses'])->toBe([])
        ->and($report['locales']['de']['missing'])->toBe(0)
        ->and($report['locales']['en']['total'])->toBe(2);
});

it('puts the course with the most missing texts first', function (): void {
    $few = Course::factory()->create(['published' => true, 'name' => ['fr' => 'Presque', 'en' => 'Almost']]); // 1 missing (de)
    $many = Course::factory()->create(['published' => true, 'name' => ['fr' => 'Rien'], 'description' => ['fr' => 'Texte']]); // 4 missing

    $courses = app(FindMissingTranslations::class)->handle()['courses'];

    expect(array_column($courses, 'id'))->toBe([$many->id, $few->id]);
});

it('shows the translated share and the courses to translate, to admins', function (): void {
    Course::factory()->create(['published' => true, 'name' => ['fr' => 'À traduire']]);

    Livewire::test(TranslationStats::class)->assertSee('Allemand')->assertSee('Anglais')->assertSee('0 %')->assertSee('1 textes encore en français');
    Livewire::test(IncompleteTranslationsTable::class)->assertSee('À traduire');
});
