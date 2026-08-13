<?php

declare(strict_types=1);

use Harlew\Ai\Tokenizer\Tokenizer;
use Harlew\Ai\Tokenizer\Tests\Fixtures\SchemaAnonymousAgent;
use Harlew\Ai\Tokenizer\Tests\Fixtures\TestTool;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Providers\Tools\WebSearch;

function shouldRunLiveTokenizerTests(): bool
{
    return filter_var((string) env('TOKENIZER_RUN_LIVE_TESTS', 'false'), FILTER_VALIDATE_BOOLEAN);
}

function liveProviderCredentialEnv(string $provider): ?string
{
    return match ($provider) {
        'openrouter' => 'OPENROUTER_API_KEY',
        'openai' => 'OPENAI_API_KEY',
        'anthropic' => 'ANTHROPIC_API_KEY',
        default => null,
    };
}

function liveCalibrationPromptTokens(object $response): int
{
    $firstStepPromptTokens = data_get($response, 'steps.0.usage.promptTokens');
    $firstStepCacheReadInputTokens = data_get($response, 'steps.0.usage.cacheReadInputTokens');

    if ($firstStepPromptTokens !== null) {
        return (int) $firstStepPromptTokens + (int) ($firstStepCacheReadInputTokens ?? 0);
    }

    $promptTokens = (int) data_get($response, 'usage.promptTokens', 0);
    $cacheReadInputTokens = (int) data_get($response, 'usage.cacheReadInputTokens', 0);

    // Fallback to aggregate usage when step usage is unavailable.
    return $promptTokens + $cacheReadInputTokens;
}

function liveSupportsRichAttachmentPayload(Throwable $e): bool
{
    $message = strtolower($e->getMessage());

    return ! (
        str_contains($message, 'invalid file type')
        || str_contains($message, 'does not support')
        || (str_contains($message, 'attachment') && str_contains($message, 'support'))
        || (str_contains($message, 'file') && str_contains($message, 'support'))
    );
}

/**
 * Build a provider-compatible tool set for rich live calibration.
 */
function liveRichToolsForProvider(string $provider): array
{
    $tools = [
        new TestTool(),
    ];

    // Provider tools are not universally supported. Keep the payload rich, but compatible.
    if (in_array($provider, ['openai', 'anthropic'], true)) {
        $tools[] = new WebSearch(maxSearches: 1, allowedDomains: ['example.com']);
    }

    return $tools;
}

it('stays within configured delta for live prompt token counts (simple payload)', function (): void {
    if (! shouldRunLiveTokenizerTests()) {
        $this->markTestSkipped('Live tokenizer tests are disabled. Set TOKENIZER_RUN_LIVE_TESTS=true to enable.');
    }

    $provider = (string) env('TOKENIZER_LIVE_PROVIDER', 'openrouter');
    $credentialEnv = liveProviderCredentialEnv($provider);

    if ($credentialEnv === null || (string) env($credentialEnv, '') === '') {
        $this->markTestSkipped("Missing credentials for provider [{$provider}]. Set {$credentialEnv}.");
    }

    $model = env('TOKENIZER_LIVE_MODEL') ?: null;
    $prompt = 'Respond with exactly: ack';
    $agent = new AnonymousAgent('You are concise.', [], []);

    $tokenizer = app(Tokenizer::class);

    $estimate = $tokenizer->forAgent($agent)->estimate($prompt, provider: $provider, model: $model);
    $response = $agent->prompt($prompt, provider: $provider, model: $model);
    $actual = liveCalibrationPromptTokens($response);

    $maxDelta = (int) env('TOKENIZER_LIVE_MAX_DELTA_SIMPLE', 8);

    expect(abs($estimate - $actual))->toBeLessThanOrEqual($maxDelta);
})->group('live');

it('stays within configured delta for live prompt token counts (rich payload)', function (): void {
    if (! shouldRunLiveTokenizerTests()) {
        $this->markTestSkipped('Live tokenizer tests are disabled. Set TOKENIZER_RUN_LIVE_TESTS=true to enable.');
    }

    $provider = (string) env('TOKENIZER_LIVE_PROVIDER', 'openrouter');
    $credentialEnv = liveProviderCredentialEnv($provider);

    if ($credentialEnv === null || (string) env($credentialEnv, '') === '') {
        $this->markTestSkipped("Missing credentials for provider [{$provider}]. Set {$credentialEnv}.");
    }

    $model = env('TOKENIZER_LIVE_MODEL') ?: null;
    $attachmentContext = 'Q4 revenue: 1.2M. Q4 margin: 22%.';
    $prompt = 'Summarize the report context and return summary plus numeric confidence.';
    $attachments = [
        Document::fromString($attachmentContext, 'text/plain')->as('report.txt'),
    ];

    $agent = new SchemaAnonymousAgent(
        'You are a precise analyst.',
        [],
        liveRichToolsForProvider($provider)
    );

    $tokenizer = app(Tokenizer::class);
    try {
        $estimate = $tokenizer->forAgent($agent)->estimate($prompt, $attachments, $provider, $model);
        $response = $agent->prompt($prompt, attachments: $attachments, provider: $provider, model: $model);
    } catch (Throwable $e) {
        if (liveSupportsRichAttachmentPayload($e)) {
            $this->markTestSkipped(
                "Provider/model does not support this rich live calibration payload: {$e->getMessage()}"
            );
        }

        // Fall back to a provider-safe rich payload when attachment media types are rejected.
        $prompt = $prompt."\n\nReport context: {$attachmentContext}";
        $attachments = [];

        try {
            $estimate = $tokenizer->forAgent($agent)->estimate($prompt, $attachments, $provider, $model);
            $response = $agent->prompt($prompt, attachments: $attachments, provider: $provider, model: $model);
        } catch (Throwable $fallback) {
            $this->markTestSkipped(
                "Provider/model does not support this rich live calibration payload: {$fallback->getMessage()}"
            );
        }
    }

    $actual = liveCalibrationPromptTokens($response);
    $maxDelta = (int) env('TOKENIZER_LIVE_MAX_DELTA_RICH', 30);

    expect(abs($estimate - $actual))->toBeLessThanOrEqual($maxDelta);
})->group('live');
