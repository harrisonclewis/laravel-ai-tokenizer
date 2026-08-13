<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\Serializers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Contracts\Files\HasContent;
use Laravel\Ai\Contracts\Files\HasMimeType;
use Laravel\Ai\Contracts\Files\HasName;
use Throwable;

class AttachmentSerializer
{
    /**
     * @param  array<int, mixed>  $attachments
     * @return array<int, array<string, mixed>>
     */
    public function serialize(array $attachments): array
    {
        $serialized = [];

        foreach ($attachments as $attachment) {
            $serialized[] = $this->serializeAttachment($attachment);
        }

        return $serialized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $payload
     */
    public function estimatedTokens(array $payload): int
    {
        $estimate = 0;

        foreach ($payload as $part) {
            $estimate += (int) ($part['estimated_tokens'] ?? 0);
        }

        return $estimate;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAttachment(mixed $attachment): array
    {
        $mimeType = $this->resolveMimeType($attachment);
        $name = $this->resolveName($attachment);
        $type = $this->resolveType($attachment);
        $textContent = $this->extractTextContent($attachment, $mimeType);

        if ($textContent !== null) {
            $truncatedBytes = (int) $textContent['truncated_bytes'];

            return array_filter([
                'type' => $type,
                'name' => $name,
                'mime_type' => $mimeType,
                'content' => $textContent['content'],
                'size_bytes' => $textContent['size_bytes'],
                'truncated_bytes' => $truncatedBytes > 0 ? $truncatedBytes : null,
                'estimated_tokens' => $truncatedBytes > 0
                    ? $this->estimateTokensFromSize($truncatedBytes, $mimeType)
                    : null,
            ], static fn (mixed $value): bool => $value !== null);
        }

        $sizeBytes = $this->resolveSizeBytes($attachment);

        return array_filter([
            'type' => $type,
            'name' => $name,
            'mime_type' => $mimeType,
            'size_bytes' => $sizeBytes,
            'estimated_tokens' => $sizeBytes !== null ? $this->estimateTokensFromSize($sizeBytes, $mimeType) : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function resolveType(mixed $attachment): string
    {
        if (is_object($attachment)) {
            return class_basename($attachment);
        }

        return gettype($attachment);
    }

    private function resolveName(mixed $attachment): ?string
    {
        if ($attachment instanceof UploadedFile) {
            return $attachment->getClientOriginalName();
        }

        if ($attachment instanceof HasName) {
            return $attachment->name();
        }

        return null;
    }

    private function resolveMimeType(mixed $attachment): ?string
    {
        if ($attachment instanceof UploadedFile) {
            return $attachment->getClientMimeType();
        }

        if ($attachment instanceof HasMimeType) {
            return $attachment->mimeType();
        }

        return null;
    }

    /**
     * @return array{content: string, size_bytes: int, truncated_bytes: int}|null
     */
    private function extractTextContent(mixed $attachment, ?string $mimeType): ?array
    {
        $content = null;

        if ($attachment instanceof UploadedFile) {
            $content = $attachment->getContent();
        } elseif ($attachment instanceof HasContent) {
            try {
                $content = $attachment->content();
            } catch (Throwable) {
                return null;
            }
        }

        if (! is_string($content) || $content === '') {
            return null;
        }

        if (is_string($mimeType) && $mimeType !== '' && ! $this->isTextMimeType($mimeType)) {
            return null;
        }

        if (($mimeType === null || $mimeType === '') && ! $this->looksLikeText($content)) {
            return null;
        }

        $sizeBytes = strlen($content);
        $truncatedBytes = 0;

        $maxTextBytes = (int) config('ai.tokenizer.attachments.max_text_bytes', 200000);

        if ($sizeBytes > $maxTextBytes) {
            $truncatedBytes = $sizeBytes - $maxTextBytes;
            $content = substr($content, 0, $maxTextBytes);
        }

        return [
            'content' => $content,
            'size_bytes' => $sizeBytes,
            'truncated_bytes' => $truncatedBytes,
        ];
    }

    private function isTextMimeType(?string $mimeType): bool
    {
        if (! is_string($mimeType) || $mimeType === '') {
            return false;
        }

        if (str_starts_with($mimeType, 'text/')) {
            return true;
        }

        return in_array($mimeType, [
            'application/json',
            'application/xml',
            'application/yaml',
            'application/x-yaml',
            'application/javascript',
            'application/x-javascript',
            'application/csv',
        ], true);
    }

    private function looksLikeText(string $content): bool
    {
        if (str_contains($content, "\0")) {
            return false;
        }

        $sample = substr($content, 0, 4096);
        $controlBytes = preg_match_all('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $sample);

        return $controlBytes !== false && $controlBytes <= 3;
    }

    private function resolveSizeBytes(mixed $attachment): ?int
    {
        if ($attachment instanceof UploadedFile) {
            $size = $attachment->getSize();

            return is_int($size) && $size > 0 ? $size : null;
        }

        if (! is_object($attachment)) {
            return null;
        }

        if (property_exists($attachment, 'path')) {
            try {
                $path = (string) $attachment->path;

                if ($path !== '' && property_exists($attachment, 'disk')) {
                    $size = Storage::disk($attachment->disk)->size($path);

                    return $size > 0 ? $size : null;
                }

                if ($path !== '' && is_file($path)) {
                    $size = filesize($path);

                    return is_int($size) && $size > 0 ? $size : null;
                }
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function estimateTokensFromSize(int $sizeBytes, ?string $mimeType): int
    {
        $bytesPerToken = $this->isTextMimeType($mimeType)
            ? (int) config('ai.tokenizer.attachments.text_bytes_per_token', 4)
            : (int) config('ai.tokenizer.attachments.binary_bytes_per_token', 20);

        return (int) ceil($sizeBytes / $bytesPerToken);
    }
}
