<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_disbursements', function (Blueprint $table) {
            $table->foreignId('transaction_id')
                ->nullable()
                ->after('failure_reason')
                ->constrained('transactions')
                ->nullOnDelete();
            $table->unique('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('loan_disbursements', function (Blueprint $table) {
            $table->dropUnique(['transaction_id']);
            $table->dropConstrainedForeignId('transaction_id');
        });
    }
};
