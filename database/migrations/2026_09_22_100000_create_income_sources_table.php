<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_sources', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            /*
             * The names this account is offered when it logs income, or a
             * deposit into savings.
             *
             * A catalogue *of suggestions*, not a foreign key: incomes.source
             * stays the free text it has always been, for the reason its own
             * migration gives. That separation is what lets a name be renamed
             * or dropped here without rewriting, or losing, the history that
             * used it — and what lets a name typed straight into an income
             * still work when it has never been curated.
             */
            $table->string('name');
            $table->timestamps();

            // One of each name per account; two accounts may both have Salary.
            $table->unique(['user_id', 'name']);
        });

        /*
         * Seed each account's catalogue from the income it already has, so
         * nobody opens this on an empty list and re-types what they have been
         * using for months. The picker read these off the incomes table until
         * now; this is the same set, frozen into rows that can be managed.
         */
        $now = now();

        DB::table('incomes')
            ->select('user_id', 'source')
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->distinct()
            ->orderBy('user_id')
            ->orderBy('source')
            ->chunk(500, function ($rows) use ($now) {
                DB::table('income_sources')->insertOrIgnore(
                    $rows->map(fn ($row) => [
                        'uuid' => (string) Str::uuid(),
                        'user_id' => $row->user_id,
                        'name' => $row->source,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_sources');
    }
};
