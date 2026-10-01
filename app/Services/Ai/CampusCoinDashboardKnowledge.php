<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Route;

/**
 * Application-controlled student dashboard knowledge map.
 * Paths and route names must match the real Campus Coin student app — never invent routes.
 */
class CampusCoinDashboardKnowledge
{
    /**
     * @return list<array{
     *     id: string,
     *     route_name: string,
     *     path: string,
     *     label: string,
     *     purpose: string,
     *     sections: list<string>,
     *     aliases: list<string>,
     *     domains: list<string>
     * }>
     */
    public function all(): array
    {
        return [
            [
                'id' => 'dashboard',
                'route_name' => 'dashboard',
                'path' => '/dashboard',
                'label' => 'Dashboard',
                'purpose' => 'Home financial snapshot: available balance, income, expenses, charts, alerts, and month overview.',
                'sections' => ['balance', 'income', 'expenses', 'savings summary', 'charts', 'recent transactions', 'budget progress'],
                'aliases' => ['dashboard', 'home', 'overview', 'main page', 'command center'],
                'domains' => ['dashboard', 'balance', 'navigation'],
            ],
            [
                'id' => 'transactions',
                'route_name' => 'transactions.index',
                'path' => '/transactions',
                'label' => 'Transactions',
                'purpose' => 'View, filter, and manage income and expense history.',
                'sections' => ['transaction list', 'filters', 'search', 'archive', 'add transaction'],
                'aliases' => ['transactions', 'transaction history', 'spending history', 'expenses list', 'income list'],
                'domains' => ['transactions', 'spending', 'navigation'],
            ],
            [
                'id' => 'budgets',
                'route_name' => 'budgets.index',
                'path' => '/budgets',
                'label' => 'Budgets',
                'purpose' => 'Category monthly limits with spent, remaining, and status.',
                'sections' => ['budget cards', 'progress', 'limits', 'month filter'],
                'aliases' => ['budgets', 'budget', 'budget limits', 'spending limits'],
                'domains' => ['budgets', 'navigation'],
            ],
            [
                'id' => 'categories',
                'route_name' => 'categories.index',
                'path' => '/categories',
                'label' => 'Categories',
                'purpose' => 'System and personal income/expense categories.',
                'sections' => ['income categories', 'expense categories', 'personal categories'],
                'aliases' => ['categories', 'category list'],
                'domains' => ['categories', 'navigation'],
            ],
            [
                'id' => 'recurring',
                'route_name' => 'recurring-transactions.index',
                'path' => '/recurring-transactions',
                'label' => 'Recurring',
                'purpose' => 'Recurring income/expense rules and pending occurrence approvals.',
                'sections' => ['recurring rules', 'pending approvals', 'pause/resume'],
                'aliases' => ['recurring', 'subscriptions', 'commitments', 'recurring payments', 'upcoming payments'],
                'domains' => ['recurring', 'navigation'],
            ],
            [
                'id' => 'reports',
                'route_name' => 'reports.index',
                'path' => '/reports',
                'label' => 'Reports',
                'purpose' => 'Spending analytics, charts, filters, and export.',
                'sections' => ['summary metrics', 'category charts', 'export PDF'],
                'aliases' => ['reports', 'report', 'analytics', 'charts'],
                'domains' => ['reports', 'navigation'],
            ],
            [
                'id' => 'intelligence',
                'route_name' => 'intelligence.index',
                'path' => '/intelligence',
                'label' => 'Intelligence',
                'purpose' => 'Financial signals, income plans, forecasts, and Campus Coin AI coach.',
                'sections' => ['suggested plan', 'campus coin ai', 'signals', 'forecast snapshot'],
                'aliases' => ['intelligence', 'financial intelligence', 'ai coach', 'insights hub'],
                'domains' => ['intelligence', 'coach', 'navigation'],
            ],
            [
                'id' => 'insights',
                'route_name' => 'insights.index',
                'path' => '/insights',
                'label' => 'Insights',
                'purpose' => 'Monthly AI insights history and regenerate.',
                'sections' => ['current insight', 'history', 'bookmarks'],
                'aliases' => ['insights', 'monthly insights'],
                'domains' => ['insights', 'navigation'],
            ],
            [
                'id' => 'monthly_review',
                'route_name' => 'monthly-review.index',
                'path' => '/monthly-review',
                'label' => 'Monthly Review',
                'purpose' => 'Month performance story, comparisons, top category, and next actions.',
                'sections' => ['summary', 'comparison', 'budgets', 'trend', 'actions'],
                'aliases' => ['monthly review', 'month review', 'how was my month', 'month summary'],
                'domains' => ['monthly_review', 'trend', 'navigation'],
            ],
            [
                'id' => 'month_forecast',
                'route_name' => 'month-forecast.index',
                'path' => '/month-forecast',
                'label' => 'Month Forecast',
                'purpose' => 'Pace-based projection for the rest of the month.',
                'sections' => ['pace', 'projected expenses', 'projected savings'],
                'aliases' => ['month forecast', 'forecast', 'projection', 'pace'],
                'domains' => ['forecast', 'navigation'],
            ],
            [
                'id' => 'savings_goal',
                'route_name' => 'savings-goal.index',
                'path' => '/savings-goal',
                'label' => 'Savings Goal',
                'purpose' => 'Long-term savings goal progress and contributions.',
                'sections' => ['goal amount', 'progress', 'contributions'],
                'aliases' => ['savings goal', 'savings', 'goal', 'save goal'],
                'domains' => ['savings', 'navigation'],
            ],
            [
                'id' => 'spending_alerts',
                'route_name' => 'spending-alerts.index',
                'path' => '/spending-alerts',
                'label' => 'Spending Alerts',
                'purpose' => 'Unusual expense alerts inbox.',
                'sections' => ['unread alerts', 'earlier alerts'],
                'aliases' => ['spending alerts', 'unusual spending', 'alerts'],
                'domains' => ['alerts', 'navigation'],
            ],
            [
                'id' => 'duplicate_review',
                'route_name' => 'duplicate-review.index',
                'path' => '/duplicate-review',
                'label' => 'Duplicate Review',
                'purpose' => 'Review and resolve possible duplicate transactions.',
                'sections' => ['pending duplicates', 'resolved'],
                'aliases' => ['duplicates', 'duplicate review', 'duplicate transactions'],
                'domains' => ['duplicates', 'navigation'],
            ],
            [
                'id' => 'bookmarks',
                'route_name' => 'bookmarks.index',
                'path' => '/bookmarks',
                'label' => 'Bookmarks',
                'purpose' => 'Saved insights and tips.',
                'sections' => ['bookmark list'],
                'aliases' => ['bookmarks', 'saved insights'],
                'domains' => ['bookmarks', 'navigation'],
            ],
            [
                'id' => 'notifications',
                'route_name' => 'notifications.index',
                'path' => '/notifications',
                'label' => 'Notifications',
                'purpose' => 'In-app notification inbox including income plans and recurring approvals.',
                'sections' => ['notification list', 'mark as read'],
                'aliases' => ['notifications', 'notification inbox', 'alerts inbox'],
                'domains' => ['notifications', 'navigation'],
            ],
            [
                'id' => 'settings',
                'route_name' => 'profile.edit',
                'path' => '/settings/profile',
                'label' => 'Settings',
                'purpose' => 'Profile, academic year, allowance, savings goal preference, password, appearance.',
                'sections' => ['profile', 'password', 'appearance'],
                'aliases' => ['settings', 'profile', 'account settings'],
                'domains' => ['settings', 'navigation'],
            ],
            [
                'id' => 'import',
                'route_name' => 'transactions.import.create',
                'path' => '/transactions/import',
                'label' => 'Import CSV',
                'purpose' => 'Upload and preview CSV transactions before confirming.',
                'sections' => ['upload', 'preview'],
                'aliases' => ['import', 'csv import', 'import csv'],
                'domains' => ['import', 'navigation'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function byId(string $id): ?array
    {
        foreach ($this->all() as $page) {
            if ($page['id'] === $id) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forDomain(string $domain): ?array
    {
        foreach ($this->all() as $page) {
            if (in_array($domain, $page['domains'], true)) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $domains
     * @return list<array{id: string, path: string, label: string, purpose: string}>
     */
    public function sliceForDomains(array $domains): array
    {
        $out = [];
        $seen = [];
        foreach ($domains as $domain) {
            $page = $this->forDomain($domain) ?? $this->byId($domain);
            if ($page === null || isset($seen[$page['id']])) {
                continue;
            }
            $seen[$page['id']] = true;
            $out[] = [
                'id' => $page['id'],
                'path' => $page['path'],
                'label' => $page['label'],
                'purpose' => $page['purpose'],
            ];
        }

        return $out;
    }

    /**
     * @return array{label: string, href: string, route: string}|null
     */
    public function link(string $pageId): ?array
    {
        $page = $this->byId($pageId);
        if ($page === null) {
            return null;
        }

        if (! Route::has($page['route_name'])) {
            return null;
        }

        return [
            'label' => 'Open '.$page['label'],
            'href' => $page['path'],
            'route' => $page['route_name'],
        ];
    }

    /**
     * Resolve a navigation target page id from a natural-language message.
     */
    public function resolveNavPage(string $message): ?string
    {
        $lower = mb_strtolower($message);
        $bestId = null;
        $bestLen = 0;

        foreach ($this->all() as $page) {
            foreach ($page['aliases'] as $alias) {
                $aliasLower = mb_strtolower($alias);
                if ($aliasLower !== '' && str_contains($lower, $aliasLower) && mb_strlen($aliasLower) > $bestLen) {
                    $bestId = $page['id'];
                    $bestLen = mb_strlen($aliasLower);
                }
            }
        }

        return $bestId;
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    public function allowedLinksPayload(): array
    {
        $links = [];
        foreach ($this->all() as $page) {
            $link = $this->link($page['id']);
            if ($link !== null) {
                $links[] = ['label' => $link['label'], 'href' => $link['href']];
            }
        }

        return $links;
    }
}
