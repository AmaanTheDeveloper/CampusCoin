<?php

namespace App\Contracts;

/**
 * @phpstan-type InsightContext array{
 *     period: string,
 *     period_label: string,
 *     income: string,
 *     expense: string,
 *     balance: string,
 *     savings_rate: float|null,
 *     top_categories: list<array{name: string, amount: string, percentage: float|null}>,
 *     budget_status: list<array{category: string, limit: string, spent: string, percentage: float, status: string}>,
 *     previous_month: array{income: string, expense: string, balance: string}|null,
 *     insufficient_data: bool
 * }
 */
interface AiInsightProvider
{
    /**
     * @param  InsightContext  $context
     * @return array{headline: string, summary: string, trends: list<array{label: string, direction: string, significance: string}>, actions: list<string>}|null
     */
    public function generateMonthlyInsight(array $context): ?array;

    public function isAvailable(): bool;

    public function name(): string;

    public function model(): ?string;
}
