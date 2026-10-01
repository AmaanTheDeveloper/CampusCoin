<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotent budget alert records to prevent duplicate in-app notifications.
     */
    public function up(): void
    {
        Schema::create('budget_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->string('alert_type', 32);
            $table->timestamp('triggered_at');
            $table->timestamps();

            $table->unique(['budget_id', 'alert_type'], 'budget_alerts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_alerts');
    }
};
