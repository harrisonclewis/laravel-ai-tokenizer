<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\Tests;

use Harlew\Ai\Tokenizer\TokenizerServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Prism\Prism\PrismServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PrismServiceProvider::class,
            AiServiceProvider::class,
            TokenizerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        $app['config']->set('ai.default', 'openrouter');
        $app['config']->set('ai.providers.openrouter', [
            'driver' => 'openrouter',
            'key' => (string) env('OPENROUTER_API_KEY', 'test-openrouter-key'),
        ]);
        $app['config']->set('ai.providers.openai', [
            'driver' => 'openai',
            'key' => (string) env('OPENAI_API_KEY', 'test-openai-key'),
        ]);
        $app['config']->set('ai.providers.anthropic', [
            'driver' => 'anthropic',
            'key' => (string) env('ANTHROPIC_API_KEY', 'test-anthropic-key'),
        ]);

        $app['config']->set('ai.tokenizer.default_model', 'gpt-4o');
        $app['config']->set('ai.tokenizer.message_overhead.openrouter.per_request', 8);
    }
}
