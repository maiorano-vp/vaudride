<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            // what the route is like to ride for real, per language: {"fr": "...", "de": "...", "en": "..."}
            $table->json('description')->nullable();
        });
    }
};
