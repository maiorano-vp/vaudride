<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pois', function (Blueprint $table): void {
            $table->string('category')->nullable();
            $table->string('source')->default('manual'); // manual (Vaud Promotion) or osm (imported, to be reviewed)
            $table->string('osm_id')->nullable(); // "node/123", "way/456"
            $table->boolean('published')->default(true);

            $table->unique(['course_id', 'osm_id']);
        });
    }
};
