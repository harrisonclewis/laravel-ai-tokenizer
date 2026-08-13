# Laravel AI Tokenizer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/harrisonclewis/laravel-ai-tokenizer.svg?style=flat-square)](https://packagist.org/packages/harrisonclewis/laravel-ai-tokenizer)
[![Total Downloads](https://img.shields.io/packagist/dt/harrisonclewis/laravel-ai-tokenizer.svg?style=flat-square)](https://packagist.org/packages/harrisonclewis/laravel-ai-tokenizer)

> This package is currently under development and testing and will be fully released soon.

Token counting and token-visualization helpers for Laravel AI SDK applications. Includes agent-aware estimation that mirrors Laravel AI message and tool payload construction.

## Installation

You can install the package via composer:

```bash
composer require harrisonclewis/laravel-ai-tokenizer
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="tokenizer-config"
```

## Usage

Implement `HasTokenization` and add the `Tokenizable` trait directly on your agent class.

```php
use Harlew\Ai\Tokenizer\Concerns\Tokenizable;
use Harlew\Ai\Tokenizer\Contracts\HasTokenization;
use Laravel\Ai\Agent;

class SalesCoach extends Agent implements HasTokenization
{
    use Tokenizable;

    // ...
}
```

Then call `tokenize()` directly on the agent instance:

```php
use Laravel\Ai\Files;

$tokens = $agent->tokenize(
    'Analyze the attached sales report',
    attachments: [
        Files\Document::fromStorage('sales-report.pdf'),
    ],
);
```

The estimate covers system instructions, conversation history, tools, structured output schema, the prompt, attachments, and provider message-framing overhead. Mirroring how the Laravel AI SDK constructs the actual request payload.

### Direct estimation

You can also use the facade directly for an agent:

```php
use Harlew\Ai\Tokenizer\Facades\Tokenizer;

// Fluent API — supports all options
$tokens = Tokenizer::forAgent($agent)->estimate(
    'Analyze the attached sales report',
    attachments: [Files\Document::fromStorage('sales-report.pdf')],
    provider: 'anthropic',
    model: 'claude-3-opus',
);

// Shorthand
$tokens = Tokenizer::agent($agent, 'Analyze the attached sales report');
```

### Count text tokens

```php
use Harlew\Ai\Tokenizer\Facades\Tokenizer;

$tokens = Tokenizer::count('Hello world', model: 'gpt-4o');
```

### Visualize tokens

```php
$tokenized = Tokenizer::visualize('Hello world', model: 'gpt-4o');

$tokenized->tokens;        // int
$tokenized->visualization; // array of token strings with whitespace markers (␣ ↵ ⇥)
```

`Tokenizer::tokenize(...)` is an alias of `Tokenizer::visualize(...)`.

### Estimate attachment tokens

```php
use Harlew\Ai\Tokenizer\Facades\Tokenizer;
use Laravel\Ai\Files;

$tokens = Tokenizer::attachments([
    Files\Image::fromPath('/tmp/chart.png'),
    Files\Document::fromPath('/tmp/report.md'),
]);
```

## Configuration

```php
return [
    'default_model' => env('TOKENIZER_MODEL', 'gpt-4'),

    'models' => [
        'gpt-4'         => ['encoding' => 'cl100k_base'],
        'gpt-4o'        => ['encoding' => 'o200k_base'],
        'gpt-4o-mini'   => ['encoding' => 'o200k_base'],
        'gpt-3.5-turbo' => ['encoding' => 'cl100k_base'],
        'claude-3-opus'   => ['encoding' => 'cl100k_base'],
        'claude-3-sonnet' => ['encoding' => 'cl100k_base'],
    ],

    // Bytes-per-token ratios used when content cannot be tokenized directly.
    // Text files use ~4 bytes/token (UTF-8 prose average).
    // Binary files use ~20 bytes/token (PDFs, images, DOCX etc.).
    'attachments' => [
        'text_bytes_per_token'   => 4,
        'binary_bytes_per_token' => 20,
        'max_text_bytes'         => 200000,
    ],

    // Per-provider token overhead added by chat framing (role markers, etc.).
    'message_overhead' => [
        'default'    => ['per_message' => 4, 'per_request' => 3, 'per_name' => 1],
        'openai'     => ['per_message' => 4, 'per_request' => 3, 'per_name' => 1],
        'anthropic'  => ['per_message' => 3, 'per_request' => 2, 'per_name' => 0],
        'gemini'     => ['per_message' => 4, 'per_request' => 3, 'per_name' => 0],
        'openrouter' => ['per_message' => 4, 'per_request' => 8, 'per_name' => 1],
    ],
];
```

## Testing

```bash
composer test
```

Run only live calibration tests:

```bash
vendor/bin/pest --group=live
```

Live tests are opt-in. Pass runtime env vars when invoking tests:

```bash
TOKENIZER_RUN_LIVE_TESTS=true \
TOKENIZER_LIVE_PROVIDER=openrouter \
TOKENIZER_LIVE_MODEL=nvidia/nemotron-3-nano-30b-a3b:free \
OPENROUTER_API_KEY=your-key \
vendor/bin/pest --group=live
```

PowerShell example:

```powershell
$env:TOKENIZER_RUN_LIVE_TESTS = 'true'
$env:TOKENIZER_LIVE_PROVIDER = 'openrouter'
$env:TOKENIZER_LIVE_MODEL = 'nvidia/nemotron-3-nano-30b-a3b:free'
$env:OPENROUTER_API_KEY = 'your-key'
vendor/bin/pest --group=live
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/harrisonclewis/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Harlew](https://github.com/harrisonclewis)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
