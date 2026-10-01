<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type', 16); // viewed|edited
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['user_id', 'transaction_id', 'activity_type'], 'txn_activity_user_txn_type_unique');
            $table->index(['user_id', 'activity_type', 'occurred_at'], 'txn_activity_user_type_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_activities');
    }
};
