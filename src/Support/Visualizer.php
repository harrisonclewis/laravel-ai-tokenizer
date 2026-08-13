<?php

declare(strict_types=1);

namespace Harlew\Ai\Tokenizer\Support;

/**
 * Formats tokens for visual display.
 *
 * This class handles the conversion of token bytes into human-readable
 * visual representations with special characters for whitespace and control chars.
 */
class Visualizer
{
    /**
     * Unicode character for open box (represents leading space).
     */
    private const SPACE_VISUAL = '␣';

    /**
     * Unicode character for carriage return (represents newline).
     */
    private const NEWLINE_VISUAL = '↵';

    /**
     * Unicode character for tab.
     */
    private const TAB_VISUAL = '⇥';

    /**
     * Middle dot for other control characters.
     */
    private const CONTROL_VISUAL = '·';

    /**
     * Format a single token for visual display.
     *
     * @param int $id The token ID
     * @param string $bytes The byte representation of the token
     * @return array<string, mixed> The formatted token data
     */
    public function formatToken(int $id, string $bytes): array
    {
        $isSpace = $this->hasLeadingSpace($bytes);
        $visual = $this->formatBytes($bytes);

        return [
            'id' => $id,
            'bytes' => $bytes,
            'visual' => $visual,
            'is_space' => $isSpace,
        ];
    }

    /**
     * Check if the bytes start with a space character.
     *
     * @param string $bytes The byte string to check
     * @return bool True if the string starts with a space
     */
    private function hasLeadingSpace(string $bytes): bool
    {
        return str_starts_with($bytes, ' ');
    }

    /**
     * Format bytes for visual display.
     *
     * Rules:
     * - Leading space is replaced with ␣ (U+2423)
     * - Newlines \n are replaced with ↵
     * - Tabs \t are replaced with ⇥
     * - Other control chars are replaced with · (middle dot)
     * - All other visible text is kept as-is
     *
     * @param string $bytes The bytes to format
     * @return string The formatted visual representation
     */
    private function formatBytes(string $bytes): string
    {
        $result = '';
        $chars = mb_str_split($bytes);
        $isFirst = true;

        foreach ($chars as $char) {
            $result .= $this->formatCharacter($char, $isFirst);
            $isFirst = false;
        }

        return $result;
    }

    /**
     * Format a single character according to visualization rules.
     *
     * @param string $char The character to format
     * @param bool $isFirst Whether this is the first character
     * @return string The formatted character
     */
    private function formatCharacter(string $char, bool $isFirst): string
    {
        $ord = ord($char);

        // Handle leading space specially
        if ($isFirst && $char === ' ') {
            return self::SPACE_VISUAL;
        }

        // Handle newline
        if ($char === "\n") {
            return self::NEWLINE_VISUAL;
        }

        // Handle carriage return (part of Windows newlines)
        if ($char === "\r") {
            return self::NEWLINE_VISUAL;
        }

        // Handle tab
        if ($char === "\t") {
            return self::TAB_VISUAL;
        }

        // Handle other control characters (0-31 and 127)
        if (($ord >= 0 && $ord <= 31) || $ord === 127) {
            return self::CONTROL_VISUAL;
        }

        // Return visible character as-is
        return $char;
    }
}
