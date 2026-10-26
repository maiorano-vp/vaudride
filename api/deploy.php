<?php

declare(strict_types=1);

namespace Deployer;

use RuntimeException;

require 'recipe/common.php';
require 'recipe/laravel.php';

// Project name
set('application', 'vaudride-api');

set('repository', 'git@github.com:maiorano-vp/vaudride.git');

// The Laravel app lives in api/ inside the monorepo: only that folder becomes the release.
set('sub_directory', 'api');

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', false);

// Production uses SQLite: the file lives in {{deploy_path}}/shared and is symlinked into every release, so it
// survives deploys (deploy:shared creates it empty on the first run; migrate then builds the schema).
add('shared_files', ['database/database.sqlite']);

// Number of releases kept on the server before older ones are pruned.
set('keep_releases', 5);

// Public URL checked by deploy:healthcheck after each release (Laravel's built-in /up route).
set('healthcheck_url', 'https://vaudride.myvaud.dev/up');

/**
 * Optional: on hosts whose docroot cannot be pointed at <release>/public (e.g. Infomaniak shared hosting),
 * set this to the public path and it becomes a symlink into `current/public`, which Deployer re-points on
 * every deploy. Leave null when the web server's docroot can target {{deploy_path}}/current/public directly.
 */
set('public_path_symlink', null);

// Shared hosting: no detectable http user, so set it explicitly (same as the other Infomaniak projects).
set('http_user', 'www-data');

// Hosts
host('production')
    ->setHostname('x17jd.ftp.infomaniak.com')
    ->setRemoteUser('x17jd_dev')
    ->setDeployPath('~/sites/vaudride.myvaud.dev')
    ->set('branch', 'main');

/**
 * Main deploy task, mirroring the Laravel recipe's default. There is no frontend build here: this app is
 * API-only (the Nuxt app is deployed separately). artisan:queue:restart makes Strava-upload queue workers
 * pick up new code; see the note below about how the worker is run.
 */
desc('Deploys your project');
task('deploy', [
    'deploy:prepare',
    'composer:install',
    'artisan:storage:link',
    'artisan:config:cache',
    'artisan:route:cache',
    'db:backup',
    'artisan:migrate',
    'deploy:publish',
    'artisan:queue:restart',
]);

// TODO: confirm how the queue worker runs on the server (Supervisor, or a cron job running
// `queue:work --stop-when-empty` on shared hosting). Rides are only uploaded to Strava while one is running.

// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

desc('Symlinks the public docroot path to the release currently live via `current`');
task('deploy:public-symlink', function () {
    $link = get('public_path_symlink');

    if ($link) {
        run("ln -sfn {{deploy_path}}/current/public {$link}");
    }
});

after('deploy:symlink', 'deploy:public-symlink');

desc('Copies the SQLite database to shared/backups before migrating (keeps the last 10)');
task('db:backup', function () {
    $db = '{{deploy_path}}/shared/database/database.sqlite';
    $dir = '{{deploy_path}}/shared/backups';

    // an empty file is the first deploy: nothing worth saving yet
    run("[ -s {$db} ] && mkdir -p {$dir} && cp {$db} {$dir}/database-$(date +%Y%m%d-%H%M%S).sqlite || true");
    run("ls -1t {$dir}/database-*.sqlite 2>/dev/null | tail -n +11 | xargs -r rm -f || true");
});

desc('Checks that the API is up');
task('deploy:healthcheck', function () {
    $url = get('healthcheck_url');
    $status = run("curl -s -o /dev/null -w '%{http_code}' -L \"{$url}\"");

    if (! in_array((int) $status, [200, 301, 302], true)) {
        throw new RuntimeException("Health check failed: {$url} returned HTTP {$status}");
    }

    info("Health check passed: {$url} returned HTTP {$status}");
});

after('deploy:publish', 'deploy:healthcheck');

// Shared hosting often has no composer binary Deployer can detect: call `composer` straight from the PATH.
task('composer:install', function () {
    run('cd {{release_or_current_path}} && composer install {{composer_options}} 2>&1');
});
