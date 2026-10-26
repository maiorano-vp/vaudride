<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // links imported from Local Hub can exceed 255 characters
        Schema::table('pois', function (Blueprint $table): void {
            $table->text('url')->nullable()->change();
            $table->text('address')->nullable()->change();
        });
    }
};
