<?php

use App\Services\Loans\RepaymentScheduleService;
use App\Support\MoneyMath;

test('zero interest schedule splits principal evenly', function () {
    $service = new RepaymentScheduleService;
    $rows = $service->buildSchedule('1200.00', 4, '0.00', new DateTimeImmutable('2026-01-01'));

    expect($rows)->toHaveCount(4);
    expect($rows[0]['amount_due'])->toBe('300.00');
    expect($rows[0]['interest_component'])->toBe('0.00');
    expect($rows[3]['due_date'])->toBe('2026-05-01');

    $principalTotal = '0.00';
    foreach ($rows as $row) {
        $principalTotal = MoneyMath::add($principalTotal, $row['principal_component']);
    }
    expect($principalTotal)->toBe('1200.00');
});

test('interest-bearing schedule keeps positive installments and clears balance', function () {
    $service = new RepaymentScheduleService;
    $rows = $service->buildSchedule('100000.00', 6, '12.00', new DateTimeImmutable('2026-01-15'));

    expect($rows)->toHaveCount(6);

    foreach ($rows as $row) {
        expect(MoneyMath::compare($row['amount_due'], '0.00'))->toBe(1);
        expect(MoneyMath::compare($row['principal_component'], '0.00'))->toBeGreaterThanOrEqual(0);
    }

    $principalTotal = '0.00';
    foreach ($rows as $row) {
        $principalTotal = MoneyMath::add($principalTotal, $row['principal_component']);
    }
    expect(MoneyMath::compare($principalTotal, '100000.00'))->toBe(0);
});

test('installment amount is deterministic for same inputs', function () {
    $service = new RepaymentScheduleService;
    $monthly = $service->installmentAmount('50000.00', 12, MoneyMath::normalize(bcdiv('12', '1200', 8)));
    $again = $service->installmentAmount('50000.00', 12, MoneyMath::normalize(bcdiv('12', '1200', 8)));

    expect($monthly)->toBe($again);
    expect(MoneyMath::compare($monthly, '0.00'))->toBe(1);
});
