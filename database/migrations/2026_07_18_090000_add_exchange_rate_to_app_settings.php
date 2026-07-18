<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // Riel per one US dollar, used to convert a price entered in KHR to
            // the USD that is actually stored. The riel is pegged near 4000, so
            // a fixed editable rate is enough — no live feed to depend on.
            $table->decimal('khr_per_usd', 10, 2)->default(4100);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('khr_per_usd');
        });
    }
};
