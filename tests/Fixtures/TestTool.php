<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class TestTool implements Tool
{
    public function description(): string
    {
        return 'Look up a simple value from a keyword.';
    }

    public function handle(Request $request): string
    {
        $keyword = (string) ($request['keyword'] ?? 'unknown');

        return json_encode([
            'keyword' => $keyword,
            'value' => 'resolved',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"value":"resolved"}';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema
                ->string()
                ->description('Keyword to look up.')
                ->required(),
        ];
    }
}
