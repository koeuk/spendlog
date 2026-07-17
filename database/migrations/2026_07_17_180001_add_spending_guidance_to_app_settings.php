<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-authored spending guidance shown on the dashboard.
 *
 * Two messages — a warning and a piece of advice — each stored as a translatable
 * JSON blob ({"en": "...", "km": "..."}), the same shape spatie/translatable uses
 * for category names. One enabled flag gates the whole thing: off means neither
 * message renders anywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // Master switch. Off by default so an upgraded install shows nothing
            // new until an admin writes the copy and turns it on.
            $table->boolean('spending_guidance_enabled')->default(false);
            // Nullable: the feature can be enabled with only one of the two
            // messages filled in, and a blank locale falls back on read.
            $table->json('spending_warning')->nullable();
            $table->json('spending_advice')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'spending_guidance_enabled',
                'spending_warning',
                'spending_advice',
            ]);
        });
    }
};
