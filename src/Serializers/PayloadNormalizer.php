<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\Serializers;

class PayloadNormalizer
{
    public function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $this->normalize($value->toArray());
        }

        return $value;
    }
}
