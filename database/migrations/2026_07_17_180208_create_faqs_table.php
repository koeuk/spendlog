<?php

use App\Enums\FaqStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // {"en": "...", "km": "..."} — spatie/laravel-translatable, same as
            // categories. No unique index: MySQL cannot index a JSON column.
            $table->json('question');
            $table->json('answer');

            // Draft entries are admin-only; the help page shows published rows.
            $table->string('status', 20)->default(FaqStatus::Draft->value);

            // The hand-set reading order on the help page. Gapped so a reorder
            // rewrites the whole set rather than needing fractional positions.
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            // The help page reads published rows in order on every visit.
            $table->index(['status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
