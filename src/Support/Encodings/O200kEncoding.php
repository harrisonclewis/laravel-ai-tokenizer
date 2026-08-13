<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\Support\Encodings;

use Yethee\Tiktoken\EncoderProvider;

class O200kEncoding implements EncodingInterface
{
    private \Yethee\Tiktoken\Encoder $encoder;

    public function __construct()
    {
        $provider = new EncoderProvider();
        $this->encoder = $provider->get('o200k_base');
    }

    public function encode(string $text): array
    {
        return $this->encoder->encode($text);
    }

    public function decode(array $tokens): string
    {
        return $this->encoder->decode($tokens);
    }

    public function decodeSingle(int $token): string
    {
        return $this->encoder->decode([$token]);
    }

    public function getName(): string
    {
        return 'o200k_base';
    }
}
