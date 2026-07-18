<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            // Who holds the copyright in the footer — a person or company, which
            // is not always the product name. Null falls back to the app name,
            // so nothing changes until an admin sets it.
            $table->string('copyright_holder')->nullable()->after('app_name');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('copyright_holder');
        });
    }
};
