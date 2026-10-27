<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // weight, bike weight, riding position, FTP and difficulty: so a player finds them on every device.
            // Weight is sensitive: it never goes in the user resource, only in the player's own settings endpoint.
            $table->json('rider_settings')->nullable();
            $table->timestamp('rider_settings_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['rider_settings', 'rider_settings_updated_at']);
        });
    }
};
