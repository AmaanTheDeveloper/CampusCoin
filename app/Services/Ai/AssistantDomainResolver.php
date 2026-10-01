<?php

namespace App\Services\Ai;

/**
 * Semantic domain families for Campus Coin assistant (synonym clusters, not exact prompt menus).
 */
class AssistantDomainResolver
{
    public function __construct(
        private readonly CampusCoinDashboardKnowledge $knowledge,
    ) {}

    /**
     * @return array{domain: string, page_id: string|null, intent_hint: string|null}
     */
    public function resolve(string $message): array
    {
        $message = trim($message);

        // Pure navigation / "where is X" before money intents.
        if ($this->looksLikeNavigation($message)) {
            $pageId = $this->knowledge->resolveNavPage($message);
            if ($pageId !== null) {
                return ['domain' => 'navigation', 'page_id' => $pageId, 'intent_hint' => 'navigation'];
            }
        }

        if (preg_match('/\b(what (is|does|can i (do|see|check)|happens) on|what can (the|i (do on|use))|explain (the )?(page|feature))\b/i', $message)
            || preg_match('/\bwhat can i (do|check|see) on (the )?intelligence\b/i', $message)) {
            $pageId = $this->knowledge->resolveNavPage($message) ?? 'intelligence';

            return ['domain' => 'feature_explain', 'page_id' => $pageId, 'intent_hint' => 'feature_explain'];
        }

        if (preg_match('/\b(available balance|my balance|how much (money )?do i have|how much (do i )?have left|what do i have available|kitna balance|mera balance)\b/i', $message)) {
            return ['domain' => 'balance', 'page_id' => 'dashboard', 'intent_hint' => 'balance'];
        }

        if (preg_match('/\b(over budget|budget left|budget remaining|which budget|how is my budget|budgets?)\b/i', $message)) {
            return ['domain' => 'budgets', 'page_id' => 'budgets', 'intent_hint' => 'budgets'];
        }

        if (preg_match('/\b(recurring|subscription|committed|upcoming (payment|bill)|what\'?s coming up)\b/i', $message)) {
            return ['domain' => 'recurring', 'page_id' => 'recurring', 'intent_hint' => 'recurring'];
        }

        if (preg_match('/\b(savings goal|how (much|far).*(saved|goal)|how much have i saved|kitna save)\b/i', $message)) {
            return ['domain' => 'savings', 'page_id' => 'savings_goal', 'intent_hint' => 'savings_goal'];
        }

        if (preg_match('/\b(how was (my )?month|how did i do (this )?month|what changed( this month)?|monthly review)\b/i', $message)) {
            return ['domain' => 'monthly_review', 'page_id' => 'monthly_review', 'intent_hint' => 'trend'];
        }

        if (preg_match('/\b(month forecast|forecast|pace|next month ka)\b/i', $message)) {
            return ['domain' => 'forecast', 'page_id' => 'month_forecast', 'intent_hint' => 'forecast'];
        }

        if (preg_match('/\b(spending alert|unusual (expense|spending))\b/i', $message)) {
            return ['domain' => 'alerts', 'page_id' => 'spending_alerts', 'intent_hint' => 'alerts'];
        }

        if (preg_match('/\b(report|analytics|charts?)\b/i', $message) && preg_match('/\b(where|see|view|open|show|find)\b/i', $message)) {
            return ['domain' => 'navigation', 'page_id' => 'reports', 'intent_hint' => 'navigation'];
        }

        if (preg_match('/\b(save more|how can i save|cut spending|reduce spending|where can i cut|cut back|cut down|lower my expenses)\b/i', $message)) {
            return ['domain' => 'coach', 'page_id' => 'intelligence', 'intent_hint' => 'save_more'];
        }

        if (preg_match('/\b(what can i do|what should i do|what should i focus|where should i start|what needs (my )?attention|how (can|do) i improve|spending too much|overspending|what should i (keep aside|prioritize|protect|change)|putting pressure|be more careful|am i (okay|doing okay) financially|is my spending okay|can i improve)\b/i', $message)) {
            return ['domain' => 'coach', 'page_id' => 'intelligence', 'intent_hint' => 'coach_priority'];
        }

        if (preg_match('/\b(how much.*(spent|spend).*(on|in)\s+(\w+)|(\w+)\s+(pe|par)\s+kitna|spent on\s+(\w+))\b/i', $message)
            || preg_match('/\b(food|transport|entertainment|academics).*(spend|spent|cost|kharch)/i', $message)
            || preg_match('/\b(spend|spent|cost|kharch).*(food|transport|entertainment|academics)\b/i', $message)) {
            return ['domain' => 'category_focus', 'page_id' => 'transactions', 'intent_hint' => 'category_focus'];
        }

        if (preg_match('/\b(overall|all spending|largest overall)\b.+\b(categor|expense)/i', $message)
            || preg_match('/\blargest overall\b/i', $message)) {
            return ['domain' => 'spending', 'page_id' => 'transactions', 'intent_hint' => 'spending_overall'];
        }

        if (preg_match('/\b(where did (my|the) money go|biggest expense|spent the most|top categor|largest categor|what did i spend|where is most of my money|losing the most money|how much (have i|did i) spend|show (me )?my spending)\b/i', $message)) {
            return ['domain' => 'spending', 'page_id' => 'transactions', 'intent_hint' => 'spending'];
        }

        if (preg_match('/\b(compare|last month|vs previous|trend)\b/i', $message)) {
            return ['domain' => 'monthly_review', 'page_id' => 'monthly_review', 'intent_hint' => 'trend'];
        }

        // Fallback navigation if a page alias is clearly mentioned.
        $pageId = $this->knowledge->resolveNavPage($message);
        if ($pageId !== null && preg_match('/\b(where|see|view|open|show|find|check|go to|take me)\b/i', $message)) {
            return ['domain' => 'navigation', 'page_id' => $pageId, 'intent_hint' => 'navigation'];
        }

        return ['domain' => 'open', 'page_id' => null, 'intent_hint' => 'open'];
    }

    private function looksLikeNavigation(string $message): bool
    {
        return (bool) preg_match(
            '/\b(where|kidhar|show|open|find|view|see|go to|take me|can i (see|check|find)|how do i (open|see|find))\b/i',
            $message,
        );
    }
}
