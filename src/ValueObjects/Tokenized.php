<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\ValueObjects;

use JsonSerializable;

readonly class Tokenized implements JsonSerializable
{
    public function __construct(
        public int $tokens,
        public array $visualization = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'tokens' => $this->tokens,
            'visualization' => $this->visualization,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
