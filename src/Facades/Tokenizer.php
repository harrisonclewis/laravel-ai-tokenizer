<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\Facades;

use Harlew\Ai\Tokenizer\AgentTokenizer;
use Harlew\Ai\Tokenizer\ValueObjects\Tokenized;
use Illuminate\Support\Facades\Facade;
use Laravel\Ai\Contracts\Agent;

/**
 * @method static Tokenized tokenize(string $text, string|null $model = null, string|null $provider = null) Tokenize and visualize the given text.
 * @method static Tokenized visualize(string $text, string|null $model = null, string|null $provider = null) Tokenize with visual representation of each token.
 * @method static int count(string $text, string|null $model = null, string|null $provider = null) Count tokens in the given text.
 * @method static AgentTokenizer forAgent(Agent $agent) Get an AgentTokenizer instance.
 * @method static int agent(Agent $agent, string $prompt, array $attachments = [], string|null $provider = null, string|null $model = null) Estimate tokens for an agent request.
 * @method static int attachments(array $attachments, string|null $model = null, string|null $provider = null) Estimate tokens for a set of attachments.
 * @method static array<string, array<string, string>> models() Get supported models.
 *
 * @see \Harlew\Ai\Tokenizer\Tokenizer
 */
class Tokenizer extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \Harlew\Ai\Tokenizer\Tokenizer::class;
    }
}
