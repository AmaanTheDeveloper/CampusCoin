<?php

namespace App\Services\Ai\Providers;

use App\Contracts\AiInsightProvider;
use Throwable;

class FakeInsightProvider implements AiInsightProvider
{
    /** @var array{headline: string, summary: string, trends: list<array{label: string, direction: string, significance: string}>, actions: list<string>}|null|callable|null */
    public mixed $nextInsight = null;

    public bool $available = true;

    public int $calls = 0;

    public ?Throwable $throwOnGenerate = null;

    public function generateMonthlyInsight(array $context): ?array
    {
        $this->calls++;

        if ($this->throwOnGenerate !== null) {
            throw $this->throwOnGenerate;
        }

        if (is_callable($this->nextInsight)) {
            return ($this->nextInsight)($context);
        }

        return $this->nextInsight;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function name(): string
    {
        return 'fake';
    }

    public function model(): ?string
    {
        return 'fake-model';
    }
}
