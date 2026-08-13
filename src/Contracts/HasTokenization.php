<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\Contracts;

/**
 * Contract for agents that support tokenization.
 *
 * Implement this interface on an agent class to enable direct tokenization:
 * $agent->tokenize($prompt, $attachments, $provider, $model)
 */
interface HasTokenization
{
    /**
     * Estimate the total token count for a prompt with the agent's context.
     *
     * This includes system instructions, conversation history (if Conversational),
     * available tools (if HasTools), the prompt, file attachments, and message
     * framing overhead for the provider.
     *
     * @param string $prompt The prompt to tokenize
     * @param array $attachments Array of Laravel AI attachments
     * @param string|null $provider The provider to use (optional, uses agent's default)
     * @param string|null $model The model to use (optional, uses agent's default)
     * @return int The estimated token count
     */
    public function tokenize(string $prompt, array $attachments = [], ?string $provider = null, ?string $model = null): int;
}
