<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            // distances along the course (m); length, climb and grade come from the course profile
            $table->unsignedInteger('start_m');
            $table->unsignedInteger('end_m');
            $table->string('category')->nullable();
            $table->boolean('published')->default(true);
            $table->timestamps();

            $table->index(['course_id', 'start_m']);
        });
    }
};
