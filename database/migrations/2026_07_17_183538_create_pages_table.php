<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * The footer's editable pages. A fixed set: the admin fills these in, but the
     * footer never grows an orphan link because nothing creates new slugs.
     */
    private const PAGES = ['about', 'privacy'];

    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Stable identifier the footer links to — 'about', 'privacy'.
            $table->string('slug', 40)->unique();

            // {"en": "...", "km": "..."} — spatie/laravel-translatable.
            $table->json('title');
            $table->json('body');

            // Off until the admin writes it and turns it on; the footer only
            // links published pages.
            $table->boolean('published')->default(false);

            $table->timestamps();
        });

        // Seed the fixed set as empty drafts so the settings screen has a row to
        // edit for each — the admin fills the copy, no create flow needed.
        foreach (self::PAGES as $slug) {
            DB::table('pages')->insert([
                'uuid' => (string) Str::uuid(),
                'slug' => $slug,
                'title' => json_encode([]),
                'body' => json_encode([]),
                'published' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
