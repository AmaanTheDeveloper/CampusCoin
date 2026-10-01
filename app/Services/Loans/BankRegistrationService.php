<?php

namespace App\Services\Loans;

use App\Enums\Loans\BankStaffRole;
use App\Enums\Loans\BankStatus;
use App\Enums\Role;
use App\Models\Bank;
use App\Models\BankMembership;
use App\Models\BankVerificationDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BankRegistrationService
{
    public function __construct(private readonly LoanAuditService $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $documents
     */
    public function register(array $data, array $documents = []): Bank
    {
        $this->assertNoDuplicateRegistration($data);

        return DB::transaction(function () use ($data, $documents) {
            $slug = $this->uniqueSlug((string) $data['name']);

            $bank = Bank::query()->create([
                'name' => $data['name'],
                'slug' => $slug,
                'reference_code' => $this->uniqueReferenceCode(),
                'official_email' => $data['official_email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? null,
                'registration_number' => $data['registration_number'] ?? null,
                'authorized_representative' => $data['authorized_representative'],
                'representative_email' => $data['representative_email'],
                'representative_phone' => $data['representative_phone'] ?? null,
                'status' => BankStatus::Pending,
            ]);

            $owner = User::query()->create([
                'name' => $data['authorized_representative'],
                'email' => $data['representative_email'],
                'password' => Hash::make($data['password']),
            ]);
            $owner->forceFill([
                'role' => Role::Bank,
                'email_verified_at' => now(),
            ])->save();

            BankMembership::query()->create([
                'bank_id' => $bank->id,
                'user_id' => $owner->id,
                'staff_role' => BankStaffRole::Owner,
                'is_active' => true,
            ]);

            foreach ($documents as $file) {
                $this->storeVerificationDocument($bank, $file);
            }

            $this->audit->log(null, 'bank.registered', $bank, 'Bank registration submitted', $bank->id);

            return $bank->fresh(['memberships', 'verificationDocuments']);
        });
    }

    public function approve(Bank $bank, User $admin): Bank
    {
        if ($bank->status === BankStatus::Approved) {
            return $bank;
        }

        $bank->forceFill([
            'status' => BankStatus::Approved,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'rejection_reason' => null,
            'suspended_at' => null,
            'suspension_reason' => null,
        ])->save();

        $this->audit->log($admin, 'bank.approved', $bank, 'Bank approved by super admin', $bank->id);

        return $bank->fresh();
    }

    public function reject(Bank $bank, User $admin, string $reason): Bank
    {
        $bank->forceFill([
            'status' => BankStatus::Rejected,
            'rejection_reason' => $reason,
            'approved_by' => null,
            'approved_at' => null,
        ])->save();

        $this->audit->log($admin, 'bank.rejected', $bank, $reason, $bank->id);

        return $bank->fresh();
    }

    public function suspend(Bank $bank, User $admin, string $reason): Bank
    {
        $bank->forceFill([
            'status' => BankStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ])->save();

        $this->audit->log($admin, 'bank.suspended', $bank, $reason, $bank->id);

        return $bank->fresh();
    }

    public function reactivate(Bank $bank, User $admin): Bank
    {
        $bank->forceFill([
            'status' => BankStatus::Approved,
            'suspended_at' => null,
            'suspension_reason' => null,
        ])->save();

        $this->audit->log($admin, 'bank.reactivated', $bank, 'Bank reactivated', $bank->id);

        return $bank->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertNoDuplicateRegistration(array $data): void
    {
        $blockingStatuses = [
            BankStatus::Pending->value,
            BankStatus::UnderReview->value,
            BankStatus::Approved->value,
            BankStatus::Suspended->value,
        ];

        $officialEmail = strtolower(trim((string) $data['official_email']));
        $existingOfficial = Bank::query()
            ->whereRaw('LOWER(official_email) = ?', [$officialEmail])
            ->whereIn('status', $blockingStatuses)
            ->exists();

        if ($existingOfficial) {
            throw ValidationException::withMessages([
                'official_email' => 'A registration request for this bank is already under review or active.',
            ]);
        }

        $registrationNumber = trim((string) ($data['registration_number'] ?? ''));
        if ($registrationNumber !== '') {
            $existingRegistration = Bank::query()
                ->where('registration_number', $registrationNumber)
                ->whereIn('status', $blockingStatuses)
                ->exists();

            if ($existingRegistration) {
                throw ValidationException::withMessages([
                    'registration_number' => 'A registration request for this bank is already under review or active.',
                ]);
            }
        }
    }

    private function storeVerificationDocument(Bank $bank, UploadedFile $file): BankVerificationDocument
    {
        $path = $file->store("bank-verifications/{$bank->id}", 'local');

        return BankVerificationDocument::query()->create([
            'bank_id' => $bank->id,
            'document_type' => 'registration',
            'original_name' => $file->getClientOriginalName(),
            'disk_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
            'status' => 'uploaded',
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'bank';
        $slug = $base;
        $i = 1;
        while (Bank::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function uniqueReferenceCode(): string
    {
        do {
            $code = 'BANK-REQ-'.strtoupper(Str::random(8));
        } while (Bank::query()->where('reference_code', $code)->exists());

        return $code;
    }
}
