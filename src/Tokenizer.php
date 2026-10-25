<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer;

use Harlew\Ai\Tokenizer\Serializers\AttachmentSerializer;
use Harlew\Ai\Tokenizer\Support\Encodings\Cl100kEncoding;
use Harlew\Ai\Tokenizer\Support\Encodings\EncodingInterface;
use Harlew\Ai\Tokenizer\Support\Encodings\O200kEncoding;
use Harlew\Ai\Tokenizer\Support\Visualizer;
use Harlew\Ai\Tokenizer\ValueObjects\Tokenized;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Agent;

class Tokenizer
{
    private array $encodings = [];

    private Visualizer $visualizer;

    private AttachmentSerializer $attachmentSerializer;

    public function __construct(?AttachmentSerializer $attachmentSerializer = null)
    {
        $this->visualizer = new Visualizer;
        $this->attachmentSerializer = $attachmentSerializer ?? new AttachmentSerializer;
    }

    public function tokenize(string $text, ?string $model = null, ?string $provider = null): Tokenized
    {
        return $this->visualize($text, $model, $provider);
    }

    public function count(string $text, ?string $model = null, ?string $provider = null): int
    {
        $model = $model ?? config('ai.tokenizer.default_model', 'gpt-4');
        $encoding = $this->getEncoding($model);

        return count($encoding->encode($text));
    }

    public function visualize(string $text, ?string $model = null, ?string $provider = null): Tokenized
    {
        $model = $model ?? config('ai.tokenizer.default_model', 'gpt-4');
        $encoding = $this->getEncoding($model);

        $tokenIds = $encoding->encode($text);
        $visualization = [];

        foreach ($tokenIds as $tokenId) {
            $bytes = $encoding->decodeSingle($tokenId);
            $visualization[] = $this->visualizer->formatToken($tokenId, $bytes);
        }

        return new Tokenized(
            tokens: count($tokenIds),
            visualization: $visualization,
        );
    }

    public function forAgent(Agent $agent): AgentTokenizer
    {
        return new AgentTokenizer($this, $agent);
    }

    public function agent(
        Agent $agent,
        string $prompt,
        array $attachments = [],
        ?string $provider = null,
        ?string $model = null
    ): int {
        return $this->forAgent($agent)->estimate($prompt, $attachments, $provider, $model);
    }

    public function attachments(array $attachments, ?string $model = null, ?string $provider = null): int
    {
        if (empty($attachments)) {
            return 0;
        }

        $payload = $this->attachmentSerializer->serialize($attachments);
        $tokens = $this->attachmentSerializer->estimatedTokens($payload);

        foreach ($payload as $part) {
            if (isset($part['content'])) {
                $tokens += $this->count($part['content'], $model, $provider);
            }

            if (isset($part['name'])) {
                $tokens += $this->count($part['name'], $model, $provider);
            }
        }

        return $tokens;
    }

    public function countPayload(array $payload, ?string $model = null, ?string $provider = null): int
    {
        return $this->count($this->serializePayload($payload), $model, $provider);
    }

    public function models(): array
    {
        return config('ai.tokenizer.models', []);
    }

    private function getEncoding(string $model): EncodingInterface
    {
        $models = config('ai.tokenizer.models', []);
        $encodingName = $models[$model]['encoding'] ?? $this->encodingForOpenAiModel($model)
            ?? config('ai.tokenizer.fallback_encoding', 'cl100k_base');

        if (isset($this->encodings[$encodingName])) {
            return $this->encodings[$encodingName];
        }

        $encoding = match ($encodingName) {
            'o200k_base' => new O200kEncoding,
            'cl100k_base' => new Cl100kEncoding,
            default => throw new InvalidArgumentException("Unsupported tokenizer encoding [{$encodingName}]."),
        };

        $this->encodings[$encodingName] = $encoding;

        return $encoding;
    }

    private function encodingForOpenAiModel(string $model): ?string
    {
        // OpenRouter prefixes OpenAI model names with "openai/".
        $model = preg_replace('~^openai/~', '', $model);

        if (preg_match('/^(gpt-5|gpt-4\.5|gpt-4\.1|gpt-4o|chatgpt-4o|o[134](?:-|$)|o4-mini)/', $model)) {
            return 'o200k_base';
        }

        if (preg_match('/^(gpt-4(?:-|$)|gpt-3\.5(?:-|$))/', $model)) {
            return 'cl100k_base';
        }

        return null;
    }

    private function serializePayload(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        ) ?: '';
    }
}
