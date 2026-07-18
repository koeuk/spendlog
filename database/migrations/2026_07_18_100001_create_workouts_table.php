<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('performed_on');
            /*
             * How long the session took, in seconds.
             *
             * Nullable because it is optional: the timer fills it in when one was
             * run, and a workout logged after the fact from memory may only have
             * sets. Seconds rather than minutes so the stopwatch can write its
             * reading back without losing precision.
             */
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Drives the date-grouped log and every dashboard series and streak.
            $table->index(['user_id', 'performed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workouts');
    }
};
