<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_category_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_type', 16);
            $table->string('description', 255)->nullable();
            $table->string('normalized_description', 255)->index();
            $table->foreignId('suggested_category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('selected_category_id')->constrained('categories')->restrictOnDelete();
            $table->boolean('was_accepted');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'transaction_type', 'normalized_description'], 'ai_feedback_user_type_desc_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_category_feedback');
    }
};
