<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $password = config('users.admin.password');

        if (blank($password)) {
            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => config('users.admin.email')],
            ['name' => config('users.admin.name'), 'password' => $password, 'is_admin' => true],
        );
        $user->forceFill(['email_verified_at' => now()])->save();
    }
};
