<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\Serializers;

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Providers\Provider;

class StructuredOutputSchemaSerializer
{
    public function __construct(
        private ?PayloadNormalizer $normalizer = null,
    ) {
        $this->normalizer ??= new PayloadNormalizer;
    }

    /**
     * Serialize an agent's structured output schema for token counting.
     *
     * @return array<string, mixed>|null
     */
    public function serialize(Agent $agent, ?Provider $provider = null): ?array
    {
        if (! $agent instanceof HasStructuredOutput) {
            return null;
        }

        $schema = $agent->schema(new JsonSchemaTypeFactory);
        $payload = $this->normalizer->normalize($this->schemaPayloadForProvider($schema, $provider));

        return $payload === [] ? null : $payload;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function schemaPayloadForProvider(array $schema, ?Provider $provider): array
    {
        $schemaObject = new ObjectSchema($schema);

        return match ($provider?->driver()) {
            // Mirrors Laravel AI's OpenRouter response_format envelope.
            'openrouter' => [
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => $schemaObject->name(),
                        'strict' => true,
                        'schema' => $schemaObject->toArray(),
                    ],
                ],
            ],
            default => $schemaObject->toArray(),
        };
    }
}
