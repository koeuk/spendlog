<?php

use App\Enums\CategoryColor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Four places, like every other money column — see expenses.price.
            $table->decimal('target_amount', 12, 4);
            $table->date('deadline')->nullable();
            // Reuses the category palette so the frontend has one colour map.
            $table->string('color', 32)->default(CategoryColor::cases()[0]->value);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_goals');
    }
};
