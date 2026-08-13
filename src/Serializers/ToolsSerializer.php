<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\Serializers;

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Throwable;

class ToolsSerializer
{
    public function __construct(
        private ?ProviderToolSerializer $providerToolSerializer = null,
        private ?PayloadNormalizer $normalizer = null,
    ) {
        $this->providerToolSerializer ??= new ProviderToolSerializer;
        $this->normalizer ??= new PayloadNormalizer;
    }

    /**
     * Serialize agent tools into token-countable payloads.
     *
     * @param  iterable<mixed>  $tools
     * @return array<int, array<string, mixed>>
     */
    public function serialize(iterable $tools, ?Provider $provider): array
    {
        $serialized = [];

        foreach ($tools as $tool) {
            if ($tool instanceof ProviderTool) {
                $serialized[] = $this->providerToolSerializer->serialize($tool, $provider);

                continue;
            }

            if (! $tool instanceof Tool) {
                continue;
            }

            try {
                $schema = $tool->schema(new JsonSchemaTypeFactory);
            } catch (Throwable) {
                $schema = [];
            }

            $schema = $this->normalizer->normalize($schema);

            $serialized[] = array_filter([
                'type' => 'tool',
                'name' => $this->toolName($tool),
                'description' => (string) $tool->description(),
                'schema' => $schema !== [] ? $schema : null,
            ], static fn (mixed $value): bool => $value !== null);
        }

        return $serialized;
    }

    protected function toolName(Tool $tool): string
    {
        if (method_exists($tool, 'name')) {
            return (string) $tool->name();
        }

        return class_basename($tool);
    }
}
