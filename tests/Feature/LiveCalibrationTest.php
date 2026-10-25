<?php

declare(strict_types=1);

use Harlew\Ai\Tokenizer\Tests\Support\LiveCalibration;

it('compares the first step with inclusive Laravel AI 1.x input usage', function (): void {
    $response = (object) [
        'steps' => [
            (object) [
                'usage' => (object) ['inputTokens' => 100, 'outputTokens' => 4, 'cacheReadInputTokens' => 20],
                'meta' => (object) ['model' => 'first-model'],
            ],
            (object) ['usage' => (object) ['inputTokens' => 150, 'outputTokens' => 5]],
        ],
        'usage' => (object) ['inputTokens' => 250, 'outputTokens' => 9],
        'meta' => (object) ['model' => 'openai/gpt-4o-mini'],
    ];

    $comparison = LiveCalibration::compare($response, 'history', 'openrouter', 'openai/gpt-4o-mini', 92, 3);

    expect($comparison['actual_input_tokens'])->toBe(100)
        ->and($comparison['delta_tokens'])->toBe(-8)
        ->and($comparison['delta_percent'])->toBe(-8.0)
        ->and($comparison['reported_model'])->toBe('first-model')
        ->and($comparison['usage_scope'])->toBe('first-step')
        ->and($comparison['response_steps'])->toBe(2)
        ->and($comparison['actual_output_tokens'])->toBe(4)
        ->and($comparison['visible_output_text_tokens'])->toBe(3)
        ->and($comparison['visible_output_delta_tokens'])->toBe(-1);
});

it('handles Laravel AI 0.10 cache usage without double counting OpenRouter input', function (): void {
    $response = (object) [
        'usage' => (object) [
            'promptTokens' => 100,
            'completionTokens' => 8,
            'cacheReadInputTokens' => 20,
            'cacheWriteInputTokens' => 5,
        ],
    ];

    $openRouter = LiveCalibration::compare($response, 'simple', 'openrouter', 'openai/gpt-4o-mini', 100);
    $anthropic = LiveCalibration::compare($response, 'simple', 'anthropic', 'claude', 100);

    expect($openRouter['actual_input_tokens'])->toBe(100)
        ->and($openRouter['usage_scope'])->toBe('response')
        ->and($openRouter['actual_output_tokens'])->toBe(8)
        ->and($anthropic['actual_input_tokens'])->toBe(125);
});
