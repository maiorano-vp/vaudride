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
            $table->string('external_id')->nullable(); // "localhub:<id>": the place's id in the source it was imported from
            $table->string('address')->nullable();

            $table->unique(['course_id', 'external_id']);
        });
    }
};
