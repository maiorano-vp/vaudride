<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // players are known by a nickname (shown to the other riders), not by a real name
        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('name', 'nickname');
        });
    }
};
