<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\Tests\Support;

use Countable;
use RuntimeException;

final class LiveCalibration
{
    public static function compare(
        object $response,
        string $scenario,
        string $provider,
        string $model,
        int $estimate,
        ?int $visibleOutputTokens = null,
    ): array {
        $firstStepUsage = data_get($response, 'steps.0.usage');
        $usage = $firstStepUsage ?? data_get($response, 'usage');

        if ($usage === null) {
            throw new RuntimeException('The Laravel AI response did not report token usage.');
        }

        $actual = data_get($usage, 'inputTokens');

        if ($actual === null) {
            $actual = data_get($usage, 'promptTokens');

            if ($actual === null) {
                throw new RuntimeException('The Laravel AI response did not report input token usage.');
            }

            // Laravel AI 0.10 reports these cache tokens separately for Anthropic and Bedrock.
            if (in_array($provider, ['anthropic', 'bedrock'], true)) {
                $actual += (int) data_get($usage, 'cacheReadInputTokens', 0);
                $actual += (int) data_get($usage, 'cacheWriteInputTokens', 0);
            }
        }

        $actual = (int) $actual;

        if ($actual <= 0) {
            throw new RuntimeException('The Laravel AI response reported no input tokens for a non-empty request.');
        }

        $delta = $estimate - $actual;
        $steps = data_get($response, 'steps');
        $output = data_get($usage, 'outputTokens') ?? data_get($usage, 'completionTokens');

        return [
            'scenario' => $scenario,
            'provider' => $provider,
            'requested_model' => $model,
            'reported_model' => data_get($response, 'steps.0.meta.model') ?? data_get($response, 'meta.model'),
            'usage_scope' => $firstStepUsage === null ? 'response' : 'first-step',
            'estimated_input_tokens' => $estimate,
            'actual_input_tokens' => $actual,
            'delta_tokens' => $delta,
            'absolute_delta_tokens' => abs($delta),
            'delta_percent' => $actual > 0 ? round(100 * $delta / $actual, 1) : null,
            'actual_output_tokens' => $output,
            'visible_output_text_tokens' => $visibleOutputTokens,
            'visible_output_delta_tokens' => $visibleOutputTokens === null || $output === null
                ? null
                : $visibleOutputTokens - (int) $output,
            'response_steps' => is_array($steps) || $steps instanceof Countable ? count($steps) : null,
        ];
    }

    public static function record(array $result): void
    {
        fwrite(STDOUT, sprintf(
            "\n%-18s input estimate=%d actual=%d delta=%+d (%+.1f%%) output actual=%s visible-text=%s steps=%s\n",
            $result['scenario'],
            $result['estimated_input_tokens'],
            $result['actual_input_tokens'],
            $result['delta_tokens'],
            $result['delta_percent'] ?? 0,
            $result['actual_output_tokens'] ?? '?',
            $result['visible_output_text_tokens'] ?? '?',
            $result['response_steps'] ?? '?',
        ));

        $path = getenv('TOKENIZER_LIVE_REPORT');

        if ($path === false || $path === '') {
            return;
        }

        $line = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;

        if (file_put_contents($path, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException("Could not write calibration report to [{$path}].");
        }
    }
}
