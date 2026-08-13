<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Laravel AI Tokenizer package.
 */
class TokenizerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ai/tokenizer.php',
            'ai.tokenizer'
        );

        $this->app->singleton(Tokenizer::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/ai/tokenizer.php' => config_path('ai/tokenizer.php'),
        ], 'tokenizer-config');
    }
}
