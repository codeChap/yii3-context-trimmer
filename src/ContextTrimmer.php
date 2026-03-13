<?php

declare(strict_types=1);

namespace Codechap\Yii3ContextTrimmer;

use Codechap\Yii3ContextTrimmer\Exception\InvalidTokenLimitException;

use function array_filter;
use function array_values;
use function count;
use function explode;
use function implode;
use function mb_strlen;
use function preg_match;
use function preg_replace;
use function preg_split;
use function sprintf;
use function trim;

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
    private bool $compressWhitespace;

    /**
     * @param int $maxTokens Maximum tokens per segment (must be >= 2).
     * @param bool $removeDuplicateLines Whether to remove duplicate lines.
     * @param bool $removeShortWords Whether to remove short words.
     * @param int $minWordLength Minimum word length to keep (words shorter than this are removed).
     * @param bool $removeExtraneous Whether to remove extraneous punctuation.
     * @param bool $compressWhitespace Whether to compress multiple whitespace into single spaces.
     * @param (callable(string): list<string>)|null $tokenizer Custom tokenizer or null for default.
     *        The default tokenizer splits on spaces — a rough heuristic. For accurate token counting,
     *        provide a tokenizer matching your LLM's tokenization (e.g. tiktoken for OpenAI models).
     *
     * @throws InvalidTokenLimitException If $maxTokens is less than 2.
     */
    public function __construct(
        int $maxTokens = 8192,
        bool $removeDuplicateLines = false,
        bool $removeShortWords = false,
        int $minWordLength = 2,
        bool $removeExtraneous = false,
        bool $compressWhitespace = true,
        ?callable $tokenizer = null,
    ) {
        if ($maxTokens < 2) {
            throw new InvalidTokenLimitException($maxTokens);
        }

        if ($minWordLength < 1) {
            throw new \InvalidArgumentException(
                sprintf('Minimum word length must be at least 1, %d given.', $minWordLength),
            );
        }

        $this->maxTokens = $maxTokens;
        $this->removeDuplicateLines = $removeDuplicateLines;
        $this->removeShortWords = $removeShortWords;
        $this->minWordLength = $minWordLength;
        $this->removeExtraneous = $removeExtraneous;
        $this->compressWhitespace = $compressWhitespace;
        $this->tokenizer = $tokenizer ?? static function (string $text): array {
            /** @var list<string> */
            return array_values(array_filter(
                explode(' ', $text),
                static fn(string $token): bool => $token !== '',
            ));
        };
    }

    /**
     * Return a new instance with the given max token limit per segment.
     *
     * @throws InvalidTokenLimitException If $maxTokens is less than 2.
     */
    public function withMaxTokens(int $maxTokens): static
    {
        if ($maxTokens < 2) {
            throw new InvalidTokenLimitException($maxTokens);
        }

        $clone = clone $this;
        $clone->maxTokens = $maxTokens;

        return $clone;
    }

    /**
     * Return a new instance with duplicate line removal enabled or disabled.
     *
     * Only non-blank duplicate lines are removed; blank lines are preserved
     * as structural separators.
     */
    public function withRemoveDuplicateLines(bool $remove): static
    {
        $clone = clone $this;
        $clone->removeDuplicateLines = $remove;

        return $clone;
    }

    /**
     * Return a new instance with short word removal enabled or disabled.
     *
     * WARNING: This aggressively removes all purely-alphabetical words shorter
     * than $minWordLength. Common words like "I", "a", "of", "in", "to" will
     * be removed, which may impair readability. Use with caution — this is
     * intended for token-budget optimization, not human-readable output.
     *
     * @param bool $remove Whether to enable short word removal.
     * @param int $minWordLength Minimum word length to keep (words shorter than this are removed).
     */
    public function withRemoveShortWords(bool $remove, int $minWordLength = 2): static
    {
        if ($minWordLength < 1) {
            throw new \InvalidArgumentException(
                sprintf('Minimum word length must be at least 1, %d given.', $minWordLength),
            );
        }

        $clone = clone $this;
        $clone->removeShortWords = $remove;
        $clone->minWordLength = $minWordLength;

        return $clone;
    }

    /**
     * Return a new instance with extraneous character removal enabled or disabled.
     *
     * WARNING: This removes brackets [], parentheses (), braces {}, angle brackets <>,
     * and asterisks *. This is aggressive and will destroy Markdown formatting, HTML tags,
     * and code syntax. Consider whether your use case can tolerate this data loss.
     */
    public function withRemoveExtraneous(bool $remove): static
    {
        $clone = clone $this;
        $clone->removeExtraneous = $remove;

        return $clone;
    }

    /**
     * Return a new instance with whitespace compression enabled or disabled.
     *
     * When enabled (default), multiple whitespace characters are collapsed into
     * a single space. Disable this to preserve original whitespace (e.g. for code).
     */
    public function withCompressWhitespace(bool $compress): static
    {
        $clone = clone $this;
        $clone->compressWhitespace = $compress;

        return $clone;
    }

    /**
     * Return a new instance with the given tokenizer callable.
     *
     * The default tokenizer splits on spaces, which is a rough heuristic. For
     * accurate token counting, provide a tokenizer that matches your LLM's
     * tokenization (e.g., tiktoken for OpenAI models).
     *
     * @param callable(string): list<string> $tokenizer
     */
    public function withTokenizer(callable $tokenizer): static
    {
        $clone = clone $this;
        $clone->tokenizer = $tokenizer;

        return $clone;
    }

    public function trim(string $input): array
    {
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

        if ($this->compressWhitespace) {
            $input = $this->compressWhitespaceText($input);
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
     * Remove duplicate non-blank lines, preserving order and blank lines as structural separators.
     */
    private function deduplicateLines(string $text): string
    {
        $lines = explode("\n", $text);
        $seen = [];
        $result = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                $result[] = $line;
                continue;
            }
            if (!isset($seen[$line])) {
                $seen[$line] = true;
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }

    /**
     * Remove purely-alphabetical words shorter than the minimum length.
     */
    private function filterShortWords(string $text, int $minWordLength): string
    {
        $tokens = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($tokens === false) {
            return $text;
        }

        $filtered = [];
        $prevWasRemoved = false;

        foreach ($tokens as $token) {
            $isShortWord = preg_match('/^\p{L}+$/u', $token) === 1 && mb_strlen($token) < $minWordLength;

            if ($isShortWord) {
                $prevWasRemoved = true;
                continue;
            }

            // Skip whitespace delimiter that followed a removed word
            if ($prevWasRemoved && preg_match('/^\s+$/u', $token) === 1) {
                $prevWasRemoved = false;
                continue;
            }

            $prevWasRemoved = false;
            $filtered[] = $token;
        }

        return trim(implode('', $filtered));
    }

    /**
     * Remove extraneous punctuation (brackets, parens, braces, angle brackets, asterisks).
     *
     * WARNING: This is aggressive and will destroy Markdown formatting, HTML tags, and code syntax.
     */
    private function stripExtraneous(string $text): string
    {
        $result = preg_replace('/[\[\]()\{}<>*]/u', '', $text);

        return $result !== null ? $result : $text;
    }

    /**
     * Compress multiple whitespace characters into a single space.
     */
    private function compressWhitespaceText(string $text): string
    {
        $result = preg_replace('/\s+/u', ' ', trim($text));

        return $result !== null ? $result : $text;
    }

    /**
     * Split text into segments by sentence boundaries, respecting the token budget.
     *
     * Uses negative lookbehinds for common abbreviations and decimal numbers to avoid
     * false sentence breaks. This is a best-effort heuristic — for complex text, consider
     * preprocessing with a dedicated sentence tokenizer.
     *
     * @return list<string>
     */
    private function segmentBySentence(string $input): array
    {
        $sentences = preg_split(
            '/'
            // Common titles
            . '(?<!Mr)(?<!Mrs)(?<!Ms)(?<!Dr)(?<!Prof)(?<!Sr)(?<!Jr)(?<!Rev)'
            // Honorifics and address
            . '(?<!St)(?<!Ave)(?<!Blvd)(?<!Dept)(?<!Gen)(?<!Gov)(?<!Sgt)(?<!Cpl)'
            // Academic and professional
            . '(?<!Ph)(?<!Vol)(?<!Fig)(?<!Eq)(?<!No)'
            // Common Latin/English abbreviations
            . '(?<!vs)(?<!etc)(?<!approx)(?<!i\.e)(?<!e\.g)'
            // Corporate suffixes
            . '(?<!Inc)(?<!Ltd)(?<!Corp)(?<!Co)(?<!Jr)'
            // Single uppercase letter abbreviations (e.g. U.S., A.M., P.O.)
            . '(?<!\b\p{Lu})'
            // Decimal numbers
            . '(?<![0-9])'
            // The actual sentence boundary
            . '(?<=[.!?])\s+/',
            $input,
            -1,
            PREG_SPLIT_NO_EMPTY,
        );

        if ($sentences === false) {
            return [$input];
        }

        /** @var list<string> $segments */
        $segments = [];
        $currentSegment = '';

        foreach ($sentences as $sentence) {
            $sentenceTokenCount = $this->countTokens($sentence);

            // Single sentence exceeds budget — break into individual tokens
            if ($sentenceTokenCount > $this->maxTokens) {
                if ($currentSegment !== '') {
                    $segments[] = trim($currentSegment);
                    $currentSegment = '';
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

            // Recount on the actual concatenated candidate to ensure accuracy
            $candidateSegment = $currentSegment === '' ? $sentence : $currentSegment . ' ' . $sentence;
            $candidateTokenCount = $this->countTokens($candidateSegment);

            if ($candidateTokenCount > $this->maxTokens) {
                $segments[] = trim($currentSegment);
                $currentSegment = $sentence;
            } else {
                $currentSegment = $candidateSegment;
            }
        }

        if ($currentSegment !== '') {
            $segments[] = trim($currentSegment);
        }

        return $segments;
    }
}
