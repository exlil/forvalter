<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\DocumentAnalysis\ClaudeDocumentAnalyzer;
use App\Services\DocumentAnalysis\DocumentAnalyzer;
use App\Services\DocumentAnalysis\FakeDocumentAnalyzer;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Resolve the AI document analyzer (brief §7). 'auto' uses Claude when
        // an Anthropic key is configured, otherwise the deterministic stub —
        // so the intake flow works with or without a key.
        $this->app->bind(DocumentAnalyzer::class, function (): DocumentAnalyzer {
            $driver = config('forvalter.ai.driver', 'auto');
            $key = config('services.anthropic.key');
            $useClaude = $driver === 'claude' || ($driver === 'auto' && filled($key));

            if ($useClaude) {
                return new ClaudeDocumentAnalyzer(
                    new Client(apiKey: $key),
                    (string) config('forvalter.ai.model'),
                    (int) config('forvalter.ai.max_tokens'),
                );
            }

            return new FakeDocumentAnalyzer;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // While a Bifrost share tunnel is open, force generated URLs to the
        // public host (the tunnel hides it behind a rewritten Host header).
        if ($shareUrl = config('forvalter.share_url')) {
            URL::forceRootUrl($shareUrl);
            URL::forceScheme('https');
        }
    }
}
