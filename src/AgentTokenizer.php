<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer;

use Harlew\Ai\Tokenizer\Serializers\StructuredOutputSchemaSerializer;
use Harlew\Ai\Tokenizer\Serializers\ToolsSerializer;
use Illuminate\Support\Collection;
use Laravel\Ai\Ai;
use Laravel\Ai\Attributes\Model as ModelAttribute;
use Laravel\Ai\Attributes\Provider as ProviderAttribute;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Providers\Provider;
use ReflectionClass;
use Throwable;

class AgentTokenizer
{
    /**
     * Create a new AgentTokenizer instance.
     */
    public function __construct(
        private Tokenizer $tokenizer,
        private Agent $agent,
        ?ToolsSerializer $toolsSerializer = null,
        ?StructuredOutputSchemaSerializer $structuredOutputSchemaSerializer = null,
    ) {
        $this->toolsSerializer = $toolsSerializer ?? new ToolsSerializer;
        $this->structuredOutputSchemaSerializer = $structuredOutputSchemaSerializer ?? new StructuredOutputSchemaSerializer;
    }

    private ToolsSerializer $toolsSerializer;

    private StructuredOutputSchemaSerializer $structuredOutputSchemaSerializer;

    /**
     * Estimate tokens for the agent with the given prompt.
     */
    public function estimate(string $prompt, array $attachments = [], ?string $provider = null, ?string $model = null): int
    {
        $provider = $provider ?? $this->getAgentProvider();
        $model = $model ?? $this->getAgentModel($provider);
        $resolvedProvider = $this->resolveProvider($provider);

        $instructions = trim((string) $this->agent->instructions());
        $messages = new Collection;

        if ($this->agent instanceof Conversational) {
            $messages = new Collection($this->agent->messages());
        }

        $messages->push(new UserMessage($prompt, $attachments));

        $tools = $this->agent instanceof HasTools
            ? $this->serializeTools($this->agent->tools(), $resolvedProvider)
            : [];

        $schema = $this->serializeStructuredOutputSchema($resolvedProvider);

        $tokens = 0;

        if ($instructions !== '') {
            $tokens += $this->tokenizer->count($instructions, $model, $provider);
        }

        $tokens += $this->countMessages($messages, $model, $provider);

        if ($tools !== []) {
            $tokens += $this->tokenizer->countPayload($tools, $model, $provider);
        }

        if ($schema !== null) {
            $tokens += $this->tokenizer->countPayload($schema, $model, $provider);
        }

        $messageCount = $messages->count() + ($instructions !== '' ? 1 : 0);

        return $tokens + $this->calculateOverhead($provider, $messageCount);
    }

    /**
     * Calculate the message framing overhead for the given provider.
     */
    protected function calculateOverhead(?string $provider, int $messageCount): int
    {
        $overheadConfig = config('ai.tokenizer.message_overhead', []);
        $providerOverhead = $overheadConfig[$provider] ?? $overheadConfig['default'] ?? [
            'per_message' => 4,
            'per_request' => 3,
            'per_name' => 0,
        ];

        $perMessage = $providerOverhead['per_message'] ?? 4;
        $perRequest = $providerOverhead['per_request'] ?? 3;

        return ($perMessage * $messageCount) + $perRequest;
    }

    /**
     * Get the provider used by the agent.
     */
    protected function getAgentProvider(): ?string
    {
        $value = null;

        // Check if agent has a provider() method
        if (method_exists($this->agent, 'provider')) {
            $value = $this->agent->provider();
        }

        // Check for Provider attribute
        if ($value === null) {
            $reflection = new ReflectionClass($this->agent);
            $attributes = $reflection->getAttributes(ProviderAttribute::class);

            if (! empty($attributes)) {
                $value = $attributes[0]->newInstance()->value;
            }
        }

        // Normalize array to string (first provider)
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        // Handle Lab enum or other stringable values
        if ($value instanceof Lab) {
            $value = $value->value;
        }

        return $value !== null ? (string) $value : config('ai.default');
    }

    /**
     * Get the model used by the agent.
     */
    protected function getAgentModel(?string $provider): string
    {
        $value = null;

        // Check if agent has a model() method
        if (method_exists($this->agent, 'model')) {
            $value = $this->agent->model();
        }

        // Check for Model attribute
        if ($value === null) {
            $reflection = new ReflectionClass($this->agent);
            $attributes = $reflection->getAttributes(ModelAttribute::class);

            if (! empty($attributes)) {
                $value = $attributes[0]->newInstance()->value;
            }
        }

        if ($value !== null) {
            return (string) $value;
        }

        $providerDefaultModel = $this->getProviderDefaultModel($provider);

        if ($providerDefaultModel !== null) {
            return $providerDefaultModel;
        }

        return (string) config('ai.tokenizer.default_model', 'gpt-4');
    }

    /**
     * Serialize tools for tokenization.
     */
    protected function serializeTools(iterable $tools, ?Provider $provider): array
    {
        return $this->toolsSerializer->serialize(
            $tools,
            $provider
        );
    }

    /**
     * Serialize the agent structured output schema.
     */
    protected function serializeStructuredOutputSchema(?Provider $provider): ?array
    {
        return $this->structuredOutputSchemaSerializer->serialize($this->agent, $provider);
    }

    protected function countMessages(Collection $messages, ?string $model, ?string $provider): int
    {
        $tokens = 0;

        foreach ($messages as $message) {
            $content = (string) (data_get($message, 'content') ?? '');

            if ($content !== '') {
                $tokens += $this->tokenizer->count($content, $model, $provider);
            }

            if (isset($message->attachments)) {
                $attachments = $message->attachments instanceof Collection
                    ? $message->attachments->all()
                    : (array) $message->attachments;

                if ($attachments !== []) {
                    $tokens += $this->tokenizer->attachments($attachments, $model, $provider);
                }
            }

            if (isset($message->toolCalls) && is_iterable($message->toolCalls)) {
                $tokens += $this->tokenizer->countPayload(
                    Collection::make($message->toolCalls)->map(
                        static fn (mixed $toolCall): mixed => method_exists($toolCall, 'toArray')
                            ? $toolCall->toArray()
                            : $toolCall
                    )->values()->all(),
                    $model,
                    $provider
                );
            }

            if (isset($message->toolResults) && is_iterable($message->toolResults)) {
                $tokens += $this->tokenizer->countPayload(
                    Collection::make($message->toolResults)->map(
                        static fn (mixed $toolResult): mixed => method_exists($toolResult, 'toArray')
                            ? $toolResult->toArray()
                            : $toolResult
                    )->values()->all(),
                    $model,
                    $provider
                );
            }
        }

        return $tokens;
    }

    protected function getProviderDefaultModel(?string $providerName): ?string
    {
        try {
            $providerName ??= (string) config('ai.default');

            return Ai::textProviderFor($this->agent, $providerName)->defaultTextModel();
        } catch (Throwable) {
            return null;
        }
    }

    protected function resolveProvider(?string $providerName): ?Provider
    {
        try {
            $providerName ??= (string) config('ai.default');
            $provider = Ai::textProviderFor($this->agent, $providerName);

            return $provider instanceof Provider ? $provider : null;
        } catch (Throwable) {
            return null;
        }
    }

}
