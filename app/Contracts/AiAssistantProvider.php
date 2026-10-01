<?php

namespace App\Contracts;

/**
 * Campus Coin Financial Assistant — narrative layer only.
 *
 * @phpstan-type AssistantRequest array{
 *     question: string,
 *     financial_context: array<string, mixed>,
 *     allocation_plan: list<array{category: string, amount: string, reason: string}>|null,
 *     incoming_amount: string|null
 * }
 * @phpstan-type AssistantResponse array{
 *     intent: string,
 *     in_scope: bool,
 *     answer: string,
 *     facts_used: list<string>,
 *     allocation_plan: list<array{category: string, amount: string, reason: string}>|null,
 *     next_action: string|null,
 *     needs_clarification: bool,
 *     clarifying_question: string|null,
 *     disclaimer: string|null
 * }
 */
interface AiAssistantProvider
{
    /**
     * @param  AssistantRequest  $payload
     * @return AssistantResponse|null
     */
    public function answer(array $payload): ?array;

    public function isAvailable(): bool;

    public function name(): string;

    public function model(): ?string;
}
