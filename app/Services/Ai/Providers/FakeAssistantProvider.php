<?php

namespace App\Services\Ai\Providers;

use App\Contracts\AiAssistantProvider;
use Throwable;

/**
 * Test double — never calls a remote provider.
 */
class FakeAssistantProvider implements AiAssistantProvider
{
    /** @var array<string, mixed>|null|callable|null */
    public mixed $nextAnswer = null;

    public bool $available = true;

    public int $calls = 0;

    public ?Throwable $throwOnAnswer = null;

    /** @var array<string, mixed>|null */
    public ?array $lastPayload = null;

    public function answer(array $payload): ?array
    {
        $this->calls++;
        $this->lastPayload = $payload;

        if ($this->throwOnAnswer !== null) {
            throw $this->throwOnAnswer;
        }

        if (is_callable($this->nextAnswer)) {
            return ($this->nextAnswer)($payload);
        }

        return $this->nextAnswer;
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
        return 'fake-assistant';
    }
}
