<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saving_tips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tip_key', 120);
            $table->string('period', 7); // YYYY-MM
            $table->string('title', 160);
            $table->text('body');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('impact_level', 16); // high|medium|low
            $table->unsignedInteger('rank_score')->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tip_key', 'period']);
            $table->index(['user_id', 'period', 'dismissed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saving_tips');
    }
};
