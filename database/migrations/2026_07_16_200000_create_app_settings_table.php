<?php

use App\Enums\BodyColor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single-row table, not key/value: the fields are a fixed, known set, so
     * real columns give types and nullability for free.
     *
     * Colours are stored as '#rrggbb' strings; the selectable presets live in
     * App\Enums\{ButtonColor,BodyColor} and are enforced by ColorRequest. The
     * defaults match the design tokens in resources/css/app.css, so a fresh
     * install looks identical until an admin changes something.
     */
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();

            // Branding.
            $table->string('app_name', 50);
            // Who holds the copyright in the footer — a person or company, which
            // is not always the product name. Null falls back to the app name,
            // so nothing changes until an admin sets it.
            $table->string('copyright_holder')->nullable();
            // Paths on the 'public' disk. Null means "fall back to the built-in mark".
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            // neutral-900 — today's --primary in light mode.
            $table->string('button_color', 7)->default('#171717');
            $table->string('body_color', 7)->default(BodyColor::White->value);

            /*
             * Admin-authored spending guidance shown on the dashboard.
             *
             * Two messages — a warning and a piece of advice — each stored as a
             * translatable JSON blob ({"en": "...", "km": "..."}), the same shape
             * spatie/translatable uses for category names.
             */
            // Master switch, gating both messages. Off by default so a fresh
            // install shows nothing until an admin writes the copy.
            $table->boolean('spending_guidance_enabled')->default(false);
            // Nullable: the feature can be enabled with only one of the two
            // messages filled in, and a blank locale falls back on read.
            $table->json('spending_warning')->nullable();
            $table->json('spending_advice')->nullable();

            // Riel per one US dollar, used to convert a price entered in KHR to
            // the USD that is actually stored. The riel is pegged near 4000, so
            // a fixed editable rate is enough — no live feed to depend on.
            $table->decimal('khr_per_usd', 10, 2)->default(4100);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
