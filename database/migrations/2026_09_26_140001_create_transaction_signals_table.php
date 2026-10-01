<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comparison_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('signal_type', 32); // large_transaction|duplicate_transaction
            $table->string('severity', 16)->default('medium'); // low|medium|high
            $table->string('status', 16)->default('open'); // open|dismissed|reviewed
            $table->string('reason', 255);
            $table->string('fingerprint', 64);
            $table->json('payload')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint'], 'txn_signals_user_fingerprint_unique');
            $table->index(['user_id', 'status', 'signal_type'], 'txn_signals_user_status_type_idx');
            $table->index(['user_id', 'transaction_id'], 'txn_signals_user_txn_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_signals');
    }
};
