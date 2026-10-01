<?php

namespace App\Models;

use App\Enums\AcademicYear;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * Intentionally excludes `role` to prevent privilege escalation via mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'academic_year',
        'monthly_allowance',
        'savings_goal',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'student',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'academic_year' => AcademicYear::class,
            'monthly_allowance' => 'decimal:2',
            'savings_goal' => 'decimal:2',
            'disabled_at' => 'datetime',
        ];
    }

    public function isStudent(): bool
    {
        return $this->role === Role::Student;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isBankUser(): bool
    {
        return $this->role === Role::Bank;
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function isActiveAccount(): bool
    {
        return ! $this->isDisabled();
    }

    /**
     * @return HasMany<BankMembership, $this>
     */
    public function bankMemberships(): HasMany
    {
        return $this->hasMany(BankMembership::class);
    }

    /**
     * @return BelongsToMany<Bank, $this>
     */
    public function banks(): BelongsToMany
    {
        return $this->belongsToMany(Bank::class, 'bank_memberships')
            ->withPivot(['staff_role', 'is_active'])
            ->withTimestamps();
    }

    public function primaryBank(): ?Bank
    {
        return $this->banks()->wherePivot('is_active', true)->first();
    }

    /**
     * @return HasMany<LoanApplication, $this>
     */
    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Long-term savings goals (tracker). Distinct from profile savings_goal.
     *
     * @return HasMany<SavingsGoal, $this>
     */
    public function savingsGoals(): HasMany
    {
        return $this->hasMany(SavingsGoal::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<RecurringTransaction, $this>
     */
    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    /**
     * @return HasMany<Budget, $this>
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * @return HasMany<AiMonthlyInsight, $this>
     */
    public function monthlyInsights(): HasMany
    {
        return $this->hasMany(AiMonthlyInsight::class);
    }

    /**
     * @return HasMany<SavingTip, $this>
     */
    public function savingTips(): HasMany
    {
        return $this->hasMany(SavingTip::class);
    }

    /**
     * @return HasMany<Bookmark, $this>
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    /**
     * @return HasMany<TransactionActivity, $this>
     */
    public function transactionActivities(): HasMany
    {
        return $this->hasMany(TransactionActivity::class);
    }

    /**
     * @return HasMany<TransactionSignal, $this>
     */
    public function transactionSignals(): HasMany
    {
        return $this->hasMany(TransactionSignal::class);
    }
}
