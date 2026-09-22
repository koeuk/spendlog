<?php

use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');

            /*
             * A display handle, not a credential — signing in is still email and
             * password. Nullable so an account may have none, and unique so the
             * ones that are set stay distinct.
             *
             * Unique *and* nullable is the combination that does what is wanted
             * here: SQL treats NULLs as distinct from one another, so any number
             * of accounts may have no username while no two may share one.
             *
             * Uniqueness is case-insensitive for free — the column inherits the
             * table's utf8mb4_unicode_ci collation, so "Koeuk" and "koeuk" collide
             * rather than becoming two accounts nobody can tell apart.
             */
            $table->string('username', 30)->nullable()->unique();

            $table->string('email')->unique();
            // Contact detail only — never used to sign in, so no unique index.
            $table->string('phone', 32)->nullable();
            // The relative path on the 'public' disk, like branding — the URL
            // is derived at read time so APP_URL can change without a backfill.
            // Nullable: an account with no photo shows its initial instead.
            $table->string('avatar_path')->nullable();

            /*
             * Google sign-in.
             *
             * The subject id rather than matching on email alone: an email
             * address is something a provider reports, and matching on it makes
             * "prove you own this address" the provider's job forever. The
             * subject id is stable, unique to the account, and survives the user
             * renaming their Gmail.
             */
            $table->string('google_id')->nullable()->unique();

            $table->timestamp('email_verified_at')->nullable();
            // Nullable because an account that only ever arrived through Google
            // has no password to store, and a hash of nothing is worse than a
            // null: it would look like a credential that could be tried.
            $table->string('password')->nullable();
            // Every sign-in reads this, and the admin user list filters on it.
            $table->string('status', 20)->default(UserStatus::Active->value)->index();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
