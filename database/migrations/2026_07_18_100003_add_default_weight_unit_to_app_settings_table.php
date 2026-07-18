<?php

use App\Enums\WeightUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            /*
             * Which unit the weight fields start on — entry only. Every weight is
             * still stored in kilograms, exactly as every amount is stored in USD
             * regardless of default_currency, which this sits beside.
             */
            $table->string('default_weight_unit', 3)
                ->default(WeightUnit::Kg->value)
                ->after('default_currency');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('default_weight_unit');
        });
    }
};
