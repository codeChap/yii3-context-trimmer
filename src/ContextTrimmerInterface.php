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
     * @return list<string> The processed text segments.
     */
    public function trim(string $input): array;

    /**
     * Count the number of tokens in the given text.
     */
    public function countTokens(string $text): int;

    /**
     * Return a new instance with the given max token limit per segment.
     */
    public function withMaxTokens(int $maxTokens): self;

    /**
     * Return a new instance with duplicate line removal enabled or disabled.
     */
    public function withRemoveDuplicateLines(bool $remove): self;

    /**
     * Return a new instance with short word removal enabled or disabled.
     */
    public function withRemoveShortWords(bool $remove, int $minWordLength = 2): self;

    /**
     * Return a new instance with extraneous character removal enabled or disabled.
     */
    public function withRemoveExtraneous(bool $remove): self;

    /**
     * Return a new instance with the given tokenizer callable.
     *
     * @param callable(string): list<string> $tokenizer
     */
    public function withTokenizer(callable $tokenizer): self;
}
