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
            // what the course card on the home page shows: a photo (a scaled copy served by Local Hub) and its
            // copyright line, the tourism destination it belongs to, and the kind of ride (velo, mtb)
            $table->text('image_url')->nullable();
            $table->text('image_credit')->nullable();
            $table->string('destination')->nullable();
            $table->string('kind', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn(['image_url', 'image_credit', 'destination', 'kind']);
        });
    }
};
