<?php

use App\Enums\CategoryColor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // {"en": "Food", "km": "អាហារ"} — spatie/laravel-translatable.
            // No unique index: MySQL cannot index a JSON column directly, so
            // per-locale uniqueness is enforced in CategoryRequest instead.
            $table->json('name');

            // Stored per row so chart colours stay stable as categories change.
            $table->string('color', 20)->default(CategoryColor::Slate->value);
            $table->string('icon', 40)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
