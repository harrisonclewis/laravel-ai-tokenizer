<?php

declare(strict_types=1);

use Harlew\Ai\Tokenizer\Serializers\StructuredOutputSchemaSerializer;
use Harlew\Ai\Tokenizer\Tests\Fixtures\SchemaAnonymousAgent;
use Laravel\Ai\Ai;

it('serializes structured schema using openrouter request envelope', function (): void {
    $agent = new SchemaAnonymousAgent('You are a precise analyst.', [], []);
    $provider = Ai::textProviderFor($agent, 'openrouter');

    $payload = (new StructuredOutputSchemaSerializer)->serialize($agent, $provider);

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKey('structured_outputs', true)
        ->and(data_get($payload, 'response_format.type'))->toBe('json_schema')
        ->and(data_get($payload, 'response_format.json_schema.name'))->toBe('schema_definition')
        ->and(data_get($payload, 'response_format.json_schema.strict'))->toBeTrue()
        ->and(data_get($payload, 'response_format.json_schema.schema.type'))->toBe('object');
});

it('serializes default structured schema as object schema when provider is not given', function (): void {
    $agent = new SchemaAnonymousAgent('You are a precise analyst.', [], []);

    $payload = (new StructuredOutputSchemaSerializer)->serialize($agent);

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKey('type', 'object')
        ->and($payload)->toHaveKey('properties')
        ->and($payload['properties'])->toHaveKey('summary')
        ->and($payload['properties'])->toHaveKey('confidence');
});

