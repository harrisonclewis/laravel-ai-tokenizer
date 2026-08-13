<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\HasStructuredOutput;

class SchemaAnonymousAgent extends AnonymousAgent implements HasStructuredOutput
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'confidence' => $schema->number()->required(),
        ];
    }
}
