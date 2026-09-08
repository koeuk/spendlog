<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rows a recurring rule created point back at it. Nulling on delete so
     * removing a rule keeps the history it wrote; the unique index is what
     * makes a run idempotent — one row per rule per day, whatever else
     * happens.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('recurring_rule_id')
                ->nullable()
                ->after('category_id')
                ->constrained()
                ->nullOnDelete();
            $table->unique(['recurring_rule_id', 'spent_on']);
        });

        Schema::table('incomes', function (Blueprint $table) {
            $table->foreignId('recurring_rule_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
            $table->unique(['recurring_rule_id', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique(['recurring_rule_id', 'spent_on']);
            $table->dropConstrainedForeignId('recurring_rule_id');
        });

        Schema::table('incomes', function (Blueprint $table) {
            $table->dropUnique(['recurring_rule_id', 'received_on']);
            $table->dropConstrainedForeignId('recurring_rule_id');
        });
    }
};
