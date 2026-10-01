<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tip_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->string('category_label', 80)->nullable();
            $table->string('impact_level', 16)->default('medium');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'impact_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tip_templates');
    }
};
