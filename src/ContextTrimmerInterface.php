<?php

declare(strict_types=1);

namespace Codechap\Yii3ContextTrimmer;

/**
 * Interface for tokenizer-agnostic text preprocessing to trim content
 * for LLM context windows.
 */
interface ContextTrimmerInterface
{
    /**
     * Trim and preprocess the input text into segments that fit within the token budget.
     *
     * @param string $input The input text to process.
     *
     * @throws \InvalidArgumentException If maxTokens is invalid.
     *
     * @return list<string> The processed text segments.
     */
    public function trim(string $input): array;

    /**
     * Count the number of tokens in the given text.
     */
    public function countTokens(string $text): int;

    /**
     * Return a new instance with the given max token limit per segment.
     *
     * @throws \InvalidArgumentException If $maxTokens is less than 2.
     */
    public function withMaxTokens(int $maxTokens): self;

    /**
     * Return a new instance with duplicate line removal enabled or disabled.
     *
     * Only non-blank duplicate lines are removed; blank lines are preserved
     * as structural separators.
     */
    public function withRemoveDuplicateLines(bool $remove): self;

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
    public function withRemoveShortWords(bool $remove, int $minWordLength = 2): self;

    /**
     * Return a new instance with extraneous character removal enabled or disabled.
     *
     * WARNING: This removes brackets [], parentheses (), braces {}, angle brackets <>,
     * and asterisks *. This is aggressive and will destroy Markdown formatting, HTML tags,
     * and code syntax. Consider whether your use case can tolerate this data loss.
     */
    public function withRemoveExtraneous(bool $remove): self;

    /**
     * Return a new instance with whitespace compression enabled or disabled.
     *
     * When enabled (default), multiple whitespace characters are collapsed into
     * a single space. Disable this to preserve original whitespace (e.g. for code).
     */
    public function withCompressWhitespace(bool $compress): self;

    /**
     * Return a new instance with the given tokenizer callable.
     *
     * The default tokenizer splits on spaces, which is a rough heuristic. For
     * accurate token counting, provide a tokenizer that matches your LLM's
     * tokenization (e.g., tiktoken for OpenAI models).
     *
     * @param callable(string): list<string> $tokenizer
     */
    public function withTokenizer(callable $tokenizer): self;
}
