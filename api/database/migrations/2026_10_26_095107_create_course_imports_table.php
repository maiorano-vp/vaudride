<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_imports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('source_url', 2048)->nullable();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('licence_status')->default('unknown');
            $table->longText('gpx');
            $table->json('summary');
            $table->json('preview');
            $table->string('course_slug')->nullable();
            $table->decimal('trim_start_m', 10, 1)->nullable();
            $table->decimal('trim_end_m', 10, 1)->nullable();
            $table->boolean('reversed')->default(false);
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();
        });
    }
};
