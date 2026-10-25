<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Tokenizer Model
    |--------------------------------------------------------------------------
    |
    | This option controls the default model that will be used by the
    | tokenizer when no specific model is provided. You may set this
    | to any of the supported models defined below.
    |
    */
    'default_model' => env('TOKENIZER_MODEL', 'gpt-4o'),

    // Unknown model names use this encoding as a rough text estimate.
    // Only OpenAI model families have a known tiktoken encoding.
    'fallback_encoding' => 'cl100k_base',

    /*
    |--------------------------------------------------------------------------
    | Supported Models
    |--------------------------------------------------------------------------
    |
    | Here you may configure the models supported by the tokenizer along
    | with their corresponding encoding schemes. The encoding determines
    | how text is tokenized for that specific model.
    |
    | Supported encodings: cl100k_base, o200k_base
    |
    */
    'models' => [
        'gpt-4' => ['encoding' => 'cl100k_base'],
        'gpt-4o' => ['encoding' => 'o200k_base'],
        'gpt-4o-mini' => ['encoding' => 'o200k_base'],
        'gpt-4.1' => ['encoding' => 'o200k_base'],
        'gpt-5' => ['encoding' => 'o200k_base'],
        'o3' => ['encoding' => 'o200k_base'],
        'o4-mini' => ['encoding' => 'o200k_base'],
        'gpt-3.5-turbo' => ['encoding' => 'cl100k_base'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Message Overhead
    |--------------------------------------------------------------------------
    |
    | When sending messages to AI providers, each message incurs additional
    | token overhead from chat framing (special tokens like <|im_start|>,
    | role markers, <|im_end|>, etc.). This overhead is not captured by
    | plain text tokenization. Configure per-provider overhead here.
    |
    | Each provider entry supports:
    |   - per_message: tokens added per message (system, user, assistant, etc.)
    |   - per_request: base tokens added once per API request
    |   - per_name: extra tokens when a message has a "name" field (optional)
    |
    */
    /*
    |--------------------------------------------------------------------------
    | Attachment Token Estimation
    |--------------------------------------------------------------------------
    |
    | When attachments cannot be tokenized directly (binary files, inaccessible
    | content), token counts are estimated from file size. These ratios control
    | how many bytes are assumed to correspond to one token.
    |
    | - text_bytes_per_token: ~4 bytes/token for UTF-8 prose (English average)
    | - binary_bytes_per_token: ~20 bytes/token for binary formats (PDFs, images,
    |   DOCX, etc.) which compress poorly and have high overhead per token
    | - max_text_bytes: maximum bytes of text content to read and tokenize
    |   directly; content beyond this is estimated from size instead
    |
    */
    'attachments' => [
        'text_bytes_per_token' => 4,
        'binary_bytes_per_token' => 20,
        'max_text_bytes' => 200000,
    ],

    'message_overhead' => [
        'default' => [
            'per_message' => 4,
            'per_request' => 3,
            'per_name' => 1,
        ],

        'openai' => [
            'per_message' => 4,
            'per_request' => 3,
            'per_name' => 1,
        ],

        'anthropic' => [
            'per_message' => 3,
            'per_request' => 2,
            'per_name' => 0,
        ],

        'gemini' => [
            'per_message' => 4,
            'per_request' => 3,
            'per_name' => 0,
        ],

        'openrouter' => [
            'per_message' => 4,
            'per_request' => 3,
            'per_name' => 1,
        ],
    ],

];
