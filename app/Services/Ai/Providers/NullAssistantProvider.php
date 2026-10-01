<?php

namespace App\Services\Ai\Providers;

use App\Contracts\AiAssistantProvider;

class NullAssistantProvider implements AiAssistantProvider
{
    public function answer(array $payload): ?array
    {
        return null;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'null';
    }

    public function model(): ?string
    {
        return null;
    }
}
