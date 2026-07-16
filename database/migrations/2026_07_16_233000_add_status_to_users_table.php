<?php

use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Existing accounts are in use, so they default to active — a default
            // of suspended would lock everyone out the moment this runs.
            $table->string('status', 20)
                ->default(UserStatus::Active->value)
                ->after('password');

            // Every sign-in reads this, and the admin list filters on it.
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
