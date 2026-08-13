<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer;

use Harlew\Ai\Tokenizer\Serializers\AttachmentSerializer;
use Harlew\Ai\Tokenizer\Support\Encodings\Cl100kEncoding;
use Harlew\Ai\Tokenizer\Support\Encodings\EncodingInterface;
use Harlew\Ai\Tokenizer\Support\Encodings\O200kEncoding;
use Harlew\Ai\Tokenizer\Support\Visualizer;
use Harlew\Ai\Tokenizer\ValueObjects\Tokenized;
use Laravel\Ai\Contracts\Agent;

class Tokenizer
{
    private array $encodings = [];

    private Visualizer $visualizer;

    private AttachmentSerializer $attachmentSerializer;

    public function __construct(?AttachmentSerializer $attachmentSerializer = null)
    {
        $this->visualizer = new Visualizer();
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
        $text = $this->serializePayload($payload);
        $estimatedTokens = $this->attachmentSerializer->estimatedTokens($payload);

        return $this->count($text, $model, $provider) + $estimatedTokens;
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
        $encodingName = $models[$model]['encoding'] ?? null;

        if ($encodingName === null) {
            $encodingName = 'cl100k_base';
        }

        if (isset($this->encodings[$encodingName])) {
            return $this->encodings[$encodingName];
        }

        $encoding = match ($encodingName) {
            'o200k_base' => new O200kEncoding(),
            'cl100k_base' => new Cl100kEncoding(),
            default => new Cl100kEncoding(),
        };

        $this->encodings[$encodingName] = $encoding;

        return $encoding;
    }

    private function serializePayload(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        ) ?: '';
    }
}
