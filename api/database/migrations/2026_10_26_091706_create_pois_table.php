<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pois', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('distance_m');
            $table->json('title');
            $table->json('description')->nullable();
            $table->decimal('lat', 9, 6);
            $table->decimal('lon', 9, 6);
            $table->string('url')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'distance_m']);
        });
    }
};
