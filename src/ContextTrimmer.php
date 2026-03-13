<?php

declare(strict_types=1);

namespace Codechap\Yii3ContextTrimmer;

use InvalidArgumentException;

/**
 * Tokenizer-agnostic text preprocessor for trimming content to fit within
 * LLM context windows. Immutable — all configuration returns a new instance.
 */
final class ContextTrimmer implements ContextTrimmerInterface
{
    /** @var callable(string): list<string> */
    private $tokenizer;

    private int $maxTokens;
    private bool $removeDuplicateLines;
    private bool $removeShortWords;
    private int $minWordLength;
    private bool $removeExtraneous;

    /**
     * @param int $maxTokens Maximum tokens per segment.
     * @param bool $removeDuplicateLines Whether to remove duplicate lines.
     * @param bool $removeShortWords Whether to remove short words.
     * @param int $minWordLength Minimum word length to keep (when removeShortWords is enabled).
     * @param bool $removeExtraneous Whether to remove extraneous punctuation.
     * @param (callable(string): list<string>)|null $tokenizer Custom tokenizer or null for default.
     */
    public function __construct(
        int $maxTokens = 8192,
        bool $removeDuplicateLines = false,
        bool $removeShortWords = false,
        int $minWordLength = 2,
        bool $removeExtraneous = false,
        ?callable $tokenizer = null,
    ) {
        $this->maxTokens = $maxTokens;
        $this->removeDuplicateLines = $removeDuplicateLines;
        $this->removeShortWords = $removeShortWords;
        $this->minWordLength = $minWordLength;
        $this->removeExtraneous = $removeExtraneous;
        $this->tokenizer = $tokenizer ?? static function (string $text): array {
            /** @var list<string> */
            return preg_split('/\b|\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        };
    }

    public function withMaxTokens(int $maxTokens): self
    {
        $clone = clone $this;
        $clone->maxTokens = $maxTokens;

        return $clone;
    }

    public function withRemoveDuplicateLines(bool $remove): self
    {
        $clone = clone $this;
        $clone->removeDuplicateLines = $remove;

        return $clone;
    }

    public function withRemoveShortWords(bool $remove, int $minWordLength = 2): self
    {
        $clone = clone $this;
        $clone->removeShortWords = $remove;
        $clone->minWordLength = $minWordLength;

        return $clone;
    }

    public function withRemoveExtraneous(bool $remove): self
    {
        $clone = clone $this;
        $clone->removeExtraneous = $remove;

        return $clone;
    }

    public function withTokenizer(callable $tokenizer): self
    {
        $clone = clone $this;
        $clone->tokenizer = $tokenizer;

        return $clone;
    }

    public function trim(string $input): array
    {
        if ($this->maxTokens <= 0) {
            throw new InvalidArgumentException('Token limit must be greater than zero.');
        }

        if ($input === '' || trim($input) === '') {
            return [];
        }

        // Preprocessing pipeline
        if ($this->removeDuplicateLines) {
            $input = $this->deduplicateLines($input);
        }

        if ($this->removeShortWords) {
            $input = $this->filterShortWords($input, $this->minWordLength);
        }

        if ($this->removeExtraneous) {
            $input = $this->stripExtraneous($input);
        }

        $input = $this->compressWhitespace($input);

        // Single token mode
        if ($this->maxTokens === 1) {
            $tokens = ($this->tokenizer)($input);

            return array_values(array_filter(
                array_map('trim', $tokens),
                static fn(string $token): bool => $token !== '',
            ));
        }

        // If already within budget, return as-is
        if ($this->countTokens($input) <= $this->maxTokens) {
            return [$input];
        }

        return $this->segmentBySentence($input);
    }

    public function countTokens(string $text): int
    {
        if ($text === '' || trim($text) === '') {
            return 0;
        }

        return count(($this->tokenizer)($text));
    }

    /**
     * Remove duplicate lines, preserving order.
     */
    private function deduplicateLines(string $text): string
    {
        $lines = explode("\n", $text);

        return implode("\n", array_unique($lines));
    }

    /**
     * Remove purely-alphabetical words shorter than or equal to the minimum length.
     */
    private function filterShortWords(string $text, int $minWordLength): string
    {
        $tokens = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($tokens === false) {
            return $text;
        }

        $filtered = array_map(
            static fn(string $token): string => preg_match('/^\p{L}+$/u', $token) === 1 && mb_strlen($token) <= $minWordLength
                ? ''
                : $token,
            $tokens,
        );

        return trim((string) preg_replace('/\s+/', ' ', implode('', $filtered)));
    }

    /**
     * Remove extraneous punctuation (brackets, parens, braces, angle brackets, asterisks).
     */
    private function stripExtraneous(string $text): string
    {
        return (string) preg_replace('/[\[\]\(\)\{\}\<\>\*]/u', '', $text);
    }

    /**
     * Compress multiple whitespace characters into a single space.
     */
    private function compressWhitespace(string $text): string
    {
        return (string) preg_replace('/\s+/u', ' ', trim($text));
    }

    /**
     * Split text into segments by sentence boundaries, respecting the token budget.
     *
     * @return list<string>
     */
    private function segmentBySentence(string $input): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $input, -1, PREG_SPLIT_NO_EMPTY);

        if ($sentences === false) {
            return [$input];
        }

        /** @var list<string> $segments */
        $segments = [];
        $currentSegment = '';
        $currentTokenCount = 0;

        foreach ($sentences as $sentence) {
            $sentenceTokenCount = $this->countTokens($sentence);

            // Single sentence exceeds budget — break into individual tokens
            if ($sentenceTokenCount > $this->maxTokens) {
                if ($currentSegment !== '') {
                    $segments[] = trim($currentSegment);
                    $currentSegment = '';
                    $currentTokenCount = 0;
                }

                $tokens = ($this->tokenizer)($sentence);

                foreach ($tokens as $token) {
                    $trimmed = trim($token);
                    if ($trimmed !== '') {
                        $segments[] = $trimmed;
                    }
                }

                continue;
            }

            // Adding this sentence would exceed budget — start a new segment
            if ($currentTokenCount + $sentenceTokenCount > $this->maxTokens) {
                $segments[] = trim($currentSegment);
                $currentSegment = $sentence;
                $currentTokenCount = $sentenceTokenCount;
            } else {
                $currentSegment = $currentSegment === '' ? $sentence : $currentSegment . ' ' . $sentence;
                $currentTokenCount += $sentenceTokenCount;
            }
        }

        if ($currentSegment !== '') {
            $segments[] = trim($currentSegment);
        }

        return $segments;
    }
}
