<?php

declare(strict_types=1);

use Harlew\Ai\Tokenizer\Tests\Fixtures\SchemaAnonymousAgent;
use Harlew\Ai\Tokenizer\Tests\Fixtures\TestTool;
use Harlew\Ai\Tokenizer\Tests\Fixtures\TokenizableAnonymousAgent;
use Harlew\Ai\Tokenizer\Tokenizer;
use Illuminate\Support\Collection;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Responses\Data\ToolResult;

it('matches manual baseline formula for a simple agent request', function (): void {
    $provider = 'openrouter';
    $model = 'gpt-4o';
    $prompt = "Hello, how are you today, I'm feeling fine, what about you?";
    $instructions = 'You are a helpful assistant.';

    $agent = new AnonymousAgent($instructions, [], []);
    $tokenizer = app(Tokenizer::class);

    $estimate = $tokenizer->forAgent($agent)->estimate($prompt, provider: $provider, model: $model);

    $overhead = config("ai.tokenizer.message_overhead.{$provider}");
    $expected = $tokenizer->count($instructions, $model, $provider)
        + $tokenizer->count($prompt, $model, $provider)
        + (($overhead['per_message'] * 2) + $overhead['per_request']);

    expect($estimate)->toBe($expected);
});

it('uses the first configured provider and its model from an agent failover list', function (): void {
    $agent = new class('', [], []) extends AnonymousAgent
    {
        public function provider(): array
        {
            return ['openai' => 'gpt-4', 'openrouter' => 'gpt-5'];
        }
    };

    $prompt = 'お誕生日おめでとう! 2026 🚀';
    $tokenizer = app(Tokenizer::class)->forAgent($agent);

    expect($tokenizer->estimate($prompt))->toBe($tokenizer->estimate($prompt, provider: 'openai', model: 'gpt-4'));
});

it('counts conversation history, attachments, and tool-result messages', function (): void {
    $provider = 'openrouter';
    $model = 'gpt-4o';
    $tokenizer = app(Tokenizer::class);

    $history = [
        new UserMessage('Prior user message.', [
            Document::fromString('Attached context from earlier turn.', 'text/plain')->as('history.txt'),
        ]),
        new AssistantMessage('Prior assistant response.'),
        new ToolResultMessage(new Collection([
            new ToolResult('tool-1', 'lookup', ['keyword' => 'revenue'], '{"value":"1.2M"}'),
        ])),
    ];

    $agent = new AnonymousAgent('You are a context-aware assistant.', $history, []);

    $withoutCurrentAttachments = $tokenizer->forAgent($agent)->estimate(
        'Use earlier context.',
        provider: $provider,
        model: $model
    );

    $withCurrentAttachments = $tokenizer->forAgent($agent)->estimate(
        'Use earlier context.',
        attachments: [Document::fromString('New attachment context.', 'text/plain')->as('current.txt')],
        provider: $provider,
        model: $model
    );

    expect($withoutCurrentAttachments)->toBeGreaterThan(0)
        ->and($withCurrentAttachments)->toBeGreaterThan($withoutCurrentAttachments);
});

it('counts each tool result as a separate provider message without SDK metadata', function (): void {
    $model = 'gpt-4o';
    $provider = 'openrouter';
    $tokenizer = app(Tokenizer::class);
    $results = new ToolResultMessage(new Collection([
        new ToolResult('call-a', 'lookup', ['unused' => 'metadata'], 'first result'),
        new ToolResult('call-b', 'lookup', ['unused' => 'metadata'], 'second result'),
    ]));

    $baseline = $tokenizer->forAgent(new AnonymousAgent('', [], []))->estimate('', provider: $provider, model: $model);
    $withResults = $tokenizer->forAgent(new AnonymousAgent('', [$results], []))->estimate('', provider: $provider, model: $model);

    $expectedDelta = $tokenizer->count('call-a', $model)
        + $tokenizer->count('first result', $model)
        + $tokenizer->count('call-b', $model)
        + $tokenizer->count('second result', $model)
        + 2 * config('ai.tokenizer.message_overhead.openrouter.per_message');

    expect($withResults - $baseline)->toBe($expectedDelta);
});

it('increases estimate when tools are enabled', function (): void {
    $provider = 'openrouter';
    $model = 'gpt-4o';
    $prompt = 'Find data and summarize it.';
    $instructions = 'You are a research assistant.';
    $tokenizer = app(Tokenizer::class);

    $noTools = new AnonymousAgent($instructions, [], []);
    $withTools = new AnonymousAgent($instructions, [], [
        new TestTool,
        new WebSearch(maxSearches: 2, allowedDomains: ['example.com']),
    ]);

    $base = $tokenizer->forAgent($noTools)->estimate($prompt, provider: $provider, model: $model);
    $withTooling = $tokenizer->forAgent($withTools)->estimate($prompt, provider: $provider, model: $model);

    expect($withTooling)->toBeGreaterThan($base);
});

it('increases estimate when structured output schema is enabled', function (): void {
    $provider = 'openrouter';
    $model = 'gpt-4o';
    $prompt = 'Return a concise JSON response.';
    $instructions = 'You are a structured assistant.';
    $tokenizer = app(Tokenizer::class);

    $plain = new AnonymousAgent($instructions, [], []);
    $structured = new SchemaAnonymousAgent($instructions, [], []);

    $plainEstimate = $tokenizer->forAgent($plain)->estimate($prompt, provider: $provider, model: $model);
    $structuredEstimate = $tokenizer->forAgent($structured)->estimate($prompt, provider: $provider, model: $model);

    expect($structuredEstimate)->toBeGreaterThan($plainEstimate);
});

it('tokenizable trait delegates to agent tokenizer estimate', function (): void {
    $provider = 'openrouter';
    $model = 'gpt-4o';
    $prompt = 'Keep this answer short.';
    $agent = new TokenizableAnonymousAgent('You are concise.', [], []);

    $tokenizer = app(Tokenizer::class);
    $fromTrait = $agent->tokenize($prompt, provider: $provider, model: $model);
    $fromService = $tokenizer->forAgent($agent)->estimate($prompt, provider: $provider, model: $model);

    expect($fromTrait)->toBe($fromService);
});

it('uses laravel ai provider-tool mapping when provider supports the tool', function (): void {
    $agent = new AnonymousAgent('You are concise.', [], [
        new WebSearch(maxSearches: 1, allowedDomains: ['example.com']),
    ]);

    $agentTokenizer = app(Tokenizer::class)->forAgent($agent);
    $serializeTools = new ReflectionMethod($agentTokenizer, 'serializeTools');
    $serializeTools->setAccessible(true);

    $serialized = $serializeTools->invoke(
        $agentTokenizer,
        $agent->tools(),
        Ai::textProviderFor($agent, 'openai')
    );

    expect($serialized)->toHaveCount(1)
        ->and($serialized[0]['type'])->toBe('web_search');
});

it('uses the OpenRouter gateway mapping for provider tools', function (): void {
    $tool = new WebSearch(maxSearches: 1, allowedDomains: ['example.com']);
    $agent = new AnonymousAgent('You are concise.', [], [
        $tool,
    ]);

    $agentTokenizer = app(Tokenizer::class)->forAgent($agent);
    $serializeTools = new ReflectionMethod($agentTokenizer, 'serializeTools');
    $serializeTools->setAccessible(true);

    $provider = Ai::textProviderFor($agent, 'openrouter');
    $serialized = $serializeTools->invoke(
        $agentTokenizer,
        $agent->tools(),
        $provider
    );

    expect($serialized)->toHaveCount(1)
        ->and($serialized[0]['type'])->toBe('openrouter:web_search')
        ->and($serialized[0])->toBe(['type' => 'openrouter:web_search', ...$provider->webSearchToolOptions($tool)]);
});
