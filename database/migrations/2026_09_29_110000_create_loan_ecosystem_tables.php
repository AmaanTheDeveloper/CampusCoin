<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('official_email');
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 80)->nullable();
            $table->string('registration_number')->nullable();
            $table->string('authorized_representative');
            $table->string('representative_email');
            $table->string('representative_phone', 40)->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('staff_role', 32);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['bank_id', 'user_id']);
        });

        Schema::create('bank_verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 64);
            $table->string('original_name');
            $table->string('disk_path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status', 32)->default('uploaded');
            $table->timestamps();
        });

        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->decimal('min_amount', 12, 2);
            $table->decimal('max_amount', 12, 2);
            $table->unsignedSmallInteger('min_tenure_months');
            $table->unsignedSmallInteger('max_tenure_months');
            $table->string('repayment_frequency', 32)->default('monthly');
            $table->decimal('annual_rate_percent', 8, 4)->default(0);
            $table->decimal('processing_fee_percent', 8, 4)->default(0);
            $table->decimal('processing_fee_fixed', 12, 2)->default(0);
            $table->boolean('allows_partial_payments')->default(false);
            $table->json('required_documents')->nullable();
            $table->json('eligibility_notes')->nullable();
            $table->boolean('is_published')->default(false);
            $table->string('status', 32)->default('draft');
            $table->timestamps();
            $table->unique(['bank_id', 'slug']);
        });

        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_product_id')->constrained()->cascadeOnDelete();
            $table->decimal('requested_amount', 12, 2);
            $table->unsignedSmallInteger('requested_tenure_months');
            $table->string('purpose')->nullable();
            $table->text('applicant_notes')->nullable();
            $table->string('status', 48)->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['bank_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('loan_application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 64);
            $table->string('original_name');
            $table->string('disk_path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status', 32)->default('uploaded');
            $table->text('reviewer_note')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 48)->nullable();
            $table->string('to_status', 48);
            $table->string('action', 64);
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('loan_application_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_product_id')->constrained()->cascadeOnDelete();
            $table->decimal('principal_amount', 12, 2);
            $table->unsignedSmallInteger('tenure_months');
            $table->decimal('annual_rate_percent', 8, 4)->default(0);
            $table->decimal('installment_amount', 12, 2);
            $table->decimal('total_repayable', 12, 2);
            $table->decimal('outstanding_balance', 12, 2);
            $table->string('status', 48)->default('approved');
            $table->timestamp('agreement_accepted_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['bank_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('loan_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status', 32)->default('pending');
            $table->string('reference')->nullable();
            $table->string('destination_note')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date');
            $table->decimal('amount_due', 12, 2);
            $table->decimal('principal_component', 12, 2)->default(0);
            $table->decimal('interest_component', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('status', 32)->default('upcoming');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['loan_id', 'installment_number']);
        });

        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_installment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PKR');
            $table->string('status', 32)->default('initiated');
            $table->string('provider', 32)->default('sandbox');
            $table->string('provider_transaction_id')->nullable()->index();
            $table->string('payment_method_type', 32)->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('loan_payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_payment_id')->constrained()->cascadeOnDelete();
            $table->string('provider_event_id')->nullable()->unique();
            $table->string('event_type', 64);
            $table->string('payload_hash', 64)->nullable();
            $table->json('payload')->nullable();
            $table->boolean('processed')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('loan_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 32)->nullable();
            $table->string('action', 64);
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_audit_logs');
        Schema::dropIfExists('loan_payment_events');
        Schema::dropIfExists('loan_payments');
        Schema::dropIfExists('loan_installments');
        Schema::dropIfExists('loan_disbursements');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('loan_application_events');
        Schema::dropIfExists('loan_application_documents');
        Schema::dropIfExists('loan_applications');
        Schema::dropIfExists('loan_products');
        Schema::dropIfExists('bank_verification_documents');
        Schema::dropIfExists('bank_memberships');
        Schema::dropIfExists('banks');
    }
};
