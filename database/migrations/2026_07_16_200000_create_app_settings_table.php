<?php

use App\Enums\BodyColor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single-row table, not key/value: the branding fields are a fixed, known
     * set, so real columns give types and nullability for free.
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
            $table->string('app_name', 50);
            // Paths on the 'public' disk. Null means "fall back to the built-in mark".
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            // neutral-900 — today's --primary in light mode.
            $table->string('button_color', 7)->default('#171717');
            $table->string('body_color', 7)->default(BodyColor::White->value);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
