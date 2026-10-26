<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segment_efforts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('segment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ride_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('time_s', 8, 2);
            $table->timestamp('started_at');
            $table->unsignedSmallInteger('avg_power_w')->nullable();
            $table->timestamps();

            $table->unique(['segment_id', 'ride_id']);
            $table->index(['segment_id', 'time_s']);
        });
    }
};
