<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\Serializers;

use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Providers\Tools\ProviderTool;

class ProviderToolSerializer
{
    public function __construct(
        private ?LaravelAiProviderToolSerializer $laravelAiSerializer = null,
        private ?PayloadNormalizer $normalizer = null,
    ) {
        $this->laravelAiSerializer ??= new LaravelAiProviderToolSerializer;
        $this->normalizer ??= new PayloadNormalizer;
    }

    /**
     * Serialize a provider tool into a token-countable payload.
     */
    public function serialize(ProviderTool $tool, ?Provider $provider): array
    {
        if ($provider !== null) {
            $payloads = $this->laravelAiSerializer->serialize($provider, [$tool]);

            if ($payloads !== []) {
                return $this->normalizer->normalize($payloads[0]);
            }
        }

        return $this->fallbackPayload($tool);
    }

    protected function fallbackPayload(ProviderTool $tool): array
    {
        $options = array_filter(
            get_object_vars($tool),
            static fn (mixed $value): bool => $value !== null && $value !== []
        );

        return array_filter([
            'type' => 'provider_tool',
            'name' => class_basename($tool),
            'options' => $options !== [] ? $options : null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
