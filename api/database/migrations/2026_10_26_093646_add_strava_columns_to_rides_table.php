<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rides', function (Blueprint $table): void {
            $table->unsignedBigInteger('strava_upload_id')->nullable();
            $table->unsignedBigInteger('strava_activity_id')->nullable();
            $table->string('strava_error')->nullable();
        });
    }
};
