<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rides', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained()->restrictOnDelete();
            $table->timestamp('started_at');
            $table->unsignedInteger('duration_s');
            $table->unsignedInteger('distance_m');
            $table->unsignedInteger('elevation_gain_m');
            $table->unsignedSmallInteger('avg_power_w')->nullable();
            $table->unsignedSmallInteger('max_power_w')->nullable();
            $table->unsignedSmallInteger('avg_heart_rate')->nullable();
            $table->json('samples');
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
        });
    }
};
