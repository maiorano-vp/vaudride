<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['localhub_places', 'pois'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->text('image_url')->nullable(); // the cover image
                $table->text('image_credit')->nullable(); // its copyright line, to show with it
            });
        }
    }
};
