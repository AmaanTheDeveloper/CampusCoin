<?php

namespace App\Enums;

enum ForecastConfidence: string
{
    case Insufficient = 'insufficient';
    case Low = 'low';
    case Moderate = 'moderate';

    public function label(): string
    {
        return match ($this) {
            self::Insufficient => 'Insufficient data',
            self::Low => 'Low confidence',
            self::Moderate => 'Moderate confidence',
        };
    }
}
