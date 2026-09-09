<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Savings stopped being "goals with a target" and became a monthly plan.
     *
     * The goals go; every savings_entries row stays exactly where it is. The
     * money and its dates are the part people care about, and entries already
     * carry their own user_id — the goal was only ever a grouping.
     */
    public function up(): void
    {
        // The FK and the index that spans the column have to go before the
        // column itself: MySQL refuses to drop a column a constraint depends on.
        Schema::table('savings_entries', function (Blueprint $table) {
            $table->dropForeign(['savings_goal_id']);
            $table->dropIndex(['savings_goal_id', 'saved_on']);
            $table->dropColumn('savings_goal_id');
        });

        Schema::dropIfExists('savings_goals');
    }

    /**
     * Rolls the schema back, not the data: the table comes back empty and the
     * column comes back nullable, because there is no goal left to point the
     * surviving entries at. Nullable rather than a default of 0, which would be
     * an FK pointing at a row that does not exist.
     */
    public function down(): void
    {
        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('target_amount', 12, 4);
            $table->date('deadline')->nullable();
            $table->string('color', 32)->default('slate');
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::table('savings_entries', function (Blueprint $table) {
            $table->foreignId('savings_goal_id')->nullable()->after('user_id')
                ->constrained()->cascadeOnDelete();
            $table->index(['savings_goal_id', 'saved_on']);
        });
    }
};
