<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\StoreCourseProfile;
use App\Models\Course;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

#[Signature('course:publish {--publish : Publish the new courses (they are created unpublished)}')]
#[Description('Creates a course from every profile.json the pipeline built that has no course yet')]
final class PublishCourseProfilesCommand extends Command
{
    public function handle(StoreCourseProfile $store): int
    {
        $created = 0;

        foreach (File::glob(config()->string('courses.dist_path').'/*/profile.json') ?: [] as $file) {
            $json = File::get($file);
            $profile = json_decode($json, true);
            $slug = is_array($profile) ? (string) ($profile['slug'] ?? '') : '';

            if ($slug === '' || Course::query()->where('slug', $slug)->exists()) {
                continue;
            }

            try {
                $summary = $store->handle($slug, $json);
            } catch (ValidationException $e) {
                $this->warn("{$slug}: ".collect($e->errors())->flatten()->first());

                continue;
            }

            Course::query()->create([
                'slug' => $slug,
                'name' => ['fr' => $profile['name'] ?? $slug],
                'version' => $profile['version'] ?? 1,
                'attribution' => $profile['attribution'] ?? '© swisstopo',
                'published' => (bool) $this->option('publish'),
                ...$summary,
            ]);
            $created++;
        }

        $this->info("{$created} courses created".($this->option('publish') ? '.' : ' (unpublished).'));

        return self::SUCCESS;
    }
}
