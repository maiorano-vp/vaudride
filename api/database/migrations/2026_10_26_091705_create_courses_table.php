<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->json('name');
            $table->unsignedSmallInteger('version')->default(1);
            $table->unsignedInteger('distance_m');
            $table->unsignedInteger('elevation_gain_m');
            $table->string('attribution')->default('© swisstopo');
            $table->boolean('published')->default(false);
            $table->timestamps();
        });
    }
};
