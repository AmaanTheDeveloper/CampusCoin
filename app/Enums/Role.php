<?php

namespace App\Enums;

enum Role: string
{
    case Student = 'student';
    case Admin = 'admin';
    case Bank = 'bank';

    public static function default(): self
    {
        return self::Student;
    }

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
            self::Student => 'Student',
            self::Admin => 'Administrator',
            self::Bank => 'Bank staff',
        };
    }
}
