<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks generated occurrences to prevent duplicate recurring generation.
     */
    public function up(): void
    {
        Schema::create('recurring_transaction_occurrences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recurring_transaction_id');
            $table->date('occurrence_date');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->timestamps();

            $table->foreign('recurring_transaction_id', 'rto_recurring_fk')
                ->references('id')
                ->on('recurring_transactions')
                ->cascadeOnDelete();

            $table->foreign('transaction_id', 'rto_transaction_fk')
                ->references('id')
                ->on('transactions')
                ->nullOnDelete();

            $table->unique(['recurring_transaction_id', 'occurrence_date'], 'recurring_occurrence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_transaction_occurrences');
    }
};
