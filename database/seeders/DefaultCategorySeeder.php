<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\Category;
use Illuminate\Database\Seeder;

class DefaultCategorySeeder extends Seeder
{
    /**
     * Idempotent seed of SRS default categories.
     */
    public function run(): void
    {
        $defaults = [
            TransactionType::Income->value => [
                'Allowance',
                'Part-time Job',
                'Scholarship',
                'Gift',
                'Other Income',
                'Loan',
            ],
            TransactionType::Expense->value => [
                'Food',
                'Transport',
                'Hostel/Rent',
                'Academics',
                'Subscriptions',
                'Entertainment',
                'Miscellaneous',
                'Loan Repayment',
            ],
        ];

        foreach ($defaults as $type => $names) {
            foreach ($names as $name) {
                $category = Category::query()->firstOrNew([
                    'user_id' => null,
                    'type' => $type,
                    'name' => $name,
                ]);

                $category->is_default = true;
                $category->save();
            }
        }
    }
}
