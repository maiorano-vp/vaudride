<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Course sources folder
    |--------------------------------------------------------------------------
    |
    | Where `course:export` writes route.gpx / overrides.yaml / source.yaml for the pipeline.
    | Points at the versioned /courses folder of the monorepo.
    |
    */

    'source_path' => env('COURSES_SOURCE_PATH', base_path('../courses')),

];
