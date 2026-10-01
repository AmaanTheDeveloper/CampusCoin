<?php

namespace App\Services\Ai\Providers;

use App\Contracts\AiAssistantProvider;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GeminiAssistantProvider implements AiAssistantProvider
{
    public const OUT_OF_SCOPE_REPLY = 'I\'m sorry, that is out of context. I can only assist you with questions related to your Campus Coin spending, budgets, savings, or income planning.';

    public function __construct(
        private readonly GeminiClient $client = new GeminiClient,
    ) {}

    public function isAvailable(): bool
    {
        return $this->client->isConfigured();
    }

    public function name(): string
    {
        return 'gemini';
    }

    public function model(): ?string
    {
        return $this->client->model();
    }

    public function answer(array $payload): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $system = <<<'PROMPT'
You are Campus Coin AI — the Student Dashboard Assistant and personal financial coach for authenticated university students inside Campus Coin.
You are NOT a general-purpose social chatbot.

AUTHORITY ORDER: Campus Coin data → deterministic coach_recommendation → dashboard_knowledge → your natural explanation.
Never invent balances, amounts, categories, budgets, goals, percentages, cut amounts, routes, or page URLs.
If coach_recommendation is provided, explain and prioritize THAT recommendation. Do not invent a competing plan.
Use only numbers that appear in financial_context, coach_recommendation, or allocation_plan.
Use only page paths/labels from dashboard_knowledge or allowed_links. Never invent URLs.

SCOPE: spending, categories, income, available balance, budgets, savings, trends, recurring, alerts, forecasts, reports, income allocation, Campus Coin student dashboard feature/navigation explanations.
READ-ONLY: never claim you mutated transactions, budgets, recurring rules, goals, or settings.

CATEGORIES:
- Prefer named categories from financial_context / coach_recommendation.primary_category.
- Uncategorized is a data-quality bucket, not a normal recommendation target.
- Never treat Uncategorized as the "largest named category."

DOMAIN GATE:
- Greetings, small talk, jokes, poems, weather, trivia, coding, politics, sports, name/identity chat, or unrelated chat → in_scope=false and answer EXACTLY out_of_scope_reply.
- Do not force financial advice onto social messages ("How are you?", "Hello", "Thanks", "What is your name?").

FOLLOW-UPS: Use conversation_history + live financial_context + coach_recommendation for "Why?", "How much?", "Tell me more", "What about Food?", etc.

TONE: Neutral and supportive. Never shame the user.

CONCISENESS:
- Factual and navigation answers: 1–3 short sentences.
- Coach/recommendation answers: still concise; prioritize the coach_recommendation.

RESPONSE CONTRACT (JSON — do not embed ANSWER/BASED ON/SUGGESTED ACTION labels inside answer):
- response_type: financial_action | financial_explanation | financial_follow_up | income_plan | general_out_of_context
- answer: conversational reply only — do not paste raw URLs; the app attaches verified links separately
- facts_used: short facts copied/paraphrased from provided Campus Coin data (no invented figures)
- next_action: ONE concrete step when in_scope=true (required); empty/null only when in_scope=false
PROMPT;

        $userPayload = json_encode([
            'question' => $payload['question'] ?? '',
            'conversation_history' => $payload['conversation_history'] ?? [],
            'financial_context' => $payload['financial_context'] ?? [],
            'coach_recommendation' => $payload['coach_recommendation'] ?? null,
            'allocation_plan' => $payload['allocation_plan'] ?? null,
            'incoming_amount' => $payload['incoming_amount'] ?? null,
            'dashboard_knowledge' => $payload['dashboard_knowledge'] ?? [],
            'allowed_links' => $payload['allowed_links'] ?? [],
            'out_of_scope_reply' => self::OUT_OF_SCOPE_REPLY,
        ], JSON_THROW_ON_ERROR);

        $schema = [
            'type' => 'object',
            'properties' => [
                'intent' => ['type' => 'string'],
                'response_type' => [
                    'type' => 'string',
                    'enum' => [
                        'financial_action',
                        'financial_explanation',
                        'financial_follow_up',
                        'income_plan',
                        'general_out_of_context',
                    ],
                ],
                'in_scope' => ['type' => 'boolean'],
                'answer' => ['type' => 'string'],
                'facts_used' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'allocation_plan' => [
                    'type' => 'array',
                    'nullable' => true,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'category' => ['type' => 'string'],
                            'amount' => ['type' => 'string'],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['category', 'amount', 'reason'],
                    ],
                ],
                'next_action' => ['type' => 'string', 'nullable' => true],
                'needs_clarification' => ['type' => 'boolean'],
                'clarifying_question' => ['type' => 'string', 'nullable' => true],
                'disclaimer' => ['type' => 'string', 'nullable' => true],
            ],
            'required' => [
                'intent',
                'response_type',
                'in_scope',
                'answer',
                'facts_used',
                'needs_clarification',
                'next_action',
            ],
        ];

        try {
            return $this->client->generateJson($system, $userPayload, $schema, 0.2, 'ai.assistant.gemini');
        } catch (Throwable $e) {
            Log::warning('ai.assistant.gemini.failure', [
                'error' => class_basename($e),
            ]);

            throw new RuntimeException('Campus Coin AI failed.', 0, $e);
        }
    }
}
