<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "I rode this course for real": declared by the player, on a day. Visible to them alone (their passport);
        // the reports only get the number of declarations per course.
        Schema::create('real_rides', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('course_id')->constrained()->cascadeOnDelete();
            $table->date('ridden_on');
            $table->timestamps();

            $table->unique(['user_id', 'course_id', 'ridden_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_rides');
    }
};
