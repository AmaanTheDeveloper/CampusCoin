<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Student profile foundation fields (SRS).
     * Monetary columns use DECIMAL for SQLite and MySQL compatibility.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('academic_year', 32)->nullable()->after('role');
            $table->decimal('monthly_allowance', 12, 2)->nullable()->after('academic_year');
            $table->decimal('savings_goal', 12, 2)->nullable()->after('monthly_allowance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['academic_year', 'monthly_allowance', 'savings_goal']);
        });
    }
};
