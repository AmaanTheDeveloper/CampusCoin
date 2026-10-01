import { FintechPanel } from '@/components/fintech-page';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import {
    AssistantRuntimeProvider,
    ComposerPrimitive,
    MessagePrimitive,
    ThreadPrimitive,
    getExternalStoreMessages,
    useAui,
    useAuiState,
    useExternalStoreRuntime,
    type AppendMessage,
    type ThreadMessageLike,
} from '@assistant-ui/react';
import { Link } from '@inertiajs/react';
import { Loader2, Sparkles } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';

export type AssistantPrompt = {
    id: string;
    label: string;
};

export type AssistantAllocationRow = {
    category: string;
    amount: string;
    reason: string;
};

export type AssistantLink = {
    label: string;
    href: string;
};

export type AssistantResponse = {
    intent: string;
    response_type?: string;
    in_scope: boolean;
    answer: string;
    facts_used: string[];
    allocation_plan: AssistantAllocationRow[] | null;
    allocation_total?: string | null;
    why_this_plan?: string[] | null;
    next_action: string | null;
    needs_clarification: boolean;
    clarifying_question: string | null;
    disclaimer: string | null;
    error: string | null;
    links?: AssistantLink[];
};

export type AssistantMeta = {
    available: boolean;
    suggested_prompts: AssistantPrompt[];
};

type CcMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    response?: AssistantResponse;
};

type Props = {
    assistant: AssistantMeta;
};

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function turnId(): string {
    return `${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}

function convertMessage(message: CcMessage): ThreadMessageLike {
    return {
        id: message.id,
        role: message.role,
        content: [{ type: 'text', text: message.content }],
        metadata: message.response
            ? {
                  custom: {
                      campusCoin: message.response,
                  },
              }
            : undefined,
    };
}

function historyForRequest(messages: CcMessage[]): Array<{ role: 'user' | 'assistant'; content: string }> {
    return messages
        .filter((m) => m.content.trim() !== '')
        .map((m) => ({ role: m.role, content: m.content }))
        .slice(-8);
}

function extractText(message: AppendMessage): string {
    const part = message.content.find((p) => p.type === 'text');
    return part && part.type === 'text' ? part.text.trim() : '';
}

export function CampusCoinAssistant({ assistant }: Props) {
    const [messages, setMessages] = useState<CcMessage[]>([]);
    const [isRunning, setIsRunning] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);
    const messagesRef = useRef(messages);
    messagesRef.current = messages;

    const onNew = useCallback(async (message: AppendMessage) => {
        const trimmed = extractText(message);
        if (!trimmed) {
            return;
        }

        const history = historyForRequest(messagesRef.current);
        const userId = turnId();
        setMessages((prev) => [...prev, { id: userId, role: 'user', content: trimmed }]);
        setIsRunning(true);
        setFormError(null);

        try {
            const res = await fetch(route('intelligence.assistant.ask'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message: trimmed, history }),
            });

            const data = (await res.json()) as { ok?: boolean; response?: AssistantResponse; message?: string };

            if (!res.ok || !data.response) {
                setFormError(
                    data.message ?? 'Campus Coin AI is temporarily unavailable. Your financial data and existing features are still available.',
                );
                return;
            }

            setMessages((prev) => [
                ...prev,
                {
                    id: turnId(),
                    role: 'assistant',
                    content: data.response!.answer,
                    response: data.response!,
                },
            ]);
        } catch {
            setFormError('Campus Coin AI is temporarily unavailable. Your financial data and existing features are still available.');
        } finally {
            setIsRunning(false);
        }
    }, []);

    const runtime = useExternalStoreRuntime({
        isRunning,
        messages,
        convertMessage,
        onNew,
    });

    return (
        <FintechPanel className="cc-elev-2" aria-labelledby="campus-coin-ai-heading">
            <div className="mb-4 flex items-start gap-3">
                <div className="bg-primary/15 text-primary flex size-11 shrink-0 items-center justify-center rounded-lg">
                    <Sparkles className="size-5" aria-hidden />
                </div>
                <div className="min-w-0">
                    <p className="cc-label">Campus Coin AI</p>
                    <h2 id="campus-coin-ai-heading" className="text-lg font-semibold text-white">
                        Your personal financial coach
                    </h2>
                    <p className="cc-text-secondary mt-1 text-sm">
                        Ask naturally about spending, budgets, or savings. Campus Coin never changes your data from chat.
                    </p>
                </div>
            </div>

            <AssistantRuntimeProvider runtime={runtime}>
                {assistant.suggested_prompts.length > 0 ? <SuggestedPrompts prompts={assistant.suggested_prompts} /> : null}
                <CampusCoinThread available={assistant.available} />
            </AssistantRuntimeProvider>

            {formError ? (
                <div role="alert" className="mt-4 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-3 text-sm text-red-100">
                    {formError}
                </div>
            ) : null}
        </FintechPanel>
    );
}

function SuggestedPrompts({ prompts }: { prompts: AssistantPrompt[] }) {
    const aui = useAui();
    const isRunning = useAuiState((s) => s.thread.isRunning);

    return (
        <div className="mb-4 flex flex-wrap gap-2" role="group" aria-label="Suggested questions">
            {prompts.map((prompt) => (
                <Button
                    key={prompt.id}
                    type="button"
                    size="sm"
                    variant="outline"
                    className="h-auto max-w-full px-3 py-1.5 text-left text-xs whitespace-normal"
                    disabled={isRunning}
                    onClick={() => aui.thread.append(prompt.label)}
                >
                    {prompt.label}
                </Button>
            ))}
        </div>
    );
}

function CampusCoinThread({ available }: { available: boolean }) {
    const isRunning = useAuiState((s) => s.thread.isRunning);

    return (
        <ThreadPrimitive.Root className="flex flex-col gap-3">
            <ThreadPrimitive.If empty={false}>
                <ThreadPrimitive.Viewport
                    className="max-h-[28rem] space-y-3 overflow-y-auto rounded-lg border border-white/10 bg-black/20 p-3"
                    aria-live="polite"
                    aria-label="Conversation"
                >
                    <ThreadPrimitive.Messages
                        components={{
                            UserMessage,
                            AssistantMessage,
                        }}
                    />

                    <ThreadPrimitive.If running>
                        <div className="flex justify-start">
                            <div className="max-w-[85%] rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white">
                                <p className="cc-label mb-1 text-[10px] tracking-wide uppercase opacity-70">Campus Coin AI</p>
                                <p className="cc-text-secondary flex items-center gap-2 text-sm">
                                    <Loader2 className="size-4 animate-spin" aria-hidden />
                                    Thinking…
                                </p>
                            </div>
                        </div>
                    </ThreadPrimitive.If>
                </ThreadPrimitive.Viewport>
            </ThreadPrimitive.If>

            <ComposerPrimitive.Root className="space-y-3">
                <label htmlFor="campus-coin-ai-input" className="sr-only">
                    Ask Campus Coin AI
                </label>
                <ComposerPrimitive.Input
                    id="campus-coin-ai-input"
                    placeholder="Ask about your spending, budgets or savings..."
                    rows={3}
                    maxLength={500}
                    disabled={isRunning}
                    submitMode="ctrlEnter"
                    className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[88px] w-full resize-y rounded-md border px-3 py-2 text-sm text-white focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                />
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="cc-text-tertiary text-xs">
                        {available ? 'Answers use your Campus Coin data only.' : 'Assistant is offline — your other features still work.'}
                    </p>
                    <ComposerPrimitive.Send asChild>
                        <Button type="button" disabled={isRunning} className="min-w-24">
                            {isRunning ? (
                                <>
                                    <Loader2 className="size-4 animate-spin" aria-hidden />
                                    Thinking
                                </>
                            ) : (
                                'Ask'
                            )}
                        </Button>
                    </ComposerPrimitive.Send>
                </div>
            </ComposerPrimitive.Root>
        </ThreadPrimitive.Root>
    );
}

function UserMessage() {
    return (
        <MessagePrimitive.Root className="flex justify-end">
            <div className="max-w-[85%] rounded-lg bg-white/10 px-3 py-2 text-sm text-white">
                <p className="cc-label mb-1 text-[10px] tracking-wide uppercase opacity-70">You</p>
                <MessagePrimitive.Parts
                    components={{
                        Text: UserTextPart,
                    }}
                />
            </div>
        </MessagePrimitive.Root>
    );
}

function UserTextPart({ text }: { text: string }) {
    return <p className="whitespace-pre-wrap">{text}</p>;
}

function AssistantMessage() {
    const response = useAuiState((s) => {
        const originals = getExternalStoreMessages<CcMessage>(s.message);
        const fromStore = originals[0]?.response;
        if (fromStore) {
            return fromStore;
        }
        const custom = s.message.metadata?.custom as { campusCoin?: AssistantResponse } | undefined;
        return custom?.campusCoin ?? null;
    });

    return (
        <MessagePrimitive.Root className="flex justify-start">
            <div className="max-w-[92%] rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white">
                <p className="cc-label mb-1 text-[10px] tracking-wide uppercase opacity-70">Campus Coin AI</p>
                {response ? <AssistantResult response={response} /> : <MessagePrimitive.Parts components={{ Text: UserTextPart }} />}
            </div>
        </MessagePrimitive.Root>
    );
}

function AssistantResult({ response }: { response: AssistantResponse }) {
    const showFacts =
        response.in_scope &&
        response.facts_used.length > 0 &&
        !['financial_explanation', 'navigation'].includes(response.response_type ?? '') &&
        response.intent !== 'navigation' &&
        response.intent !== 'feature_explain' &&
        response.intent !== 'balance';
    const links = response.in_scope ? (response.links ?? []) : [];

    return (
        <div className="space-y-3">
            <div>
                <p className="cc-label mb-1">Answer</p>
                <p className="text-sm leading-relaxed whitespace-pre-wrap text-white">{response.answer}</p>
            </div>

            {showFacts ? (
                <div>
                    <p className="cc-label mb-1">Based on</p>
                    <ul className="cc-text-secondary list-inside list-disc text-sm">
                        {response.facts_used.map((fact) => (
                            <li key={fact}>{fact}</li>
                        ))}
                    </ul>
                </div>
            ) : null}

            {response.allocation_plan && response.allocation_plan.length > 0 ? (
                <div>
                    <p className="cc-label mb-2">Suggested Plan</p>
                    <div className="space-y-2">
                        {response.allocation_plan.map((row) => (
                            <div
                                key={row.category}
                                className={cn(
                                    'flex flex-col gap-0.5 rounded-lg border border-white/10 bg-white/5 px-3 py-2 sm:flex-row sm:items-center sm:justify-between',
                                )}
                            >
                                <div className="min-w-0">
                                    <p className="text-sm font-medium text-white">{row.category}</p>
                                    <p className="cc-text-tertiary text-xs">{row.reason}</p>
                                </div>
                                <p className="shrink-0 text-sm font-semibold text-white">{formatMoney(row.amount)}</p>
                            </div>
                        ))}
                    </div>
                    {response.allocation_total ? (
                        <p className="mt-2 text-sm font-medium text-white">Total {formatMoney(response.allocation_total)}</p>
                    ) : null}
                    {response.why_this_plan && response.why_this_plan.length > 0 ? (
                        <div className="mt-3">
                            <p className="cc-label mb-1">Why this plan?</p>
                            <ul className="cc-text-secondary list-inside list-disc text-sm">
                                {response.why_this_plan.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                </div>
            ) : null}

            {response.next_action ? (
                <div>
                    <p className="cc-label mb-1">Suggested action</p>
                    <p className="cc-text-secondary text-sm">{response.next_action}</p>
                </div>
            ) : null}

            {links.length > 0 ? (
                <div>
                    <p className="cc-label mb-2">View in Campus Coin</p>
                    <div className="flex flex-wrap gap-2">
                        {links.map((link) => (
                            <Button key={link.href} asChild size="sm" variant="outline" className="h-auto px-3 py-1.5 text-xs">
                                <Link href={link.href}>{link.label}</Link>
                            </Button>
                        ))}
                    </div>
                </div>
            ) : null}

            {response.disclaimer ? <p className="cc-text-tertiary text-xs">{response.disclaimer}</p> : null}
        </div>
    );
}
