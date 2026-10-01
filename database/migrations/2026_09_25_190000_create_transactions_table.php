<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Core financial transaction records (Phase 3).
     * Category linkage is deferred to Phase 4 — no category_id here.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->decimal('amount', 12, 2);
            $table->string('description', 255)->nullable();
            $table->date('transaction_date');
            $table->timestamps();
            $table->softDeletes();

            // History / date-range queries for a single user
            $table->index(['user_id', 'transaction_date']);
            // Type filter + date ordering for a single user
            $table->index(['user_id', 'type', 'transaction_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
