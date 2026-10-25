<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer;

use Harlew\Ai\Tokenizer\Serializers\StructuredOutputSchemaSerializer;
use Harlew\Ai\Tokenizer\Serializers\ToolsSerializer;
use Illuminate\Support\Collection;
use Laravel\Ai\Ai;
use Laravel\Ai\Attributes\Model as ModelAttribute;
use Laravel\Ai\Attributes\Provider as ProviderAttribute;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Attributes\UseSmartestModel;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Providers\Provider;
use ReflectionClass;
use RuntimeException;

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
        $providerModel = null;
        $providerList = false;

        if ($provider === null) {
            [$provider, $providerModel, $providerList] = $this->getAgentProviderAndModel();
        }

        $resolvedProvider = $this->resolveProvider($provider);
        $model = $model ?? $providerModel ?? $this->getAgentModel($resolvedProvider, $providerList);

        $instructions = (string) $this->agent->instructions();
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

        if (trim($instructions) !== '') {
            $tokens += $this->tokenizer->count($instructions, $model, $provider);
        }

        $tokens += $this->countMessages($messages, $model, $provider);

        if ($tools !== []) {
            $tokens += $this->tokenizer->countPayload($tools, $model, $provider);
        }

        if ($schema !== null) {
            $tokens += $this->tokenizer->countPayload($schema, $model, $provider);
        }

        $messageCount = $messages->sum(static fn (mixed $message): int => $message instanceof ToolResultMessage
            ? $message->toolResults->count()
            : 1) + (trim($instructions) !== '' ? 1 : 0);

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
    protected function getAgentProviderAndModel(): array
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

        $value ??= config('ai.default');

        if ($value === null) {
            throw new RuntimeException('No Laravel AI provider is configured.');
        }

        $providers = Provider::formatProviderAndModelList($value);
        $name = array_key_first($providers);

        if ($name === null) {
            throw new RuntimeException('No Laravel AI provider is configured.');
        }

        return [$name, $providers[$name], is_array($value)];
    }

    /**
     * Get the model used by the agent.
     */
    protected function getAgentModel(Provider $provider, bool $providerList = false): string
    {
        $value = null;

        // Check if agent has a model() method
        if (! $providerList && method_exists($this->agent, 'model')) {
            $value = $this->agent->model();
        }

        // Check for Model attribute
        if (! $providerList && $value === null) {
            $reflection = new ReflectionClass($this->agent);
            $attributes = $reflection->getAttributes(ModelAttribute::class);

            if (! empty($attributes)) {
                $value = $attributes[0]->newInstance()->value;
            }
        }

        if ($value !== null) {
            return (string) $value;
        }

        $reflection = new ReflectionClass($this->agent);

        if ($reflection->getAttributes(UseSmartestModel::class) !== []) {
            return $provider->smartestTextModel();
        }

        if ($reflection->getAttributes(UseCheapestModel::class) !== []) {
            return $provider->cheapestTextModel();
        }

        return $provider->defaultTextModel();
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
            if ($message instanceof ToolResultMessage) {
                foreach ($message->toolResults as $result) {
                    $tokens += $this->tokenizer->count((string) ($result->resultId ?? $result->id), $model, $provider);
                    $tokens += $this->tokenizer->count(method_exists($result, 'text')
                        ? $result->text()
                        : (is_string($result->result) ? $result->result : $this->serializeToolResult($result->result)), $model, $provider);
                }

                continue;
            }

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
                foreach ($message->toolCalls as $toolCall) {
                    $tokens += $this->tokenizer->count((string) ($toolCall->resultId ?? $toolCall->id), $model, $provider);
                    $tokens += $this->tokenizer->count((string) $toolCall->name, $model, $provider);
                    $tokens += $this->tokenizer->count($this->serializeToolResult($toolCall->arguments), $model, $provider);
                }
            }

        }

        return $tokens;
    }

    private function serializeToolResult(mixed $result): string
    {
        return is_array($result)
            ? (json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
            : (string) $result;
    }

    protected function resolveProvider(?string $providerName): Provider
    {
        $providerName ??= (string) config('ai.default');
        $provider = Ai::textProviderFor($this->agent, $providerName);

        if (! $provider instanceof Provider) {
            throw new RuntimeException("Provider [{$providerName}] cannot be mapped to a Laravel AI text provider.");
        }

        return $provider;
    }
}
