<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Who did it. Cascade: an account's history goes with the account,
            // the same way its expenses do.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('action', 20); // created | updated | deleted

            // What it was done to. A plain morph rather than a constrained key
            // because a deleted row is exactly the case the log exists for —
            // the subject_id then points at nothing, and that is fine.
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id')->nullable();

            // A label frozen at the time, so the line still reads after the
            // subject is gone or renamed: "Lunch · $3.00".
            $table->string('subject_label');

            // For updates: {field: {from, to}}. Null otherwise.
            $table->json('changes')->nullable();

            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
