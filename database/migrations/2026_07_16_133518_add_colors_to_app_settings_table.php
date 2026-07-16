<?php

use App\Enums\BodyColor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stored as '#rrggbb' rather than an enum: the body colour offers five
     * presets but also accepts any custom value, so the column has to hold
     * arbitrary hex. The presets live in App\Enums\BodyColor for the UI.
     *
     * The defaults match the current design tokens in resources/css/app.css, so
     * existing installs look identical until an admin changes something.
     */
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // neutral-900 — today's --primary in light mode.
            $table->string('button_color', 7)->default('#171717');
            $table->string('body_color', 7)->default(BodyColor::White->value);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['button_color', 'body_color']);
        });
    }
};
