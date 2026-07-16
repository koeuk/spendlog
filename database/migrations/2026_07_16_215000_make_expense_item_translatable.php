<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * item: varchar → json, matching categories.name.
     *
     * The existing rows hold bare strings like 'Lunch'. Changing the column type
     * alone is not enough: spatie json_decodes the value on read, and 'Lunch' is
     * not valid JSON, so every item would silently render blank — exactly what
     * happened to categories.name. So the data is wrapped first, in a new column,
     * and only then swapped in.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->json('item_translations')->nullable()->after('item');
        });

        // One statement rather than a PHP loop: JSON_OBJECT escapes for us, and
        // this runs over every row at once.
        DB::statement("UPDATE expenses SET item_translations = JSON_OBJECT('en', item)");

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('item');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->renameColumn('item_translations', 'item');
        });

        // The staging column had to be nullable to be added to populated rows;
        // restore the constraint the original column carried, or an expense with
        // no item at all becomes representable.
        Schema::table('expenses', function (Blueprint $table) {
            $table->json('item')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('item_plain', 255)->nullable()->after('item');
        });

        // Falls back to the Khmer value when English is absent, so a row that
        // only ever had a Khmer item does not come back empty.
        DB::statement(
            "UPDATE expenses SET item_plain = COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(item, '$.en')),
                JSON_UNQUOTE(JSON_EXTRACT(item, '$.km')),
                ''
            )"
        );

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('item');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->renameColumn('item_plain', 'item');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('item', 255)->nullable(false)->change();
        });
    }
};
