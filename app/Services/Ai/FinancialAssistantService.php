<?php

namespace App\Services\Ai;

use App\Contracts\AiAssistantProvider;
use App\Models\User;
use App\Services\Ai\Providers\GeminiAssistantProvider;
use App\Support\MoneyMath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class FinancialAssistantService
{
    private const OUT_OF_SCOPE_REPLY = GeminiAssistantProvider::OUT_OF_SCOPE_REPLY;

    private const MUTATION_REFUSAL = 'As a security protocol, Campus Coin AI cannot modify your data or transactions directly from the chat.';

    private const UNAVAILABLE_REPLY = 'Campus Coin AI is temporarily unavailable. Your financial data and existing features are still available.';

    private const SOCIAL_OUT_OF_SCOPE_PATTERNS = [
        '/^\s*(hi|hello|hey|yo)\b[\s!.?]*$/i',
        '/^\s*what\'?s up\??\s*$/i',
        '/^\s*how are you\??\s*$/i',
        '/^\s*how\'?s it going\??\s*$/i',
        '/^\s*(good\s+)?(morning|afternoon|evening)\b[\s!.?]*$/i',
        '/^\s*(thanks|thank you|thx)\b[\s!.?]*$/i',
        '/^\s*tell me something\s*$/i',
        '/^\s*what is ai\??\s*$/i',
        '/^\s*what(\'s| is) your name\??\s*$/i',
        '/^\s*who are you\??\s*$/i',
    ];

    /** @var list<string> */
    private const OUT_OF_SCOPE_PATTERNS = [
        '/\bjoke\b/i',
        '/\bpoem\b/i',
        '/\bpoetry\b/i',
        '/\bweather\b/i',
        '/\bphysics\b/i',
        '/\bquantum\b/i',
        '/\bcelebrity\b/i',
        '/\bgossip\b/i',
        '/\bfootball\b/i',
        '/\bsoccer\b/i',
        '/\bwrite (me )?(an? )?(python|java|js|javascript|game|email|poem|story)\b/i',
        '/\bcode\b/i',
        '/\bprogramming\b/i',
        '/\bpolitic/i',
        '/\bsport\b/i',
        '/\bmedical\b/i',
        '/\blegal advice\b/i',
        '/\bstock\b/i',
        '/\bcrypto\b/i',
        '/\btrading\b/i',
        '/\binvestment\b/i',
        '/\bhack\b/i',
        '/\bstory\b/i',
        '/\belon musk\b/i',
        '/ignore (all|any) (previous|prior) instructions/i',
        '/show (another|other) user/i',
        '/reveal (your )?(system )?prompt/i',
        '/api[_ ]?key/i',
    ];

    /** @var list<string> */
    private const FOLLOW_UP_PATTERNS = [
        '/^\s*why\??\s*$/i',
        '/^\s*how much\??\s*$/i',
        '/^\s*what about\b/i',
        '/^\s*and\s+\w+\??\s*$/i',
        '/^\s*what should i reduce\??\s*$/i',
        '/^\s*can i afford (it|that)\??\s*$/i',
        '/^\s*tell me more\b/i',
        '/^\s*explain(\s+that)?\??\s*$/i',
        '/^\s*and\s+savings\??\s*$/i',
        '/^\s*what if i reduce\b/i',
        '/^\s*is this normal\??\s*$/i',
        '/^\s*compared with last month\??\s*$/i',
        '/^\s*where can i see (it|that)\??\s*$/i',
        '/^\s*show me( that| it)?\??\s*$/i',
    ];

    public function __construct(
        private readonly AiAssistantProvider $provider,
        private readonly FinancialContextBuilder $contextBuilder,
        private readonly IncomeAllocationPlanner $planner,
        private readonly CoachRecommendationEngine $coach,
        private readonly CampusCoinDashboardKnowledge $knowledge,
        private readonly AssistantDomainResolver $domainResolver,
    ) {}

    public function isAvailable(): bool
    {
        return (bool) config('ai.enabled', true) && $this->provider->isAvailable();
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public function suggestedPrompts(User $user): array
    {
        $context = $this->contextBuilder->forUser($user);
        $prompts = [];

        if ($context['insufficient_data']) {
            $prompts[] = ['id' => 'start', 'label' => 'How do I start tracking?'];
            $prompts[] = ['id' => 'help', 'label' => 'What can Campus Coin help me with?'];

            return $prompts;
        }

        $prompts[] = ['id' => 'where', 'label' => 'Where did my money go?'];
        $prompts[] = ['id' => 'save', 'label' => 'How can I save more?'];

        $income = MoneyMath::normalize($context['current_period']['income'] ?? 0);
        if (MoneyMath::compare($income, '0.00') > 0) {
            $prompts[] = ['id' => 'plan', 'label' => 'I received Rs '.$this->friendlyAmount($income).'. How should I plan it?'];
        } else {
            $prompts[] = ['id' => 'plan', 'label' => 'I received Rs 30,000. How should I plan it?'];
        }

        $top = $context['current_period']['top_named_category']
            ?? $context['current_period']['top_category']
            ?? null;
        if (is_string($top) && $top !== '' && strcasecmp($top, 'Uncategorized') !== 0) {
            $prompts[] = ['id' => 'reduce', 'label' => 'Why is my '.$top.' spending high?'];
        } else {
            $prompts[] = ['id' => 'reduce', 'label' => 'Which category should I reduce?'];
        }

        foreach ($context['budgets'] ?? [] as $budget) {
            if (! is_array($budget)) {
                continue;
            }
            $status = (string) ($budget['status'] ?? '');
            if (in_array($status, ['near_limit', 'over', 'exceeded', 'warning'], true)
                || ((float) ($budget['percentage'] ?? 0)) >= 80) {
                $prompts[] = ['id' => 'budget', 'label' => 'Which budget needs attention?'];
                break;
            }
        }

        if (MoneyMath::compare((string) ($context['savings']['goal'] ?? '0'), '0.00') > 0) {
            $prompts[] = ['id' => 'goal', 'label' => 'How can I move closer to my goal?'];
        }

        return array_slice($prompts, 0, 4);
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array<string, mixed>
     */
    public function ask(User $user, string $message, array $history = []): array
    {
        $message = trim(strip_tags($message));
        $history = $this->sanitizeHistory($history);
        $max = (int) config('ai.assistant_max_message_length', 500);

        if ($message === '') {
            return $this->responsePayload(
                inScope: true,
                answer: 'Ask about your spending, budgets, savings, or how to plan income.',
                facts: [],
                intent: 'empty',
                nextAction: 'Try a suggested question above or ask about this month\'s spending.',
            );
        }

        if (mb_strlen($message) > $max) {
            return $this->responsePayload(
                inScope: true,
                answer: 'Please keep your question under '.$max.' characters.',
                facts: [],
                intent: 'too_long',
                nextAction: 'Shorten your question and ask again.',
            );
        }

        if ($this->looksOutOfScope($message)) {
            return $this->responsePayload(
                inScope: false,
                answer: self::OUT_OF_SCOPE_REPLY,
                facts: [],
                intent: 'out_of_scope',
            );
        }

        if ($this->looksLikeMutation($message)) {
            return $this->responsePayload(
                inScope: true,
                answer: self::MUTATION_REFUSAL,
                facts: [],
                intent: 'mutation_refused',
                nextAction: 'Use the Transactions, Budgets, Recurring, or Settings pages to make changes manually.',
            );
        }

        $context = $this->contextBuilder->forUser($user);
        $recommendation = $this->coach->recommend($context);

        if ($feature = $this->answerFeatureHelp($message)) {
            return $feature;
        }

        if ($followUp = $this->resolveFollowUp($message, $history, $context, $recommendation)) {
            return $followUp;
        }

        $incoming = $this->extractIncomingAmount($message);
        $allocation = null;

        if ($incoming !== null) {
            $allocation = $this->planner->plan($user, $incoming, $context);
            if ($allocation['needs_clarification']) {
                return $this->responsePayload(
                    inScope: true,
                    answer: $allocation['clarifying_question'] ?? 'How much income did you receive?',
                    facts: ['Need the income amount to build a plan.'],
                    intent: 'allocation_clarify',
                    needsClarification: true,
                    clarifyingQuestion: $allocation['clarifying_question'],
                    nextAction: 'Reply with the income amount in rupees.',
                    responseType: 'income_plan',
                    links: $this->verifiedLinks('intelligence'),
                );
            }
        }

        if ($spend = $this->extractSpendCheckAmount($message)) {
            $available = MoneyMath::normalize($context['current_period']['available_balance'] ?? 0);
            if (MoneyMath::compare($spend, $available) > 0) {
                $shortfall = MoneyMath::sub($spend, $available);

                return $this->responsePayload(
                    inScope: true,
                    answer: 'You currently cannot cover a Rs '.$this->friendlyAmount($spend).' expense from your available balance. It would exceed your available balance by Rs '.$this->friendlyAmount($shortfall).'.',
                    facts: [
                        'Available balance: Rs '.$this->friendlyAmount($available),
                        'Requested amount: Rs '.$this->friendlyAmount($spend),
                        'Shortfall: Rs '.$this->friendlyAmount($shortfall),
                    ],
                    intent: 'balance_check',
                    nextAction: 'Review your current spending or budget before making the expense.',
                    responseType: 'financial_action',
                    links: $this->verifiedLinks('dashboard', 'budgets'),
                );
            }

            $remaining = MoneyMath::sub($available, $spend);

            return $this->responsePayload(
                inScope: true,
                answer: 'You can afford an additional Rs '.$this->friendlyAmount($spend).' expense based on your current available balance.',
                facts: [
                    'Available balance: Rs '.$this->friendlyAmount($available),
                    'Requested amount: Rs '.$this->friendlyAmount($spend),
                    'Remaining after the expense: Rs '.$this->friendlyAmount($remaining),
                ],
                intent: 'balance_check',
                nextAction: 'Before spending it, check whether any recurring commitments are due soon.',
                responseType: 'financial_action',
                links: $this->verifiedLinks('dashboard', 'recurring'),
            );
        }

        $resolved = $this->domainResolver->resolve($message);

        if ($resolved['domain'] === 'navigation' && is_string($resolved['page_id'])) {
            return $this->answerNavigation($resolved['page_id']);
        }

        if ($resolved['domain'] === 'feature_explain' && is_string($resolved['page_id'])) {
            return $this->answerFeatureExplain($resolved['page_id']);
        }

        if ($resolved['domain'] === 'balance') {
            return $this->answerBalance($context);
        }

        if ($resolved['domain'] === 'forecast') {
            return $this->answerForecastNav();
        }

        if ($resolved['domain'] === 'alerts') {
            return $this->answerAlertsNav();
        }

        if ($resolved['domain'] === 'category_focus') {
            $hint = $this->extractCategoryHint($message);
            if ($hint !== null) {
                return $this->answerCategoryFocus($context, $hint);
            }
        }

        $intent = $this->mapDomainToIntent($resolved);

        // Deterministic Campus Coin answers — authoritative even when Gemini is down.
        if ($intent !== null && $intent !== 'open') {
            $deterministic = $this->answerDeterministicIntent($intent, $context, $allocation, $incoming, $recommendation);
            if ($deterministic !== null) {
                // Optional single Gemini narration for coach/save intents when available.
                if ($this->isAvailable() && in_array($intent, ['coach_priority', 'save_more'], true)) {
                    $narrated = $this->tryGemini($user, $message, $context, $allocation, $incoming, $history, $recommendation, $resolved);
                    if ($narrated !== null && ($narrated['in_scope'] ?? true)) {
                        return $this->ensureLinks($narrated, $intent, $recommendation);
                    }
                }

                return $deterministic;
            }
        }

        if ($allocation !== null && $incoming !== null) {
            if ($this->isAvailable()) {
                $narrated = $this->tryGemini($user, $message, $context, $allocation, $incoming, $history, $recommendation, $resolved);
                if ($narrated !== null) {
                    return $this->ensureLinks($narrated, 'allocation', $recommendation);
                }
            }

            return $this->fallbackFromFacts($context, $allocation, $incoming, $recommendation);
        }

        if (! $this->isAvailable()) {
            // Open / unmatched NL without Gemini → coach recommendation (never a bare spending dump).
            return $this->fallbackFromFacts($context, null, null, $recommendation);
        }

        $rateKey = 'ai-assistant:'.$user->id;
        $limit = (int) config('ai.assistant_rate_limit_per_minute', 10);
        if (RateLimiter::tooManyAttempts($rateKey, $limit)) {
            return $this->answerCoachPriority($recommendation, 'rate_limited_coach');
        }
        RateLimiter::hit($rateKey, 60);

        $narrated = $this->tryGemini($user, $message, $context, $allocation, $incoming, $history, $recommendation, $resolved);
        if ($narrated !== null) {
            return $this->ensureLinks($narrated, $intent ?? 'open', $recommendation);
        }

        return $this->fallbackFromFacts($context, $allocation, $incoming, $recommendation);
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return list<array{role: string, content: string}>
     */
    private function sanitizeHistory(array $history): array
    {
        $clean = [];
        foreach (array_slice($history, -8) as $turn) {
            if (! is_array($turn)) {
                continue;
            }
            $role = (string) ($turn['role'] ?? '');
            if (! in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $content = mb_substr(trim(strip_tags((string) ($turn['content'] ?? ''))), 0, 800);
            if ($content === '') {
                continue;
            }
            $clean[] = ['role' => $role, 'content' => $content];
        }

        return $clean;
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $recommendation
     * @return array<string, mixed>|null
     */
    private function resolveFollowUp(string $message, array $history, array $context, array $recommendation): ?array
    {
        $isFollowUp = false;
        foreach (self::FOLLOW_UP_PATTERNS as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                $isFollowUp = true;
                break;
            }
        }

        if (! $isFollowUp && ! preg_match('/^\s*what about\s+.+/i', $message)) {
            return null;
        }

        $priorUser = null;
        $priorAssistant = null;
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if ($priorAssistant === null && $history[$i]['role'] === 'assistant') {
                $priorAssistant = $history[$i]['content'];
            }
            if ($priorUser === null && $history[$i]['role'] === 'user') {
                $priorUser = $history[$i]['content'];
            }
            if ($priorUser !== null && $priorAssistant !== null) {
                break;
            }
        }

        if ($priorUser === null && $priorAssistant === null) {
            return $this->responsePayload(
                inScope: true,
                answer: 'What would you like me to explain about your Campus Coin spending, budgets, or savings?',
                facts: [],
                intent: 'clarify',
                nextAction: 'Ask a full question such as “Where did my money go?” or “How can I save more?”',
                responseType: 'financial_follow_up',
            );
        }

        if (preg_match('/^\s*where can i see (it|that)\??\s*$/i', $message)
            || preg_match('/^\s*show me( that| it)?\??\s*$/i', $message)) {
            $priorResolved = $priorUser !== null
                ? $this->domainResolver->resolve($priorUser)
                : ['domain' => 'open', 'page_id' => null];
            $pageId = is_string($priorResolved['page_id'] ?? null)
                ? $priorResolved['page_id']
                : $this->defaultPageForIntent($this->mapDomainToIntent($priorResolved));
            if ($pageId !== null) {
                $page = $this->knowledge->byId($pageId);

                return $this->responsePayload(
                    inScope: true,
                    answer: $page !== null
                        ? 'You can see that on '.$page['label'].' ('.$page['path'].').'
                        : 'Open the related Campus Coin page from the link below.',
                    facts: [],
                    intent: 'navigation_follow_up',
                    nextAction: null,
                    responseType: 'financial_follow_up',
                    links: $this->verifiedLinks($pageId),
                );
            }
        }

        $priorIntent = $priorUser !== null ? $this->detectCampusIntent($priorUser) : 'open';

        if (preg_match('/^\s*why\??\s*$/i', $message)
            || preg_match('/^\s*tell me more\b/i', $message)
            || preg_match('/^\s*explain(\s+that)?\??\s*$/i', $message)
            || preg_match('/^\s*is this normal\??\s*$/i', $message)) {
            if (in_array($priorIntent, ['coach_priority', 'save_more', 'spending', 'open'], true)) {
                return $this->answerCoachWhy($recommendation, $context);
            }
            if ($priorIntent === 'budgets') {
                return $this->answerBudgets($context);
            }
            if ($priorIntent === 'trend' || $priorIntent === 'monthly_review' || preg_match('/^\s*compared with last month\??\s*$/i', $message)) {
                return $this->answerTrend($context);
            }
        }

        if (preg_match('/^\s*compared with last month\??\s*$/i', $message)) {
            return $this->answerTrend($context);
        }

        if (preg_match('/^\s*how much\??\s*$/i', $message)
            || preg_match('/^\s*what should i reduce\??\s*$/i', $message)
            || preg_match('/^\s*what if i reduce\b/i', $message)) {
            return $this->answerCoachHowMuch($recommendation);
        }

        if (preg_match('/^\s*and\s+savings\??\s*$/i', $message)) {
            return $this->answerSavingsGoal($context);
        }

        if (preg_match('/^\s*can i afford (it|that)\??\s*$/i', $message)) {
            $available = MoneyMath::normalize($context['current_period']['available_balance'] ?? 0);

            return $this->responsePayload(
                inScope: true,
                answer: 'Your current available balance is Rs '.$this->friendlyAmount($available).'. Tell me the amount you are considering and I can compare it for you.',
                facts: ['Available balance: Rs '.$this->friendlyAmount($available)],
                intent: 'balance_check',
                nextAction: 'Ask “Can I spend Rs X?” with a specific amount.',
                responseType: 'financial_follow_up',
                links: $this->verifiedLinks('dashboard'),
            );
        }

        if (preg_match('/^\s*what about\s+(.+?)\??\s*$/i', $message, $m) || preg_match('/^\s*and\s+(.+?)\??\s*$/i', $message, $m)) {
            $categoryHint = trim($m[1]);

            return $this->answerCategoryFocus($context, $categoryHint);
        }

        if ($priorIntent !== null && $priorIntent !== 'open') {
            return $this->answerDeterministicIntent($priorIntent, $context, null, null, $recommendation);
        }

        return $this->answerCoachPriority($recommendation);
    }

    /**
     * @param  array<string, mixed>  $recommendation
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function answerCoachWhy(array $recommendation, array $context): array
    {
        unset($context);
        $category = $recommendation['primary_category'] ?? null;
        $cut = $recommendation['suggested_cut_amount'] ?? null;
        $answer = $recommendation['why'];
        if (is_string($category) && $category !== '') {
            $amountFact = collect($recommendation['facts'] ?? [])
                ->first(fn ($f) => is_string($f) && str_starts_with($f, $category.':'));
            $answer .= ' '.$category.' is the focus of the current recommendation';
            if (is_string($amountFact)) {
                $answer .= ' ('.$amountFact.')';
            }
            $answer .= '.';
        }
        if (is_string($cut) && MoneyMath::compare($cut, '0.00') > 0) {
            $answer .= ' A practical reduction target is about Rs '.$this->friendlyAmount($cut).'.';
        }
        if (! empty($recommendation['data_quality_note'])) {
            $answer .= ' '.$recommendation['data_quality_note'];
        }

        return $this->responsePayload(
            inScope: true,
            answer: $answer,
            facts: $recommendation['facts'],
            intent: 'spending_why',
            nextAction: $recommendation['suggested_action'],
            responseType: 'financial_follow_up',
            links: $this->coachLinks($recommendation),
        );
    }

    /**
     * @param  array<string, mixed>  $recommendation
     * @return array<string, mixed>
     */
    private function answerCoachHowMuch(array $recommendation): array
    {
        $cut = $recommendation['suggested_cut_amount'] ?? null;
        $category = $recommendation['primary_category'] ?? 'that category';

        if (! is_string($cut) || MoneyMath::compare($cut, '0.00') <= 0) {
            return $this->answerCoachPriority($recommendation, 'coach_how_much');
        }

        return $this->responsePayload(
            inScope: true,
            answer: 'Aim to reduce '.$category.' by approximately Rs '.$this->friendlyAmount($cut).' based on your current Campus Coin spending pattern.',
            facts: $recommendation['facts'],
            intent: 'coach_how_much',
            nextAction: $recommendation['suggested_action'],
            responseType: 'financial_follow_up',
            links: $this->coachLinks($recommendation),
        );
    }

    /**
     * @param  array<string, mixed>  $recommendation
     * @return array<string, mixed>
     */
    private function answerCoachPriority(array $recommendation, string $intent = 'coach_priority'): array
    {
        return $this->responsePayload(
            inScope: true,
            answer: $recommendation['answer'],
            facts: $recommendation['facts'],
            intent: $intent,
            nextAction: $recommendation['suggested_action'],
            responseType: $recommendation['response_type'] ?? 'financial_action',
            links: $this->coachLinks($recommendation),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function answerCategoryFocus(array $context, string $hint): array
    {
        $hintLower = mb_strtolower($hint);
        foreach ($context['categories'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = (string) ($row['name'] ?? '');
            if ($name === '' || ! str_contains(mb_strtolower($name), $hintLower)) {
                continue;
            }

            $amount = MoneyMath::normalize($row['amount'] ?? 0);
            $pct = $row['percentage'] !== null ? number_format((float) $row['percentage'], 1).'%' : 'n/a';
            $count = (int) ($row['transaction_count'] ?? 0);
            $answer = $name.' spending this month is Rs '.$this->friendlyAmount($amount).' ('.$pct.' of expenses)';
            if ($count > 0) {
                $answer .= ' across '.$count.' transaction'.($count === 1 ? '' : 's');
            }
            $answer .= '.';

            if (($row['budget_limit'] ?? null) !== null) {
                $answer .= ' Budget: Rs '.$this->friendlyAmount((string) $row['budget_spent']).' of Rs '.$this->friendlyAmount((string) $row['budget_limit']).' used';
                if (($row['budget_status'] ?? null) === 'exceeded') {
                    $answer .= ', over by Rs '.$this->friendlyAmount((string) ($row['budget_over'] ?? '0'));
                } else {
                    $answer .= ', remaining Rs '.$this->friendlyAmount((string) ($row['budget_remaining'] ?? '0'));
                }
                $answer .= '.';
            }

            return $this->responsePayload(
                inScope: true,
                answer: $answer,
                facts: [
                    $name.': Rs '.$this->friendlyAmount($amount),
                    'Share of expenses: '.$pct,
                ],
                intent: 'category_focus',
                nextAction: 'Review '.$name.' on Transactions or set a budget on the Budgets page if you need a limit.',
                responseType: 'financial_explanation',
                links: $this->verifiedLinks('transactions', 'budgets'),
            );
        }

        return $this->responsePayload(
            inScope: true,
            answer: 'I could not find a matching category for “'.$hint.'” in your current-month Campus Coin data.',
            facts: ['Ask about a category that appears in your spending breakdown.'],
            intent: 'category_focus',
            nextAction: 'Ask “Where did my money go?” to see your named categories.',
            responseType: 'financial_explanation',
            links: $this->verifiedLinks('transactions'),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $allocation
     * @param  list<array{role: string, content: string}>  $history
     * @param  array<string, mixed>|null  $recommendation
     * @return array<string, mixed>|null
     */
    private function tryGemini(
        User $user,
        string $message,
        array $context,
        ?array $allocation,
        ?string $incoming,
        array $history = [],
        ?array $recommendation = null,
        ?array $resolved = null,
    ): ?array {
        try {
            $domains = array_values(array_filter([
                $resolved['domain'] ?? null,
                $resolved['page_id'] ?? null,
                'intelligence',
            ]));
            $raw = $this->provider->answer([
                'question' => $message,
                'conversation_history' => $history,
                'financial_context' => $context,
                'coach_recommendation' => $recommendation,
                'allocation_plan' => $allocation['plan'] ?? null,
                'incoming_amount' => $incoming,
                'dashboard_knowledge' => $this->knowledge->sliceForDomains($domains),
                'allowed_links' => $this->knowledge->allowedLinksPayload(),
            ]);

            $validated = $this->validateProviderResponse($raw, $allocation, $recommendation);
            if ($validated === null) {
                return null;
            }

            if ($allocation !== null) {
                $validated['allocation_plan'] = $allocation['plan'];
                $validated['facts_used'] = array_values(array_unique(array_merge(
                    $validated['facts_used'],
                    array_map(fn (string $b) => 'Based on '.$b, $allocation['basis']),
                )));
            }

            return $validated;
        } catch (Throwable $e) {
            Log::warning('ai.assistant.failed', [
                'error' => class_basename($e),
                'message' => mb_substr($e->getMessage(), 0, 120),
                'user_id' => $user->id,
            ]);

            return null;
        }
    }

    /**
     * Synonym-family intent detection — meaning clusters, not exact prompt strings.
     */
    private function detectCampusIntent(string $message): ?string
    {
        return $this->mapDomainToIntent($this->domainResolver->resolve($message));
    }

    /**
     * @param  array{domain: string, page_id: string|null, intent_hint?: string|null}  $resolved
     */
    private function mapDomainToIntent(array $resolved): string
    {
        return match ($resolved['domain']) {
            'coach' => ($resolved['intent_hint'] ?? '') === 'save_more' ? 'save_more' : 'coach_priority',
            'spending' => ($resolved['intent_hint'] ?? '') === 'spending_overall' ? 'spending_overall' : 'spending',
            'budgets' => 'budgets',
            'recurring' => 'recurring',
            'savings' => 'savings_goal',
            'monthly_review', 'trend' => 'trend',
            'category_focus' => 'category_focus',
            'balance' => 'balance',
            'navigation' => 'navigation',
            'feature_explain' => 'feature_explain',
            'forecast' => 'forecast',
            'alerts' => 'alerts',
            default => 'open',
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $allocation
     * @param  array<string, mixed>  $recommendation
     * @return array<string, mixed>|null
     */
    private function answerDeterministicIntent(
        string $intent,
        array $context,
        ?array $allocation,
        ?string $incoming,
        array $recommendation = [],
    ): ?array {
        return match ($intent) {
            'coach_priority' => $this->answerCoachPriority($recommendation !== [] ? $recommendation : $this->coach->recommend($context)),
            'save_more' => $this->answerSaveMore($context),
            'spending' => $this->answerSpending($context, preferNamed: true),
            'spending_overall' => $this->answerSpending($context, preferNamed: false),
            'budgets' => $this->answerBudgets($context),
            'recurring' => $this->answerRecurring($context),
            'savings_goal' => $this->answerSavingsGoal($context),
            'trend' => $this->answerTrend($context),
            'balance' => $this->answerBalance($context),
            'forecast' => $this->answerForecastNav(),
            'alerts' => $this->answerAlertsNav(),
            default => $allocation !== null ? $this->fallbackFromFacts($context, $allocation, $incoming, $recommendation) : null,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function answerSaveMore(array $context): array
    {
        if ($context['insufficient_data']) {
            return $this->responsePayload(
                inScope: true,
                answer: 'I don\'t have enough spending history yet to identify a reliable saving opportunity. Add a few categorized expenses and Campus Coin can analyze your actual categories.',
                facts: ['Insufficient categorized spending history this month'],
                intent: 'insufficient_data',
                nextAction: 'Add expenses with categories on the Transactions page.',
                links: $this->verifiedLinks('transactions'),
            );
        }

        $expenses = MoneyMath::normalize($context['current_period']['expenses'] ?? 0);
        $income = MoneyMath::normalize($context['current_period']['income'] ?? 0);
        $available = MoneyMath::normalize($context['current_period']['available_balance'] ?? 0);
        $goal = MoneyMath::normalize($context['savings']['goal'] ?? 0);
        $periodSavings = MoneyMath::normalize($context['savings']['period_savings'] ?? 0);
        $named = $this->namedCategories($context);
        $topNamed = $named[0] ?? null;
        $second = $named[1] ?? null;
        $hasUncategorized = (bool) ($context['current_period']['has_uncategorized'] ?? false);
        $uncatAmount = MoneyMath::normalize($context['current_period']['uncategorized_amount'] ?? 0);

        $answerParts = [];
        $facts = [
            'Current-month expenses: Rs '.$this->friendlyAmount($expenses),
            'Available balance: Rs '.$this->friendlyAmount($available),
        ];
        $suggested = 'Open Transactions or Budgets to apply the cuts you choose — chat will not change your data.';

        if ($topNamed !== null) {
            $pct = $topNamed['percentage'] !== null ? number_format((float) $topNamed['percentage'], 1).'%' : 'a large share';
            $answerParts[] = 'Your strongest saving opportunity is '.$topNamed['name'].'. It is currently your largest named expense category at Rs '.$this->friendlyAmount($topNamed['amount']).' ('.$pct.' of expenses), so reducing it would have the largest direct impact based on your current spending pattern.';
            $facts[] = $topNamed['name'].': Rs '.$this->friendlyAmount($topNamed['amount']);
            if ($pct !== 'a large share') {
                $facts[] = $topNamed['name'].' share of expenses: '.$pct;
            }
        } elseif ($hasUncategorized) {
            $answerParts[] = 'Much of your spending is still uncategorized (Rs '.$this->friendlyAmount($uncatAmount).'), so Campus Coin cannot yet pinpoint a named category to cut first.';
            $facts[] = 'Uncategorized expenses: Rs '.$this->friendlyAmount($uncatAmount);
            $suggested = 'Categorize recent Uncategorized expenses on the Transactions page, then ask again.';
        } else {
            $answerParts[] = 'Track a few more categorized expenses so Campus Coin can pinpoint a reduction target.';
        }

        $budgetTip = $this->budgetOpportunityLine($context);
        if ($budgetTip !== null) {
            $answerParts[] = $budgetTip['line'];
            $facts[] = $budgetTip['fact'];
        }

        if (MoneyMath::compare($goal, '0.00') > 0) {
            $answerParts[] = 'Your savings goal is Rs '.$this->friendlyAmount($goal).' and this month\'s period balance is Rs '.$this->friendlyAmount($periodSavings).'.';
            $facts[] = 'Savings goal: Rs '.$this->friendlyAmount($goal);
            $facts[] = 'Current-month period balance: Rs '.$this->friendlyAmount($periodSavings);
        } elseif (MoneyMath::compare($income, '0.00') > 0) {
            $answerParts[] = 'You have Rs '.$this->friendlyAmount($income).' income this month against Rs '.$this->friendlyAmount($expenses).' expenses.';
            $facts[] = 'Current-month income: Rs '.$this->friendlyAmount($income);
        }

        $cutPrimary = '0.00';
        $cutSecondary = '0.00';
        if ($topNamed !== null && MoneyMath::compare($topNamed['amount'], '0.00') > 0) {
            $cutPrimary = $this->suggestCut($topNamed['amount'], 15);
        }
        if ($second !== null && MoneyMath::compare($second['amount'], '0.00') > 0) {
            $cutSecondary = $this->suggestCut($second['amount'], 10);
        }
        $freed = MoneyMath::add($cutPrimary, $cutSecondary);

        if (MoneyMath::compare($freed, '0.00') > 0 && $topNamed !== null) {
            $suggested = 'Target a reduction of approximately Rs '.$this->friendlyAmount($cutPrimary).' in '.$topNamed['name'];
            if ($second !== null && MoneyMath::compare($cutSecondary, '0.00') > 0) {
                $suggested .= ' and Rs '.$this->friendlyAmount($cutSecondary).' in '.$second['name'];
            }
            $suggested .= ' this month, then redirect about Rs '.$this->friendlyAmount($freed).' toward your savings goal.';
        }

        if ($hasUncategorized && MoneyMath::compare($uncatAmount, '0.00') > 0 && $topNamed !== null) {
            $answerParts[] = 'You also have Rs '.$this->friendlyAmount($uncatAmount).' of Uncategorized spending — categorize it so future advice stays specific.';
            $facts[] = 'Uncategorized expenses: Rs '.$this->friendlyAmount($uncatAmount);
        }

        return $this->responsePayload(
            inScope: true,
            answer: implode(' ', $answerParts),
            facts: $facts,
            intent: 'save_more',
            nextAction: $suggested,
            links: $this->verifiedLinks('intelligence', 'transactions', 'budgets'),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function answerSpending(array $context, bool $preferNamed = true): array
    {
        if ($context['insufficient_data']) {
            return $this->responsePayload(
                inScope: true,
                answer: 'Campus Coin does not have spending data for this month yet. Add expenses with categories to see where your money goes.',
                facts: ['No expenses recorded for the current month'],
                intent: 'insufficient_data',
                nextAction: 'Add a few expenses with categories on the Transactions page.',
                links: $this->verifiedLinks('transactions'),
            );
        }

        $expenses = MoneyMath::normalize($context['current_period']['expenses'] ?? 0);
        $named = $this->namedCategories($context);
        $hasUncategorized = (bool) ($context['current_period']['has_uncategorized'] ?? false);
        $uncat = MoneyMath::normalize($context['current_period']['uncategorized_amount'] ?? 0);
        $facts = ['Current-month expenses: Rs '.$this->friendlyAmount($expenses)];

        $answer = 'This month you\'ve spent Rs '.$this->friendlyAmount($expenses).'.';

        if ($preferNamed && $named !== []) {
            $top = $named[0];
            $answer .= ' '.$top['name'].' is your largest named expense category at Rs '.$this->friendlyAmount($top['amount']).'.';
            $facts[] = $top['name'].': Rs '.$this->friendlyAmount($top['amount']);
            foreach (array_slice($named, 0, 5) as $row) {
                $pct = $row['percentage'] !== null ? number_format((float) $row['percentage'], 1).'%' : null;
                $facts[] = $row['name'].' — Rs '.$this->friendlyAmount($row['amount']).($pct ? ' — '.$pct : '');
            }
        } elseif (! $preferNamed) {
            $topOverall = $context['current_period']['top_category'] ?? null;
            $topAmount = MoneyMath::normalize($context['current_period']['top_category_amount'] ?? 0);
            if (is_string($topOverall) && $topOverall !== '') {
                $answer .= ' Your largest overall spending bucket is '.$topOverall.' at Rs '.$this->friendlyAmount($topAmount).'.';
                $facts[] = $topOverall.': Rs '.$this->friendlyAmount($topAmount);
            }
            foreach (array_slice($context['categories'] ?? [], 0, 5) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $pct = ($row['percentage'] ?? null) !== null ? number_format((float) $row['percentage'], 1).'%' : null;
                $facts[] = ($row['name'] ?? 'Category').' — Rs '.$this->friendlyAmount((string) ($row['amount'] ?? 0)).($pct ? ' — '.$pct : '');
            }
        }

        if ($hasUncategorized && MoneyMath::compare($uncat, '0.00') > 0) {
            $answer .= ' You also have Rs '.$this->friendlyAmount($uncat).' of uncategorized spending.';
            $facts[] = 'Uncategorized — Rs '.$this->friendlyAmount($uncat);
        }

        if ($named === [] && ! $hasUncategorized) {
            $answer .= ' No category breakdown is available yet.';
        }

        $next = $named !== []
            ? 'Review '.$named[0]['name'].' first because it is your largest named category'.($hasUncategorized ? ', and categorize uncategorized spending for clearer analysis' : '').'.'
            : 'Open Reports for charts and month comparisons.';

        return $this->responsePayload(
            inScope: true,
            answer: $answer,
            facts: array_values(array_unique($facts)),
            intent: 'spending',
            nextAction: $next,
            responseType: 'financial_explanation',
            links: $this->verifiedLinks('transactions', 'reports'),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function answerBudgets(array $context): array
    {
        $budgets = $context['budgets'] ?? [];
        if ($budgets === []) {
            return $this->responsePayload(
                inScope: true,
                answer: 'You do not have any budgets set for the current month yet. Create category budgets on the Budgets page to track limits vs spending.',
                facts: [],
                intent: 'budgets',
                nextAction: 'Open Budgets to add a monthly limit.',
                links: $this->verifiedLinks('budgets'),
            );
        }

        $lines = ['Here is your current Campus Coin budget status:'];
        $facts = [];
        $attention = [];

        foreach ($budgets as $budget) {
            if (! is_array($budget)) {
                continue;
            }
            $name = (string) ($budget['category'] ?? 'Category');
            $spent = MoneyMath::normalize($budget['spent'] ?? 0);
            $limit = MoneyMath::normalize($budget['limit'] ?? 0);
            $remaining = MoneyMath::normalize($budget['remaining'] ?? 0);
            $status = (string) ($budget['status_label'] ?? $budget['status'] ?? '');
            $lines[] = '• '.$name.': spent Rs '.$this->friendlyAmount($spent).' of Rs '.$this->friendlyAmount($limit).' ('.$status.'), remaining Rs '.$this->friendlyAmount($remaining).'.';
            $facts[] = $name.': Rs '.$this->friendlyAmount($spent).' / Rs '.$this->friendlyAmount($limit);

            if (in_array((string) ($budget['status'] ?? ''), ['near_limit', 'exceeded'], true)) {
                $attention[] = $name;
            }
        }

        if ($attention !== []) {
            $lines[] = 'Needs attention: '.implode(', ', $attention).'.';
        }

        return $this->responsePayload(
            inScope: true,
            answer: implode("\n", $lines),
            facts: $facts,
            intent: 'budgets',
            nextAction: 'Review flagged categories on the Budgets page.',
            links: $this->verifiedLinks('budgets'),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function answerRecurring(array $context): array
    {
        $upcoming = $context['recurring']['upcoming'] ?? [];
        $pending = $context['recurring']['pending_approvals'] ?? [];
        $committed = MoneyMath::normalize($context['recurring']['monthly_committed_expense'] ?? 0);

        if ($upcoming === [] && $pending === []) {
            return $this->responsePayload(
                inScope: true,
                answer: 'You do not have active recurring commitments right now. You can set allowances, rent, or subscriptions on the Recurring page.',
                facts: [],
                intent: 'recurring',
                nextAction: 'Open Recurring to add an allowance, rent, or subscription rule.',
                links: $this->verifiedLinks('recurring'),
            );
        }

        $lines = ['Your Campus Coin recurring picture:'];
        $facts = [];
        if (MoneyMath::compare($committed, '0.00') > 0) {
            $lines[] = 'Active expense commitments total about Rs '.$this->friendlyAmount($committed).' (next upcoming runs).';
            $facts[] = 'Committed recurring expenses: Rs '.$this->friendlyAmount($committed);
        }

        foreach (array_slice($upcoming, 0, 6) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cat = $row['category'] ? ' ('.$row['category'].')' : '';
            $lines[] = '• '.$row['description'].$cat.': Rs '.$this->friendlyAmount($row['amount']).' — next '.$row['next_run_date'];
        }

        if ($pending !== []) {
            $lines[] = count($pending).' occurrence(s) are waiting for your approval.';
        }

        return $this->responsePayload(
            inScope: true,
            answer: implode("\n", $lines),
            facts: $facts,
            intent: 'recurring',
            nextAction: 'Review pending approvals in Notifications or Recurring.',
            links: $this->verifiedLinks('recurring', 'notifications'),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function answerSavingsGoal(array $context): array
    {
        $goal = MoneyMath::normalize($context['savings']['goal'] ?? 0);
        $periodSavings = MoneyMath::normalize($context['savings']['period_savings'] ?? 0);
        $available = MoneyMath::normalize($context['savings']['available_balance'] ?? 0);

        if (MoneyMath::compare($goal, '0.00') <= 0) {
            return $this->responsePayload(
                inScope: true,
                answer: 'You have not set a savings goal yet. Add one in your profile/settings, then Campus Coin can track progress against it.',
                facts: ['Available balance: Rs '.$this->friendlyAmount($available)],
                intent: 'savings_goal',
                nextAction: 'Set a savings goal in Settings, then ask again about progress.',
                links: $this->verifiedLinks('savings_goal', 'settings'),
            );
        }

        $remaining = MoneyMath::compare($goal, $available) > 0
            ? MoneyMath::sub($goal, $available)
            : '0.00';

        $answer = 'Your savings goal is Rs '.$this->friendlyAmount($goal).'. ';
        $answer .= 'Available balance is Rs '.$this->friendlyAmount($available).'. ';
        $answer .= 'This month\'s period balance is Rs '.$this->friendlyAmount($periodSavings).'. ';
        if (MoneyMath::compare($remaining, '0.00') > 0) {
            $answer .= 'You still need about Rs '.$this->friendlyAmount($remaining).' of available funds to match that goal amount.';
        } else {
            $answer .= 'Your available balance already meets or exceeds that goal amount.';
        }

        return $this->responsePayload(
            inScope: true,
            answer: $answer,
            facts: [
                'Savings goal: Rs '.$this->friendlyAmount($goal),
                'Available balance: Rs '.$this->friendlyAmount($available),
            ],
            intent: 'savings_goal',
            nextAction: 'Ask “How can I save more?” for category-level cut ideas.',
            links: $this->verifiedLinks('savings_goal'),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function answerTrend(array $context): array
    {
        $previous = $context['history']['previous_month'] ?? null;
        $expense = MoneyMath::normalize($context['current_period']['expenses'] ?? 0);
        $income = MoneyMath::normalize($context['current_period']['income'] ?? 0);

        if (! is_array($previous)) {
            return $this->responsePayload(
                inScope: true,
                answer: 'Campus Coin does not have a previous-month comparison yet. After you track another month, I can compare income and expenses.',
                facts: [
                    'Current-month expenses: Rs '.$this->friendlyAmount($expense),
                    'Current-month income: Rs '.$this->friendlyAmount($income),
                ],
                intent: 'trend',
                nextAction: 'Keep tracking this month, then ask again after next month starts.',
                links: $this->verifiedLinks('monthly_review', 'reports'),
            );
        }

        $prevExpense = MoneyMath::normalize($previous['expense'] ?? 0);
        $delta = MoneyMath::sub($expense, $prevExpense);
        $direction = MoneyMath::compare($delta, '0.00') > 0
            ? 'higher'
            : (MoneyMath::compare($delta, '0.00') < 0 ? 'lower' : 'about the same as');

        $abs = MoneyMath::normalize(abs((float) $delta));
        $answer = 'This month expenses are Rs '.$this->friendlyAmount($expense)
            .' vs Rs '.$this->friendlyAmount($prevExpense).' last month — '
            .$direction.(MoneyMath::compare($abs, '0.00') > 0 ? ' by Rs '.$this->friendlyAmount($abs) : '').'.';

        $named = $this->namedCategories($context);
        if ($named !== []) {
            $answer .= ' Largest named category this month: '.$named[0]['name']
                .' (Rs '.$this->friendlyAmount($named[0]['amount']).').';
        }

        return $this->responsePayload(
            inScope: true,
            answer: $answer,
            facts: [
                'Current expenses: Rs '.$this->friendlyAmount($expense),
                'Previous expenses: Rs '.$this->friendlyAmount($prevExpense),
            ],
            intent: 'trend',
            nextAction: 'Open Monthly Review or Reports for charts and month-by-month comparisons.',
            links: $this->verifiedLinks('monthly_review', 'reports'),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function answerFeatureHelp(string $message): ?array
    {
        $map = [
            '/how does (my )?budget work|what does over budget mean/i' => [
                'intent' => 'feature_budget',
                'page' => 'budgets',
                'answer' => 'Budgets set a monthly spending limit per category. Campus Coin compares that limit with your categorized expenses for the month. Near limit means you have used most of the limit; over budget / exceeded means spent is above the limit. Remaining never goes negative in the UI — overrun is shown separately.',
            ],
            '/how does recurring work/i' => [
                'intent' => 'feature_recurring',
                'page' => 'recurring',
                'answer' => 'Recurring rules schedule expected income or expenses. When an occurrence is due, Campus Coin can ask you to approve it before it becomes a real transaction, so balance checks and ownership stay intact.',
            ],
            '/how does csv import work/i' => [
                'intent' => 'feature_csv',
                'page' => 'import',
                'answer' => 'CSV import lets you upload transactions, preview rows, optionally use AI category suggestions, then confirm. Import is idempotent for a session key and never bypasses ownership or balance safety on expenses.',
            ],
            '/how does ai categor/i' => [
                'intent' => 'feature_ai_category',
                'page' => 'transactions',
                'answer' => 'AI categorization suggests an expense category from the description. You choose Accept, Change, or Ignore — Campus Coin never auto-saves an AI category without your action.',
            ],
            '/what does (the )?forecast mean/i' => [
                'intent' => 'feature_forecast',
                'page' => 'month_forecast',
                'answer' => 'The next-month forecast estimates income and expenses from your recent completed months. It is explanatory and review-only — it does not change transactions.',
            ],
            '/unusual expense|spending alert/i' => [
                'intent' => 'feature_alert',
                'page' => 'spending_alerts',
                'answer' => 'Unusual expense alerts flag an expense that is much larger than your recent average in the same category (deterministic rules, not Gemini). Review them on Spending Alerts or Intelligence — Campus Coin never auto-deletes the transaction.',
            ],
        ];

        foreach ($map as $pattern => $payload) {
            if (preg_match($pattern, $message) === 1) {
                return $this->responsePayload(
                    inScope: true,
                    answer: $payload['answer'],
                    facts: ['Grounded in Campus Coin product behavior'],
                    intent: $payload['intent'],
                    nextAction: 'Open the related page in Campus Coin if you want to apply this, or ask about your own spending next.',
                    responseType: 'financial_explanation',
                    links: $this->verifiedLinks($payload['page']),
                );
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    private function namedCategories(array $context): array
    {
        $rows = [];
        foreach ($context['categories'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $categoryId = $row['category_id'] ?? null;
            if ($name === '' || strcasecmp($name, 'Uncategorized') === 0 || $categoryId === null) {
                continue;
            }
            $rows[] = $row;
        }

        usort($rows, fn (array $a, array $b) => MoneyMath::compare($b['amount'] ?? '0', $a['amount'] ?? '0'));

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{line: string, fact: string}|null
     */
    private function budgetOpportunityLine(array $context): ?array
    {
        foreach ($context['budgets'] ?? [] as $budget) {
            if (! is_array($budget)) {
                continue;
            }
            $status = (string) ($budget['status'] ?? '');
            $name = (string) ($budget['category'] ?? 'Category');
            $spent = MoneyMath::normalize($budget['spent'] ?? 0);
            $limit = MoneyMath::normalize($budget['limit'] ?? 0);
            $remaining = MoneyMath::normalize($budget['remaining'] ?? 0);

            if ($status === 'exceeded') {
                return [
                    'line' => $name.' is over budget (Rs '.$this->friendlyAmount($spent).' vs Rs '.$this->friendlyAmount($limit).').',
                    'fact' => $name.' over budget',
                ];
            }
            if ($status === 'near_limit') {
                return [
                    'line' => $name.' is near its limit with only Rs '.$this->friendlyAmount($remaining).' remaining.',
                    'fact' => $name.' near limit',
                ];
            }
        }

        return null;
    }

    private function suggestCut(string $amount, float $percent): string
    {
        $cut = MoneyMath::normalize(((float) MoneyMath::normalize($amount)) * ($percent / 100));
        if (MoneyMath::compare($cut, '100.00') < 0 && MoneyMath::compare($amount, '100.00') > 0) {
            return '100.00';
        }

        return $cut;
    }

    private function looksOutOfScope(string $message): bool
    {
        foreach (self::SOCIAL_OUT_OF_SCOPE_PATTERNS as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return true;
            }
        }

        foreach (self::OUT_OF_SCOPE_PATTERNS as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeMutation(string $message): bool
    {
        return (bool) preg_match(
            '/\b(create|add|delete|remove|update|edit|approve|transfer|set)\b.+\b(transaction|budget|recurring|category|goal|payment)\b/i',
            $message,
        );
    }

    private function extractIncomingAmount(string $message): ?string
    {
        if (! preg_match('/\b(received|got|earn(ed)?|income|allowance|plan|divide|allocate)\b/i', $message)) {
            if (! preg_match('/\breceived\b/i', $message) && ! preg_match('/how should i (plan|divide|allocate)/i', $message)) {
                return null;
            }
        }

        if (! preg_match('/(?:rs\.?|pkr|rupees?)?\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]+)?|[0-9]+(?:\.[0-9]+)?)\s*(?:rs\.?|pkr|rupees?)?/i', $message, $m)) {
            if (preg_match('/how should i (plan|divide|allocate)/i', $message)) {
                return '0.00';
            }

            return null;
        }

        return MoneyMath::normalize(str_replace(',', '', $m[1]));
    }

    private function extractSpendCheckAmount(string $message): ?string
    {
        if (! preg_match('/\b(can i (spend|afford)|afford to spend|spend)\b/i', $message)) {
            return null;
        }

        if (! preg_match('/(?:rs\.?|pkr)?\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]+)?|[0-9]+(?:\.[0-9]+)?)/i', $message, $m)) {
            return null;
        }

        return MoneyMath::normalize(str_replace(',', '', $m[1]));
    }

    /**
     * @param  array<string, mixed>|null  $raw
     * @param  array<string, mixed>|null  $allocation
     * @param  array<string, mixed>|null  $recommendation
     * @return array<string, mixed>|null
     */
    private function validateProviderResponse(?array $raw, ?array $allocation, ?array $recommendation = null): ?array
    {
        if ($raw === null) {
            return null;
        }

        $inScope = (bool) ($raw['in_scope'] ?? true);
        $answer = trim(strip_tags((string) ($raw['answer'] ?? '')));
        $answer = $this->stripInventedUrls($answer);
        $intent = mb_substr(trim(strip_tags((string) ($raw['intent'] ?? 'general'))), 0, 64);
        $responseType = mb_substr(trim(strip_tags((string) ($raw['response_type'] ?? 'financial_action'))), 0, 64);

        if ($answer === '') {
            return null;
        }

        if (! $inScope || $responseType === 'general_out_of_context') {
            return $this->responsePayload(
                inScope: false,
                answer: self::OUT_OF_SCOPE_REPLY,
                facts: [],
                intent: 'out_of_scope',
                responseType: 'general_out_of_context',
            );
        }

        $facts = [];
        foreach ($raw['facts_used'] ?? [] as $fact) {
            if (! is_string($fact)) {
                continue;
            }
            $clean = mb_substr(trim(strip_tags($fact)), 0, 200);
            if ($clean !== '') {
                $facts[] = $clean;
            }
        }

        // Prefer authoritative coach facts when present.
        if (is_array($recommendation) && ($recommendation['facts'] ?? []) !== []) {
            $facts = array_values(array_unique(array_merge($recommendation['facts'], $facts)));
        }

        $plan = $allocation['plan'] ?? null;
        $nextAction = isset($raw['next_action'])
            ? mb_substr(trim(strip_tags((string) $raw['next_action'])), 0, 300)
            : '';

        if ($nextAction === '' && is_array($recommendation)) {
            $nextAction = (string) ($recommendation['suggested_action'] ?? '');
        }

        if ($nextAction === '') {
            $nextAction = $allocation !== null
                ? 'Review the suggested plan, then record transactions yourself when ready.'
                : 'Open Reports for more detail.';
        }

        return $this->responsePayload(
            inScope: true,
            answer: mb_substr($answer, 0, 2000),
            facts: array_slice($facts, 0, 8),
            intent: $intent,
            allocation: $plan,
            nextAction: $nextAction,
            needsClarification: (bool) ($raw['needs_clarification'] ?? false),
            clarifyingQuestion: isset($raw['clarifying_question'])
                ? mb_substr(trim(strip_tags((string) $raw['clarifying_question'])), 0, 300)
                : null,
            disclaimer: isset($raw['disclaimer'])
                ? mb_substr(trim(strip_tags((string) $raw['disclaimer'])), 0, 300)
                : null,
            whyPlan: $allocation['basis'] ?? null,
            allocationTotal: $allocation['total'] ?? null,
            responseType: $responseType !== '' ? $responseType : 'financial_action',
            links: $this->filterVerifiedLinkList(is_array($raw['links'] ?? null) ? $raw['links'] : []),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $allocation
     * @param  array<string, mixed>|null  $recommendation
     * @return array<string, mixed>
     */
    private function fallbackFromFacts(array $context, ?array $allocation, ?string $incoming, ?array $recommendation = null): array
    {
        if ($allocation !== null && $incoming !== null) {
            $lines = ['Suggested plan for Rs '.$this->friendlyAmount($incoming).':'];
            foreach ($allocation['plan'] as $row) {
                $lines[] = $row['category'].': Rs '.$this->friendlyAmount($row['amount']);
            }

            return $this->responsePayload(
                inScope: true,
                answer: implode("\n", $lines),
                facts: array_map(fn (string $b) => 'Based on '.$b, $allocation['basis']),
                intent: 'allocation',
                allocation: $allocation['plan'],
                nextAction: 'Review the suggested plan, then record transactions yourself when ready.',
                whyPlan: $allocation['basis'],
                allocationTotal: $allocation['total'],
                responseType: 'income_plan',
                links: $this->verifiedLinks('intelligence', 'transactions'),
            );
        }

        // Open / Gemini-failure path: prioritized coach action — never a default spending dump.
        $rec = $recommendation ?? $this->coach->recommend($context);

        return $this->answerCoachPriority($rec);
    }

    /**
     * @param  list<string>  $facts
     * @param  list<array{category: string, amount: string, reason: string}>|null  $allocation
     * @param  list<string>|null  $whyPlan
     * @param  list<array{label: string, href: string}>  $links
     * @return array<string, mixed>
     */
    private function responsePayload(
        bool $inScope,
        string $answer,
        array $facts,
        string $intent,
        ?array $allocation = null,
        ?string $nextAction = null,
        bool $needsClarification = false,
        ?string $clarifyingQuestion = null,
        ?string $disclaimer = null,
        ?string $error = null,
        ?array $whyPlan = null,
        ?string $allocationTotal = null,
        ?string $responseType = null,
        array $links = [],
    ): array {
        return [
            'intent' => $intent,
            'response_type' => $responseType ?? ($inScope ? 'financial_action' : 'general_out_of_context'),
            'in_scope' => $inScope,
            'answer' => $answer,
            'facts_used' => $facts,
            'allocation_plan' => $allocation,
            'allocation_total' => $allocationTotal,
            'why_this_plan' => $whyPlan,
            'next_action' => $nextAction,
            'needs_clarification' => $needsClarification,
            'clarifying_question' => $clarifyingQuestion,
            'disclaimer' => $disclaimer,
            'error' => $error,
            'links' => $inScope ? array_values($links) : [],
            'provider' => $this->provider->name(),
            'model' => $this->provider->model(),
        ];
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    private function verifiedLinks(string ...$pageIds): array
    {
        $links = [];
        $seen = [];
        foreach ($pageIds as $pageId) {
            if ($pageId === '' || isset($seen[$pageId])) {
                continue;
            }
            $link = $this->knowledge->link($pageId);
            if ($link === null) {
                continue;
            }
            $seen[$pageId] = true;
            $links[] = [
                'label' => $link['label'],
                'href' => $link['href'],
            ];
        }

        return $links;
    }

    /**
     * @param  array<string, mixed>  $recommendation
     * @return list<array{label: string, href: string}>
     */
    private function coachLinks(array $recommendation): array
    {
        $page = is_string($recommendation['suggested_page'] ?? null)
            ? $recommendation['suggested_page']
            : 'intelligence';

        if ($page === '' || $page === 'intelligence') {
            return $this->verifiedLinks('intelligence');
        }

        return $this->verifiedLinks('intelligence', $page);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $recommendation
     * @return array<string, mixed>
     */
    private function ensureLinks(array $payload, string $intent, ?array $recommendation = null): array
    {
        $payload['answer'] = $this->stripInventedUrls((string) ($payload['answer'] ?? ''));
        if (! empty($payload['links']) && is_array($payload['links'])) {
            $payload['links'] = $this->filterVerifiedLinkList($payload['links']);
            if ($payload['links'] !== []) {
                return $payload;
            }
        }

        if (in_array($intent, ['coach_priority', 'save_more', 'rate_limited_coach', 'open'], true) && is_array($recommendation)) {
            $payload['links'] = $this->coachLinks($recommendation);

            return $payload;
        }

        $page = $this->defaultPageForIntent($intent);
        $payload['links'] = $page !== null ? $this->verifiedLinks($page) : [];

        return $payload;
    }

    /**
     * @param  list<mixed>  $links
     * @return list<array{label: string, href: string}>
     */
    private function filterVerifiedLinkList(array $links): array
    {
        $allowed = [];
        foreach ($this->knowledge->all() as $page) {
            $allowed[$page['path']] = $page['id'];
        }

        $out = [];
        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }
            $href = (string) ($link['href'] ?? '');
            if (! isset($allowed[$href])) {
                continue;
            }
            $verified = $this->knowledge->link($allowed[$href]);
            if ($verified === null) {
                continue;
            }
            $out[] = [
                'label' => is_string($link['label'] ?? null) && $link['label'] !== ''
                    ? mb_substr(strip_tags($link['label']), 0, 80)
                    : $verified['label'],
                'href' => $verified['href'],
            ];
        }

        return $out;
    }

    private function stripInventedUrls(string $answer): string
    {
        $answer = preg_replace('#https?://\S+#i', '', $answer) ?? $answer;
        $answer = preg_replace(
            '#(?<![\w])/(?:dashboard|transactions|budgets|categories|recurring-transactions|reports|intelligence|insights|monthly-review|month-forecast|savings-goal|spending-alerts|duplicate-review|bookmarks|notifications|settings)(?:/[\w-]*)?#i',
            '',
            $answer,
        ) ?? $answer;

        return trim(preg_replace('/\s{2,}/', ' ', $answer) ?? $answer);
    }

    private function defaultPageForIntent(string $intent): ?string
    {
        return match ($intent) {
            'balance', 'balance_check' => 'dashboard',
            'spending', 'category_focus', 'spending_overall' => 'transactions',
            'budgets', 'feature_budget' => 'budgets',
            'recurring', 'feature_recurring' => 'recurring',
            'savings_goal' => 'savings_goal',
            'trend', 'monthly_review' => 'monthly_review',
            'forecast', 'feature_forecast' => 'month_forecast',
            'alerts', 'feature_alert' => 'spending_alerts',
            'coach_priority', 'save_more', 'allocation', 'income_plan', 'open', 'rate_limited_coach' => 'intelligence',
            'navigation', 'feature_explain' => null,
            default => 'intelligence',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function answerNavigation(string $pageId): array
    {
        $page = $this->knowledge->byId($pageId);
        if ($page === null) {
            return $this->responsePayload(
                inScope: true,
                answer: 'I could not find that Campus Coin page. Try asking about Dashboard, Transactions, Budgets, or Intelligence.',
                facts: [],
                intent: 'navigation',
                nextAction: null,
                responseType: 'financial_explanation',
                links: $this->verifiedLinks('dashboard'),
            );
        }

        return $this->responsePayload(
            inScope: true,
            answer: $page['label'].' is at '.$page['path'].'. '.$page['purpose'],
            facts: [],
            intent: 'navigation',
            nextAction: null,
            responseType: 'financial_explanation',
            links: $this->verifiedLinks($pageId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function answerFeatureExplain(string $pageId): array
    {
        $page = $this->knowledge->byId($pageId);
        if ($page === null) {
            return $this->answerNavigation('intelligence');
        }

        $sections = $page['sections'] !== []
            ? ' Key areas: '.implode(', ', $page['sections']).'.'
            : '';

        return $this->responsePayload(
            inScope: true,
            answer: $page['purpose'].$sections,
            facts: [],
            intent: 'feature_explain',
            nextAction: null,
            responseType: 'financial_explanation',
            links: $this->verifiedLinks($pageId),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function answerBalance(array $context): array
    {
        $available = MoneyMath::normalize($context['current_period']['available_balance'] ?? 0);

        return $this->responsePayload(
            inScope: true,
            answer: 'Your available balance is Rs '.$this->friendlyAmount($available).'.',
            facts: ['Available balance: Rs '.$this->friendlyAmount($available)],
            intent: 'balance',
            nextAction: null,
            responseType: 'financial_explanation',
            links: $this->verifiedLinks('dashboard'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function answerForecastNav(): array
    {
        $page = $this->knowledge->byId('month_forecast');

        return $this->responsePayload(
            inScope: true,
            answer: $page !== null
                ? $page['purpose'].' Open Month Forecast to review pace and projected month totals.'
                : 'Open Month Forecast to review your pace-based projection.',
            facts: [],
            intent: 'forecast',
            nextAction: null,
            responseType: 'financial_explanation',
            links: $this->verifiedLinks('month_forecast'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function answerAlertsNav(): array
    {
        return $this->responsePayload(
            inScope: true,
            answer: 'Unusual expense alerts appear on Spending Alerts. Review flagged expenses there — Campus Coin never auto-deletes them.',
            facts: [],
            intent: 'alerts',
            nextAction: null,
            responseType: 'financial_explanation',
            links: $this->verifiedLinks('spending_alerts'),
        );
    }

    private function extractCategoryHint(string $message): ?string
    {
        if (preg_match('/\b(?:spent|spend|spending|cost|kharch).*?\b(?:on|in)\s+([A-Za-z][A-Za-z\s]{1,30})/i', $message, $m)) {
            return trim($m[1], " \t\n\r\0\x0B?.!");
        }
        if (preg_match('/\b(Food|Transport|Entertainment|Academics|Hostel|Rent|Utilities|Shopping|Health|Personal)\b/i', $message, $m)) {
            return $m[1];
        }

        return null;
    }

    private function friendlyAmount(string $amount): string
    {
        $normalized = MoneyMath::normalize($amount);
        if (str_ends_with($normalized, '.00')) {
            return number_format((float) $normalized, 0, '.', ',');
        }

        return number_format((float) $normalized, 2, '.', ',');
    }
}
