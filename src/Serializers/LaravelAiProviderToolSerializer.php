<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\Serializers;

use Laravel\Ai\Providers\Provider;
use ReflectionMethod;
use RuntimeException;

class LaravelAiProviderToolSerializer
{
    /**
     * Serialize tools with the same gateway mapper used by Laravel AI.
     *
     * @param  array<int, mixed>  $tools
     * @return array<int, array<string, mixed>>
     */
    public function serialize(Provider $provider, array $tools): array
    {
        if (! method_exists($provider, 'textGateway')) {
            throw new RuntimeException('The selected Laravel AI provider has no text gateway.');
        }

        $gateway = $provider->textGateway();

        if (! method_exists($gateway, 'mapTools')) {
            throw new RuntimeException('The selected Laravel AI gateway has no tool mapper.');
        }

        $mapper = new ReflectionMethod($gateway, 'mapTools');

        return $mapper->getNumberOfParameters() > 1
            ? $mapper->invoke($gateway, $tools, $provider)
            : $mapper->invoke($gateway, $tools);
    }
}
