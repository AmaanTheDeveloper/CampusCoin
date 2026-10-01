<?php

namespace App\Providers;

use App\Contracts\AiAssistantProvider;
use App\Contracts\AiCategorizationProvider;
use App\Contracts\AiInsightProvider;
use App\Contracts\DocumentScanExtractor;
use App\Listeners\NotifyAdminsOfNewStudent;
use App\Models\User;
use App\Services\Ai\Providers\GeminiAssistantProvider;
use App\Services\Ai\Providers\GeminiCategorizationProvider;
use App\Services\Ai\Providers\GeminiInsightProvider;
use App\Services\Ai\Providers\NullAssistantProvider;
use App\Services\Ai\Providers\NullCategorizationProvider;
use App\Services\Ai\Providers\NullInsightProvider;
use App\Services\Ai\Providers\OpenAiCategorizationProvider;
use App\Services\Ai\Providers\OpenAiInsightProvider;
use App\Services\DocumentScan\ChainDocumentScanExtractor;
use App\Services\DocumentScan\FakeDocumentScanExtractor;
use App\Services\DocumentScan\GeminiDocumentScanExtractor;
use App\Services\DocumentScan\LocalTesseractDocumentScanExtractor;
use App\Services\DocumentScan\NullDocumentScanExtractor;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiCategorizationProvider::class, function () {
            return $this->resolveCategorizationProvider();
        });

        $this->app->singleton(AiInsightProvider::class, function () {
            return $this->resolveInsightProvider();
        });

        $this->app->singleton(AiAssistantProvider::class, function () {
            return $this->resolveAssistantProvider();
        });

        $this->app->singleton(DocumentScanExtractor::class, function () {
            return $this->resolveDocumentScanExtractor();
        });

        $this->app->bind(
            \App\Services\Loans\Payments\PaymentProvider::class,
            \App\Services\Loans\Payments\SandboxPaymentProvider::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('access-admin', function (User $user): bool {
            return $user->isAdmin();
        });

        Event::listen(Registered::class, NotifyAdminsOfNewStudent::class);
    }

    private function resolveCategorizationProvider(): AiCategorizationProvider
    {
        $enabled = (bool) config('ai.enabled', true);
        $provider = (string) config('ai.provider', 'openai');

        if (! $enabled || $provider === 'null') {
            return new NullCategorizationProvider;
        }

        if ($provider === 'gemini') {
            $gemini = new GeminiCategorizationProvider;

            return $gemini->isAvailable() ? $gemini : new NullCategorizationProvider;
        }

        if (! filled(config('ai.api_key'))) {
            return new NullCategorizationProvider;
        }

        return new OpenAiCategorizationProvider;
    }

    private function resolveInsightProvider(): AiInsightProvider
    {
        $enabled = (bool) config('ai.enabled', true);
        $provider = (string) config('ai.provider', 'openai');

        if (! $enabled || $provider === 'null') {
            return new NullInsightProvider;
        }

        if ($provider === 'gemini') {
            $gemini = new GeminiInsightProvider;

            return $gemini->isAvailable() ? $gemini : new NullInsightProvider;
        }

        if (! filled(config('ai.api_key'))) {
            return new NullInsightProvider;
        }

        return new OpenAiInsightProvider;
    }

    private function resolveAssistantProvider(): AiAssistantProvider
    {
        $enabled = (bool) config('ai.enabled', true);
        $provider = (string) config('ai.provider', 'openai');

        if (! $enabled || $provider === 'null') {
            return new NullAssistantProvider;
        }

        // Campus Coin AI assistant is Gemini-backed. Activate whenever Gemini is
        // configured so OpenAI can still power categorization/insights independently.
        $gemini = new GeminiAssistantProvider;

        return $gemini->isAvailable() ? $gemini : new NullAssistantProvider;
    }

    private function resolveDocumentScanExtractor(): DocumentScanExtractor
    {
        // Local/browser QA only — never enable in production.
        if ((bool) config('campuscoin.document_scan.fake_extractor', false) && ! app()->environment('production')) {
            return new FakeDocumentScanExtractor;
        }

        $aiEnabled = (bool) config('ai.enabled', true);
        $primary = new NullDocumentScanExtractor;

        if ($aiEnabled) {
            $gemini = new GeminiDocumentScanExtractor;
            if ($gemini->isAvailable()) {
                $primary = $gemini;
            }
        }

        $fallback = null;
        if ((bool) config('campuscoin.document_scan.fallback_enabled', true)) {
            $tesseract = new LocalTesseractDocumentScanExtractor;
            if ($tesseract->isAvailable()) {
                $fallback = $tesseract;
            }
        }

        if ($fallback === null) {
            return $primary;
        }

        return new ChainDocumentScanExtractor($primary, $fallback);
    }
}
