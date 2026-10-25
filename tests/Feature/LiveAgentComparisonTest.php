<?php

declare(strict_types=1);

use Harlew\Ai\Tokenizer\Tests\Fixtures\SchemaAnonymousAgent;
use Harlew\Ai\Tokenizer\Tests\Fixtures\TestTool;
use Harlew\Ai\Tokenizer\Tests\Support\LiveCalibration;
use Harlew\Ai\Tokenizer\Tokenizer;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;

function openRouterCalibrationSkipReason(): ?string
{
    if (! filter_var((string) env('TOKENIZER_RUN_LIVE_TESTS', 'false'), FILTER_VALIDATE_BOOLEAN)) {
        return 'Use composer test:live to run real OpenRouter requests.';
    }

    if ((string) env('OPENROUTER_API_KEY', '') === '') {
        return 'OpenRouter calibration requires OPENROUTER_API_KEY.';
    }

    return null;
}

function compareOpenRouterRequest(
    string $scenario,
    AnonymousAgent $agent,
    string $prompt,
    array $attachments = [],
    int $maxDelta = 8,
): void {
    $model = (string) env('TOKENIZER_LIVE_MODEL', 'openai/gpt-4o-mini');
    $estimate = app(Tokenizer::class)->forAgent($agent)->estimate($prompt, $attachments, 'openrouter', $model);
    $response = $agent->prompt($prompt, attachments: $attachments, provider: 'openrouter', model: $model);
    $visibleOutputTokens = app(Tokenizer::class)->count($response->text, $model, 'openrouter');
    $comparison = LiveCalibration::compare($response, $scenario, 'openrouter', $model, $estimate, $visibleOutputTokens);

    LiveCalibration::record($comparison);

    expect($comparison['absolute_delta_tokens'])->toBeLessThanOrEqual(
        $maxDelta,
        sprintf('%s estimate differs from provider usage by %d tokens.', $scenario, $comparison['absolute_delta_tokens']),
    );
}

it('compares a simple request with OpenRouter input usage', function (): void {
    if (($reason = openRouterCalibrationSkipReason()) !== null) {
        $this->markTestSkipped($reason);
    }

    compareOpenRouterRequest(
        'simple',
        new AnonymousAgent('You are concise.', [], []),
        'Respond with exactly: ack',
        maxDelta: (int) env('TOKENIZER_LIVE_MAX_DELTA_SIMPLE', 8),
    );
})->group('live');

it('compares conversation history with OpenRouter input usage', function (): void {
    if (($reason = openRouterCalibrationSkipReason()) !== null) {
        $this->markTestSkipped($reason);
    }

    compareOpenRouterRequest(
        'history',
        new AnonymousAgent('You are concise.', [
            new UserMessage('Earlier I asked about revenue.'),
            new AssistantMessage('The earlier revenue was 1.2M.'),
        ], []),
        'What was the earlier revenue? Answer briefly.',
        maxDelta: (int) env('TOKENIZER_LIVE_MAX_DELTA_HISTORY', 12),
    );
})->group('live');

it('compares tools and structured output with OpenRouter input usage', function (): void {
    if (($reason = openRouterCalibrationSkipReason()) !== null) {
        $this->markTestSkipped($reason);
    }

    compareOpenRouterRequest(
        'tools-and-schema',
        new SchemaAnonymousAgent('Return a short JSON response.', [], [new TestTool]),
        'Return a summary of "ok" and confidence 1. Do not call tools.',
        maxDelta: (int) env('TOKENIZER_LIVE_MAX_DELTA_RICH', 30),
    );
})->group('live');

it('optionally compares a text document attachment with OpenRouter input usage', function (): void {
    if (($reason = openRouterCalibrationSkipReason()) !== null) {
        $this->markTestSkipped($reason);
    }

    if (! filter_var((string) env('TOKENIZER_LIVE_INCLUDE_ATTACHMENTS', 'false'), FILTER_VALIDATE_BOOLEAN)) {
        $this->markTestSkipped('Set TOKENIZER_LIVE_INCLUDE_ATTACHMENTS=true to test file processing.');
    }

    compareOpenRouterRequest(
        'text-attachment',
        new AnonymousAgent('Summarize the attached report in one sentence.', [], []),
        'What does the report say?',
        [Document::fromString('Q4 revenue: 1.2M. Q4 margin: 22%.', 'text/plain')->as('report.txt')],
        maxDelta: (int) env('TOKENIZER_LIVE_MAX_DELTA_ATTACHMENT', 30),
    );
})->group('live');
