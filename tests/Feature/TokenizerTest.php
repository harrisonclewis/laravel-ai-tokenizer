<?php

declare(strict_types=1);

use Harlew\Ai\Tokenizer\Serializers\AttachmentSerializer;
use Harlew\Ai\Tokenizer\Tokenizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Yethee\Tiktoken\EncoderProvider;

it('counts and visualizes tokens consistently', function (): void {
    $tokenizer = app(Tokenizer::class);
    $text = 'Hello world';

    $count = $tokenizer->count($text, 'gpt-4');
    $visualized = $tokenizer->visualize($text, 'gpt-4');
    $tokenized = $tokenizer->tokenize($text, 'gpt-4');

    expect($count)->toBe(2)
        ->and($visualized->tokens)->toBe($count)
        ->and($tokenized->tokens)->toBe($count)
        ->and($visualized->visualization)->not->toBeEmpty();
});

it('selects the o200k encoding for current OpenAI models and OpenRouter aliases', function (): void {
    $text = 'お誕生日おめでとう! 2026 🚀';
    $expected = count((new EncoderProvider)->get('o200k_base')->encode($text));
    $tokenizer = app(Tokenizer::class);

    expect($tokenizer->count($text, 'gpt-5'))->toBe($expected)
        ->and($tokenizer->count($text, 'gpt-4.1'))->toBe($expected)
        ->and($tokenizer->count($text, 'openai/gpt-4o'))->toBe($expected);
});

it('rejects unknown configured encodings instead of silently using a different one', function (): void {
    config()->set('ai.tokenizer.models.custom-model', ['encoding' => 'does_not_exist']);

    app(Tokenizer::class)->count('Hello', 'custom-model');
})->throws(InvalidArgumentException::class, 'Unsupported tokenizer encoding');

it('returns configured model map', function (): void {
    $models = app(Tokenizer::class)->models();

    expect($models)->toBeArray()
        ->and($models)->toHaveKey('gpt-4o')
        ->and($models['gpt-4o']['encoding'])->toBe('o200k_base');
});

it('counts attachment payloads for laravel ai files and uploaded files', function (): void {
    $tokenizer = app(Tokenizer::class);

    $tmpPath = tempnam(sys_get_temp_dir(), 'tok-');
    file_put_contents($tmpPath, 'uploaded attachment body');

    $uploaded = new UploadedFile($tmpPath, 'note.txt', 'text/plain', null, true);

    try {
        $tokens = $tokenizer->attachments([
            Document::fromString('A text document body for tokenization.', 'text/plain')->as('doc.txt'),
            Image::fromBase64(base64_encode('fake-image-bytes'), 'image/png')->as('chart.png'),
            $uploaded,
        ], 'gpt-4o', 'openrouter');
    } finally {
        @unlink($tmpPath);
    }

    expect($tokens)->toBeInt()
        ->and($tokens)->toBeGreaterThan(0);
});

it('returns zero for empty attachments array', function (): void {
    $tokens = app(Tokenizer::class)->attachments([], 'gpt-4o', 'openrouter');

    expect($tokens)->toBe(0);
});

it('attachment serializer includes text content for text documents', function (): void {
    $payload = (new AttachmentSerializer)->serialize([
        Document::fromString('Attachment body.', 'text/plain')->as('report.txt'),
    ]);

    expect($payload)->toBeArray()
        ->and($payload[0])->toHaveKey('content')
        ->and($payload[0]['content'])->toContain('Attachment body.');
});

it('attachment serializer includes content for stored text documents', function (): void {
    Storage::fake('tokenizer-tests');
    Storage::disk('tokenizer-tests')->put('report.txt', 'Stored attachment body.');

    $payload = (new AttachmentSerializer)->serialize([
        Document::fromStorage('report.txt', 'tokenizer-tests')->as('report.txt'),
    ]);

    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    expect($payload)->toBeArray()
        ->and($encoded)->toContain('Stored attachment body.')
        ->and($payload[0])->toHaveKey('content');
});

it('attachment serializer compensates token estimate for truncated text content', function (): void {
    $content = str_repeat('a', 210000);
    $payload = (new AttachmentSerializer)->serialize([
        Document::fromString($content, 'text/plain')->as('large.txt'),
    ]);

    expect($payload)->toBeArray()
        ->and($payload[0]['size_bytes'])->toBe(210000)
        ->and(strlen($payload[0]['content']))->toBe(200000)
        ->and($payload[0]['truncated_bytes'])->toBe(10000)
        ->and($payload[0]['estimated_tokens'])->toBe(2500);
});

it('attachment serializer falls back to size-based token estimate for binary files', function (): void {
    Storage::fake('tokenizer-tests');
    Storage::disk('tokenizer-tests')->put('blob.bin', random_bytes(512));

    $attachment = Document::fromStorage('blob.bin', 'tokenizer-tests')->as('blob.bin');
    $payload = (new AttachmentSerializer)->serialize([$attachment]);

    expect($payload)->toBeArray()
        ->and($payload[0])->toHaveKey('estimated_tokens')
        ->and($payload[0])->toHaveKey('size_bytes')
        ->and($payload[0])->not->toHaveKey('content')
        ->and($payload[0]['estimated_tokens'])->toBeGreaterThan(0);
});

it('counts attachment content without invented serialization metadata', function (): void {
    Storage::fake('tokenizer-tests');
    Storage::disk('tokenizer-tests')->put('blob.bin', random_bytes(1024));

    $attachment = Document::fromStorage('blob.bin', 'tokenizer-tests')->as('blob.bin');
    $tokenizer = app(Tokenizer::class);
    $serializer = new AttachmentSerializer;

    $payload = $serializer->serialize([$attachment]);
    $attachmentTokens = $tokenizer->attachments([$attachment], 'gpt-4o', 'openrouter');

    expect($attachmentTokens)->toBe($payload[0]['estimated_tokens'] + $tokenizer->count('blob.bin', 'gpt-4o'));
});

it('counts text attachment content directly', function (): void {
    $tokenizer = app(Tokenizer::class);
    $body = 'A short report with numbers 123 and 456.';

    $actual = $tokenizer->attachments([
        Document::fromString($body, 'text/plain')->as('report.txt'),
    ], 'gpt-4o', 'openrouter');

    expect($actual)->toBe(
        $tokenizer->count($body, 'gpt-4o') + $tokenizer->count('report.txt', 'gpt-4o')
    );
});
