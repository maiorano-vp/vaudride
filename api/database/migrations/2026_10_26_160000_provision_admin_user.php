<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $password = config('users.admin.password');

        if (blank($password)) {
            return;
        }

        // on a fresh database this runs before the column is renamed (see rename_users_name_to_nickname)
        $nameColumn = Schema::hasColumn('users', 'nickname') ? 'nickname' : 'name';

        $user = User::query()->updateOrCreate(
            ['email' => config('users.admin.email')],
            [$nameColumn => config('users.admin.nickname'), 'password' => $password, 'is_admin' => true],
        );
        $user->forceFill(['email_verified_at' => now()])->save();
    }
};
