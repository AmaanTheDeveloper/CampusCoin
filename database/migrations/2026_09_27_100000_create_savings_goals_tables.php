<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Long-term savings goal tracker (one active goal per user).
 * Distinct from users.savings_goal (monthly profile target for Money Pulse).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('target_amount', 12, 2);
            $table->decimal('current_amount', 12, 2)->default(0);
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('savings_goal_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_goal_id')->constrained('savings_goals')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('note', 255)->nullable();
            $table->timestamp('contributed_at');
            $table->timestamps();

            $table->index(['savings_goal_id', 'contributed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_goal_contributions');
        Schema::dropIfExists('savings_goals');
    }
};
