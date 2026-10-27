<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // the next days at the start of each course, from MeteoSwiss (refreshed by `weather:sync`)
        Schema::create('course_forecasts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->decimal('temp_min', 4, 1)->nullable();
            $table->decimal('temp_max', 4, 1)->nullable();
            $table->decimal('precipitation_mm', 5, 1)->nullable();
            $table->unsignedSmallInteger('pictogram')->nullable(); // MeteoSwiss weather symbol number
            $table->string('place'); // the MeteoSwiss point the figures are for
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['course_id', 'day']);
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->json('best_months')->nullable(); // [4, 5, 6, 9, 10]: the months worth coming for
        });
    }
};
