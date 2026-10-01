<?php

use App\Enums\RecurringOccurrenceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_transaction_occurrences', function (Blueprint $table) {
            $table->string('status', 32)->default(RecurringOccurrenceStatus::PendingApproval->value)->after('occurrence_date');
            $table->timestamp('last_reminded_at')->nullable()->after('transaction_id');
            $table->timestamp('decided_at')->nullable()->after('last_reminded_at');

            $table->index(['status', 'last_reminded_at'], 'rto_status_reminded_idx');
        });

        // Existing rows that already posted a transaction are approved.
        DB::table('recurring_transaction_occurrences')
            ->whereNotNull('transaction_id')
            ->update([
                'status' => RecurringOccurrenceStatus::Approved->value,
                'decided_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('recurring_transaction_occurrences', function (Blueprint $table) {
            $table->dropIndex('rto_status_reminded_idx');
            $table->dropColumn(['status', 'last_reminded_at', 'decided_at']);
        });
    }
};
