<?php

namespace App\Enums;

enum BudgetStatus: string
{
    case Healthy = 'healthy';
    case NearLimit = 'near_limit';
    case Exceeded = 'exceeded';

    /**
     * Implementation choice (not an SRS literal): near-limit starts at 80%.
     */
    public const NEAR_LIMIT_THRESHOLD = 80.0;

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::NearLimit => 'Near Limit',
            self::Exceeded => 'Exceeded',
        };
    }

    public static function fromPercentage(float $percentage): self
    {
        if ($percentage >= 100) {
            return self::Exceeded;
        }

        if ($percentage >= self::NEAR_LIMIT_THRESHOLD) {
            return self::NearLimit;
        }

        return self::Healthy;
    }
}
