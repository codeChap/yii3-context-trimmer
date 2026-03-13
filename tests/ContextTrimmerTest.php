<?php

declare(strict_types=1);

namespace Codechap\ContextTrimmer\Tests;

use Codechap\ContextTrimmer\ContextTrimmer;
use Codechap\ContextTrimmer\ContextTrimmerInterface;
use Codechap\ContextTrimmer\Exception\InvalidTokenLimitException;
use PHPUnit\Framework\TestCase;

use function count;
use function explode;
use function implode;
use function mb_str_split;
use function substr_count;

final class ContextTrimmerTest extends TestCase
{
    private ContextTrimmer $trimmer;

    protected function setUp(): void
    {
        $this->trimmer = new ContextTrimmer();
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(ContextTrimmerInterface::class, $this->trimmer);
    }

    public function testImmutability(): void
    {
        $original = new ContextTrimmer();
        $modified = $original->withMaxTokens(100);

        $this->assertNotSame($original, $modified);
    }

    public function testBasicTrimming(): void
    {
        $input = 'This is a long text that needs to be trimmed to fit within token limits.';

        $result = $this->trimmer
            ->withMaxTokens(5)
            ->trim($input);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $trimmer = $this->trimmer->withMaxTokens(5);
        foreach ($result as $segment) {
            $this->assertLessThanOrEqual(5, $trimmer->countTokens($segment));
        }
    }

    public function testEmptyInput(): void
    {
        $result = $this->trimmer->withMaxTokens(10)->trim('');
        $this->assertSame([], $result);
    }

    public function testWhitespaceOnlyInput(): void
    {
        $result = $this->trimmer->withMaxTokens(10)->trim('   ');
        $this->assertSame([], $result);
    }

    public function testNegativeTokenLimit(): void
    {
        $this->expectException(InvalidTokenLimitException::class);
        $this->trimmer->withMaxTokens(-1);
    }

    public function testZeroTokenLimit(): void
    {
        $this->expectException(InvalidTokenLimitException::class);
        $this->trimmer->withMaxTokens(0);
    }

    public function testOneTokenLimit(): void
    {
        $this->expectException(InvalidTokenLimitException::class);
        $this->trimmer->withMaxTokens(1);
    }

    public function testConstructorThrowsOnInvalidTokens(): void
    {
        $this->expectException(InvalidTokenLimitException::class);
        new ContextTrimmer(maxTokens: 0);
    }

    public function testConstructorThrowsOnOneToken(): void
    {
        $this->expectException(InvalidTokenLimitException::class);
        new ContextTrimmer(maxTokens: 1);
    }

    public function testUnderTokenLimitReturnsSingleSegment(): void
    {
        $input = 'Short text with few tokens';

        $result = $this->trimmer->withMaxTokens(50)->trim($input);

        $this->assertCount(1, $result);
    }

    public function testRemoveDuplicateLines(): void
    {
        $input = "Line one\nLine two\nLine one\nLine three\nLine two";

        $result = $this->trimmer
            ->withMaxTokens(100)
            ->withRemoveDuplicateLines(true)
            ->trim($input);

        $joined = implode(' ', $result);
        $this->assertEquals(1, substr_count($joined, 'Line one'));
        $this->assertEquals(1, substr_count($joined, 'Line two'));
        $this->assertEquals(1, substr_count($joined, 'Line three'));
    }

    public function testDeduplicateLinesPreservesBlankLines(): void
    {
        $input = "Paragraph one\n\nParagraph two\n\nParagraph one";

        $result = $this->trimmer
            ->withMaxTokens(200)
            ->withRemoveDuplicateLines(true)
            ->withCompressWhitespace(false)
            ->trim($input);

        $joined = implode('', $result);
        // The blank lines should be preserved as separators
        $this->assertStringContainsString("\n\n", $joined);
        // The duplicate "Paragraph one" should be removed
        $this->assertEquals(1, substr_count($joined, 'Paragraph one'));
    }

    public function testRemoveShortWords(): void
    {
        $input = 'I am a big fan of the great outdoors.';

        $result = $this->trimmer
            ->withMaxTokens(100)
            ->withRemoveShortWords(true, 3)
            ->trim($input);

        $joined = implode(' ', $result);
        $this->assertStringNotContainsString(' am ', $joined);
        $this->assertStringNotContainsString(' of ', $joined);
        $this->assertStringContainsString('big', $joined);
        $this->assertStringContainsString('fan', $joined);
    }

    public function testMinWordLengthKeepsWordsAtExactLength(): void
    {
        $input = 'I am a big fan';

        $result = $this->trimmer
            ->withMaxTokens(100)
            ->withRemoveShortWords(true, 2)
            ->trim($input);

        $joined = implode(' ', $result);
        // "am" has length 2 and minWordLength is 2, so it should be KEPT
        $this->assertStringContainsString('am', $joined);
        // "I" and "a" have length 1, shorter than 2, so removed
        $this->assertStringNotContainsString(' I ', $joined);
    }

    public function testRemoveExtraneous(): void
    {
        $input = 'Hello [world] (test) {data} <html> *bold*';

        $result = $this->trimmer
            ->withMaxTokens(100)
            ->withRemoveExtraneous(true)
            ->trim($input);

        $joined = implode(' ', $result);
        $this->assertStringNotContainsString('[', $joined);
        $this->assertStringNotContainsString(']', $joined);
        $this->assertStringNotContainsString('(', $joined);
        $this->assertStringNotContainsString('*', $joined);
        $this->assertStringContainsString('Hello', $joined);
    }

    public function testCustomTokenizer(): void
    {
        $tokenizer = static fn(string $text): array => explode(' ', $text);
        $trimmer = new ContextTrimmer(tokenizer: $tokenizer);

        $input = 'One two three four five six seven';

        $result = $trimmer->withMaxTokens(3)->trim($input);

        foreach ($result as $segment) {
            $tokens = array_filter(explode(' ', $segment), static fn(string $t): bool => $t !== '');
            $this->assertLessThanOrEqual(3, count($tokens));
        }
    }

    public function testWithTokenizerImmutability(): void
    {
        $original = new ContextTrimmer();
        $custom = $original->withTokenizer(static fn(string $text): array => explode(' ', $text));

        $this->assertNotSame($original, $custom);
    }

    public function testConstructorDefaults(): void
    {
        $trimmer = new ContextTrimmer();

        $result = $trimmer->trim('Short text.');

        $this->assertCount(1, $result);
        $this->assertSame('Short text.', $result[0]);
    }

    public function testAllOptionsChained(): void
    {
        $input = "Welcome to our lodge.\nBook your stay today.\nWelcome to our lodge.\nEnjoy the views.";

        $result = $this->trimmer
            ->withMaxTokens(8192)
            ->withRemoveDuplicateLines(true)
            ->withRemoveShortWords(true, 3)
            ->withRemoveExtraneous(true)
            ->trim($input);

        $joined = implode(' ', $result);
        $this->assertEquals(1, substr_count($joined, 'Welcome'));
        $this->assertStringContainsString('lodge', $joined);
        $this->assertStringContainsString('Enjoy', $joined);
    }

    public function testCompressWhitespaceConfigurable(): void
    {
        $input = "Hello    world";

        $compressed = $this->trimmer
            ->withMaxTokens(100)
            ->withCompressWhitespace(true)
            ->trim($input);

        $uncompressed = $this->trimmer
            ->withMaxTokens(100)
            ->withCompressWhitespace(false)
            ->trim($input);

        $this->assertSame('Hello world', $compressed[0]);
        $this->assertSame('Hello    world', $uncompressed[0]);
    }

    public function testSentenceSplitHandlesAbbreviations(): void
    {
        $input = 'Dr. Smith went home. He rested.';

        $result = $this->trimmer
            ->withMaxTokens(100)
            ->trim($input);

        // Should fit in one segment, not split at "Dr."
        $this->assertCount(1, $result);
        $this->assertSame('Dr. Smith went home. He rested.', $result[0]);
    }

    public function testSentenceSplitHandlesDecimals(): void
    {
        $input = 'The value is 3.14 approximately. That is pi.';

        $result = $this->trimmer
            ->withMaxTokens(100)
            ->trim($input);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('3.14', $result[0]);
    }

    public function testSegmentTokenCountAccuracy(): void
    {
        // This test verifies that actual concatenated segments respect the budget,
        // not just the additive sum of individual sentence token counts.
        $input = 'First sentence here. Second sentence here. Third sentence here. Fourth sentence here.';

        $trimmer = $this->trimmer->withMaxTokens(7);
        $result = $trimmer->trim($input);

        foreach ($result as $segment) {
            $this->assertLessThanOrEqual(7, $trimmer->countTokens($segment));
        }
    }

    public function testWithCompressWhitespaceImmutability(): void
    {
        $original = new ContextTrimmer();
        $modified = $original->withCompressWhitespace(false);

        $this->assertNotSame($original, $modified);
    }

    public function testSentenceSplitAbbreviationsDoNotPreventRealSplits(): void
    {
        // Abbreviations should not prevent splitting at real sentence boundaries
        $input = 'Dr. Smith went home. He rested.';

        $trimmer = $this->trimmer->withMaxTokens(5);
        $result = $trimmer->trim($input);

        // Should be split into multiple segments since budget is small
        $this->assertGreaterThan(1, count($result));
    }

    public function testFilterShortWordsPreservesWhitespaceWhenCompressDisabled(): void
    {
        $input = "Hello  I  am  here";

        $result = $this->trimmer
            ->withMaxTokens(100)
            ->withRemoveShortWords(true, 2)
            ->withCompressWhitespace(false)
            ->trim($input);

        $joined = implode('', $result);
        // "I" is removed but the double spaces between remaining words should be preserved
        $this->assertStringContainsString('  ', $joined);
    }

    public function testFilterShortWordsDoesNotCollapseNewlines(): void
    {
        $input = "Hello\n\nworld";

        $result = $this->trimmer
            ->withMaxTokens(100)
            ->withRemoveShortWords(true, 2)
            ->withCompressWhitespace(false)
            ->trim($input);

        $joined = implode('', $result);
        $this->assertStringContainsString("\n\n", $joined);
    }

    public function testSentenceSplitHandlesLatinAbbreviations(): void
    {
        $input = 'Use e.g. apples or oranges. They are healthy.';

        $result = $this->trimmer
            ->withMaxTokens(100)
            ->trim($input);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('e.g.', $result[0]);
    }

    public function testSentenceSplitHandlesNumberedItems(): void
    {
        $input = 'Item No. 5 is the best. We should buy it.';

        $result = $this->trimmer
            ->withMaxTokens(100)
            ->trim($input);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('No.', $result[0]);
    }

    public function testSentenceSplitHandlesUppercaseLetterAbbreviations(): void
    {
        $input = 'The U.S. is large. It has many states.';

        $result = $this->trimmer
            ->withMaxTokens(100)
            ->trim($input);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('U.S.', $result[0]);
    }

    public function testConstructorThrowsOnNegativeMinWordLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum word length must be at least 1');
        new ContextTrimmer(minWordLength: -1);
    }

    public function testConstructorThrowsOnZeroMinWordLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ContextTrimmer(minWordLength: 0);
    }

    public function testWithRemoveShortWordsThrowsOnNegativeMinWordLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum word length must be at least 1');
        $this->trimmer->withRemoveShortWords(true, -1);
    }

    public function testMultibyteUnicodeTextWithDefaultTokenizer(): void
    {
        // Default tokenizer splits on spaces — CJK text without spaces becomes one token
        $input = '这是一段中文文本';

        $result = $this->trimmer
            ->withMaxTokens(100)
            ->trim($input);

        $this->assertCount(1, $result);
        $this->assertSame('这是一段中文文本', $result[0]);
        // Default tokenizer treats the whole string as 1 token (no spaces)
        $this->assertSame(1, $this->trimmer->countTokens($input));
    }

    public function testMultibyteUnicodeTextWithCustomTokenizer(): void
    {
        // A character-level tokenizer handles CJK properly
        $trimmer = new ContextTrimmer(
            tokenizer: static fn(string $text): array => mb_str_split($text),
        );

        $input = '这是一段中文文本';

        $this->assertSame(8, $trimmer->countTokens($input));

        $result = $trimmer->withMaxTokens(4)->trim($input);

        $this->assertGreaterThan(1, count($result));
    }
}
