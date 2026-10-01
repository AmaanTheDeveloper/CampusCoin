<?php

namespace App\Enums;

enum AcademicYear: string
{
    case Year1 = 'year_1';
    case Year2 = 'year_2';
    case Year3 = 'year_3';
    case Year4 = 'year_4';
    case Graduate = 'graduate';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Year1 => 'Year 1',
            self::Year2 => 'Year 2',
            self::Year3 => 'Year 3',
            self::Year4 => 'Year 4',
            self::Graduate => 'Graduate',
            self::Other => 'Other',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $year) => ['value' => $year->value, 'label' => $year->label()],
            self::cases()
        );
    }
}
