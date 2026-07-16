<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Null means an overall budget covering every category.
            // Restrict rather than cascade: MySQL forbids ON DELETE CASCADE on a
            // column that the category_key generated column below depends on.
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            // Always stored as the first day of the month it applies to.
            $table->date('month');
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            // MySQL treats NULLs as distinct in a unique index, so a plain
            // unique on category_id would not block duplicate overall budgets.
            // Collapsing null to 0 in a generated column restores the guarantee.
            $table->unsignedBigInteger('category_key')->storedAs('COALESCE(category_id, 0)');
            $table->unique(['user_id', 'category_key', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
