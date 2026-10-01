<?php

namespace App\Services\Loans;

use App\Support\MoneyMath;

/**
 * Deterministic repayment schedule calculator (equal installment / reducing balance approximation).
 */
class RepaymentScheduleService
{
    /**
     * @return list<array{
     *   installment_number: int,
     *   due_date: string,
     *   amount_due: string,
     *   principal_component: string,
     *   interest_component: string
     * }>
     */
    public function buildSchedule(
        string $principal,
        int $tenureMonths,
        string $annualRatePercent,
        ?\DateTimeInterface $startDate = null,
    ): array {
        $principal = MoneyMath::normalize($principal);
        $rate = MoneyMath::normalize($annualRatePercent);
        $start = $startDate ? \Carbon\Carbon::instance(\DateTimeImmutable::createFromInterface($startDate)) : now();

        if ($tenureMonths < 1) {
            return [];
        }

        $monthlyRate = $this->monthlyRate($rate);
        $installment = $this->installmentAmount($principal, $tenureMonths, $monthlyRate);
        $balance = $principal;
        $rows = [];

        for ($i = 1; $i <= $tenureMonths; $i++) {
            $interest = $this->multiply($balance, $monthlyRate);
            $principalPart = MoneyMath::sub($installment, $interest);

            if ($i === $tenureMonths) {
                $principalPart = $balance;
                $installment = MoneyMath::add($principalPart, $interest);
            }

            if (MoneyMath::compare($principalPart, $balance) === 1) {
                $principalPart = $balance;
                $installment = MoneyMath::add($principalPart, $interest);
            }

            $rows[] = [
                'installment_number' => $i,
                'due_date' => $start->copy()->addMonthsNoOverflow($i)->toDateString(),
                'amount_due' => MoneyMath::normalize($installment),
                'principal_component' => MoneyMath::normalize($principalPart),
                'interest_component' => MoneyMath::normalize($interest),
            ];

            $balance = MoneyMath::sub($balance, $principalPart);
            if (MoneyMath::compare($balance, '0.00') < 0) {
                $balance = '0.00';
            }
        }

        return $rows;
    }

    public function installmentAmount(string $principal, int $tenureMonths, string $monthlyRate): string
    {
        $principal = MoneyMath::normalize($principal);

        if (MoneyMath::compare($monthlyRate, '0.00') === 0) {
            return MoneyMath::normalize($this->divide($principal, (string) $tenureMonths));
        }

        // P * r * (1+r)^n / ((1+r)^n - 1)
        $onePlus = MoneyMath::add('1', $monthlyRate);
        $pow = $this->pow($onePlus, $tenureMonths);
        $numerator = $this->multiply($this->multiply($principal, $monthlyRate), $pow);
        $denominator = MoneyMath::sub($pow, '1');

        if (MoneyMath::compare($denominator, '0.00') === 0) {
            return MoneyMath::normalize($this->divide($principal, (string) $tenureMonths));
        }

        return MoneyMath::normalize($this->divide($numerator, $denominator));
    }

    public function totalRepayable(string $installmentAmount, int $tenureMonths): string
    {
        $total = '0.00';
        for ($i = 0; $i < $tenureMonths; $i++) {
            $total = MoneyMath::add($total, $installmentAmount);
        }

        return $total;
    }

    private function monthlyRate(string $annualPercent): string
    {
        $annual = MoneyMath::normalize($annualPercent);
        if (MoneyMath::compare($annual, '0.00') === 0) {
            return '0.00';
        }

        return MoneyMath::normalize($this->divide($annual, '1200'));
    }

    private function multiply(string $left, string $right): string
    {
        $left = MoneyMath::normalize($left);
        $right = MoneyMath::normalize($right);

        if (function_exists('bcmul')) {
            return MoneyMath::normalize(bcmul($left, $right, 8));
        }

        return MoneyMath::normalize((float) $left * (float) $right);
    }

    private function divide(string $left, string $right): string
    {
        $left = MoneyMath::normalize($left);
        $right = MoneyMath::normalize($right);

        if (MoneyMath::compare($right, '0.00') === 0) {
            return '0.00';
        }

        if (function_exists('bcdiv')) {
            return MoneyMath::normalize(bcdiv($left, $right, 8));
        }

        return MoneyMath::normalize((float) $left / (float) $right);
    }

    private function pow(string $base, int $exp): string
    {
        $result = '1';
        for ($i = 0; $i < $exp; $i++) {
            $result = $this->multiply($result, $base);
        }

        return $result;
    }
}
