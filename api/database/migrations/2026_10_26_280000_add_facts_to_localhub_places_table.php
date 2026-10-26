<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('localhub_places', function (Blueprint $table): void {
            // the attributes an editor filled in, kept so the category rules can change without reading the API again
            $table->json('facts')->nullable();
        });
    }
};
