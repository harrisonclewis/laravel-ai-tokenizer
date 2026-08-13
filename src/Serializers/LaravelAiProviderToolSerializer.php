<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\Serializers;

use Laravel\Ai\Gateway\Prism\Concerns\AddsToolsToPrismRequests;
use Laravel\Ai\Providers\Provider;
use Prism\Prism\ValueObjects\ProviderTool as PrismProviderTool;
use Throwable;

class LaravelAiProviderToolSerializer
{
    use AddsToolsToPrismRequests;

    /**
     * Serialize provider tools using Laravel AI's own Prism mapping.
     *
     * @param  array<int, mixed>  $tools
     * @return array<int, array<string, mixed>>
     */
    public function serialize(Provider $provider, array $tools): array
    {
        $collector = new class
        {
            /**
             * @var array<int, PrismProviderTool>
             */
            public array $providerTools = [];

            /**
             * @param  array<int, PrismProviderTool>  $providerTools
             */
            public function withProviderTools(array $providerTools): self
            {
                $this->providerTools = $providerTools;

                return $this;
            }
        };

        try {
            $this->addProviderTools($provider, $collector, $tools);
        } catch (Throwable) {
            return [];
        }

        return array_map(
            static fn (PrismProviderTool $tool): array => array_filter([
                'type' => 'provider_tool',
                'provider_type' => $tool->type,
                'name' => $tool->name,
                'options' => $tool->options !== [] ? $tool->options : null,
            ], static fn (mixed $value): bool => $value !== null),
            $collector->providerTools
        );
    }
}
