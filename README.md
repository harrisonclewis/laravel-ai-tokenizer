# Laravel AI Tokenizer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/harrisonclewis/laravel-ai-tokenizer.svg?style=flat-square)](https://packagist.org/packages/harrisonclewis/laravel-ai-tokenizer)
[![Total Downloads](https://img.shields.io/packagist/dt/harrisonclewis/laravel-ai-tokenizer.svg?style=flat-square)](https://packagist.org/packages/harrisonclewis/laravel-ai-tokenizer)

> This package is currently under development and testing and will be fully released soon.

Text token counting and input-token estimation for Laravel AI SDK applications. Agent estimates include instructions, history, tools, schemas, and attachments. They are estimates: providers can add tokens for request structure and process images and files in ways a local tokenizer cannot reproduce.

## Installation

You can install the package via composer:

```bash
composer require harrisonclewis/laravel-ai-tokenizer
```

Requires PHP 8.3+, Laravel 12 or 13, and Laravel AI SDK 0.10 or 1.x.

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

`tokenize()` estimates **input tokens for the first model request**. It does not predict the tokens the model will generate. When an agent calls tools, later requests include additional context and the response usage may cover several requests.

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

OpenAI model families use their corresponding `cl100k_base` or `o200k_base` encoding, including versioned names and `openai/` OpenRouter aliases. Unknown models use the configurable fallback encoding, which is only a rough text estimate. In particular, tiktoken is not a native Claude or Gemini tokenizer.

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

Text attachment content is tokenized directly. Binary attachments use a configurable size heuristic; image resolution, provider preprocessing, remote file parsing, and provider file references can make that estimate substantially different from billed usage.

### Compare with provider usage

```php
$estimate = Tokenizer::forAgent($agent)->estimate($prompt, provider: 'openai', model: 'gpt-4o');
$response = $agent->prompt($prompt, provider: 'openai', model: 'gpt-4o');

// Laravel AI 1.x: compare with the first request, not the whole tool loop.
$actualInput = data_get($response, 'steps.0.usage.inputTokens')
    ?? $response->usage->inputTokens;
```

For Laravel AI 0.10, use `promptTokens` instead of `inputTokens`. Anthropic and Bedrock 0.10 usage reports cached input separately, so add `cacheReadInputTokens` and `cacheWriteInputTokens` for those providers. Do not add cached input again on Laravel AI 1.x; it is already part of `inputTokens`.

Visible response text is not a reliable measure of output usage. Reasoning and message-format tokens may be included in the provider's `outputTokens` even when absent from `$response->text`.

## Configuration

```php
return [
    'default_model' => env('TOKENIZER_MODEL', 'gpt-4o'),
    'fallback_encoding' => 'cl100k_base',

    'models' => [
        'gpt-4'         => ['encoding' => 'cl100k_base'],
        'gpt-4o'        => ['encoding' => 'o200k_base'],
        'gpt-4o-mini'   => ['encoding' => 'o200k_base'],
        'gpt-4.1'       => ['encoding' => 'o200k_base'],
        'gpt-5'         => ['encoding' => 'o200k_base'],
        'o3'            => ['encoding' => 'o200k_base'],
        'o4-mini'       => ['encoding' => 'o200k_base'],
        'gpt-3.5-turbo' => ['encoding' => 'cl100k_base'],
    ],

    // Bytes-per-token ratios used when content cannot be tokenized directly.
    // Text files use ~4 bytes/token (UTF-8 prose average).
    // Binary files use a rough ~20 bytes/token fallback, not a provider count.
    'attachments' => [
        'text_bytes_per_token'   => 4,
        'binary_bytes_per_token' => 20,
        'max_text_bytes'         => 200000,
    ],

    // Configurable per-provider chat-framing estimates.
    'message_overhead' => [
        'default'    => ['per_message' => 4, 'per_request' => 3, 'per_name' => 1],
        'openai'     => ['per_message' => 4, 'per_request' => 3, 'per_name' => 1],
        'anthropic'  => ['per_message' => 3, 'per_request' => 2, 'per_name' => 0],
        'gemini'     => ['per_message' => 4, 'per_request' => 3, 'per_name' => 0],
        'openrouter' => ['per_message' => 4, 'per_request' => 3, 'per_name' => 1],
    ],
];
```

## Testing

```bash
composer test
```

Run the OpenRouter calibration with an API key. The command makes three small requests: a simple prompt, conversation history, and a prompt with a tool and structured schema. It prints the estimate, reported input usage for the first request when available, signed difference, provider output usage, and token count of the visible response text. It also saves JSON Lines results to a temporary report path shown at the end. The report records whether usage came from the first step or the aggregate response.

```bash
TOKENIZER_LIVE_KEY_FILE=/absolute/path/to/openrouter.key composer test:live
```

The key file can live outside this repository. You can also set `OPENROUTER_API_KEY` in the environment instead. The default model is `openai/gpt-4o-mini`; set `TOKENIZER_LIVE_MODEL` to compare another OpenRouter model. A positive delta means the package overestimated the input count. Each scenario fails its live test if the absolute difference exceeds its configured tolerance, while the comparison is still written to the report.

To include a text document attachment, enable the additional scenario:

```bash
TOKENIZER_LIVE_KEY_FILE=/absolute/path/to/openrouter.key \
TOKENIZER_LIVE_INCLUDE_ATTACHMENTS=true \
composer test:live
```

PowerShell:

```powershell
$env:TOKENIZER_LIVE_KEY_FILE = 'C:\path\to\openrouter.key'
composer test:live
```

`composer test` runs the non-live suite and makes no provider requests. You can set `TOKENIZER_LIVE_REPORT` to choose the JSON Lines output path and `TOKENIZER_LIVE_MAX_DELTA_SIMPLE`, `TOKENIZER_LIVE_MAX_DELTA_HISTORY`, `TOKENIZER_LIVE_MAX_DELTA_RICH`, or `TOKENIZER_LIVE_MAX_DELTA_ATTACHMENT` to adjust the allowed token differences.

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
