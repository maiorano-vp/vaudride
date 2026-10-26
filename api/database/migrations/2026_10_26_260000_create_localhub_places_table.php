<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // a compact copy of the Local Hub catalogue (about 4 000 places), refreshed by `localhub:sync`:
        // the API takes minutes to read in full, an import per course must not wait for it
        Schema::create('localhub_places', function (Blueprint $table): void {
            $table->string('id')->primary(); // "localhub:<id>"
            $table->json('title');
            $table->json('summary')->nullable();
            $table->decimal('lat', 9, 6);
            $table->decimal('lon', 9, 6);
            $table->string('address')->nullable();
            $table->string('url')->nullable();
            $table->string('category');
            $table->timestamp('synced_at');

            $table->index(['lat', 'lon']);
        });
    }
};
