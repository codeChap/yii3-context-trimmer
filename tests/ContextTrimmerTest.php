<?php

declare(strict_types=1);

namespace Codechap\Yii3ContextTrimmer\Tests;

use Codechap\Yii3ContextTrimmer\ContextTrimmer;
use Codechap\Yii3ContextTrimmer\ContextTrimmerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

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
        $this->expectException(InvalidArgumentException::class);
        $this->trimmer->withMaxTokens(-1)->trim('Some text');
    }

    public function testZeroTokenLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->trimmer->withMaxTokens(0)->trim('Some text');
    }

    public function testUnderTokenLimitReturnsSingleSegment(): void
    {
        $input = 'Short text with few tokens';

        $result = $this->trimmer->withMaxTokens(50)->trim($input);

        $this->assertCount(1, $result);
    }

    public function testMaxTokensOne(): void
    {
        $input = 'This is a test';

        $result = $this->trimmer->withMaxTokens(1)->trim($input);

        $this->assertIsArray($result);
        foreach ($result as $segment) {
            $this->assertNotEmpty($segment);
        }
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

    public function testRemoveShortWords(): void
    {
        $input = 'I am a big fan of the great outdoors.';

        $result = $this->trimmer
            ->withMaxTokens(100)
            ->withRemoveShortWords(true, 2)
            ->trim($input);

        $joined = implode(' ', $result);
        $this->assertStringNotContainsString(' am ', $joined);
        $this->assertStringNotContainsString(' of ', $joined);
        $this->assertStringContainsString('big', $joined);
        $this->assertStringContainsString('fan', $joined);
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
            ->withRemoveShortWords(true, 2)
            ->withRemoveExtraneous(true)
            ->trim($input);

        $joined = implode(' ', $result);
        $this->assertEquals(1, substr_count($joined, 'Welcome'));
        $this->assertStringContainsString('lodge', $joined);
        $this->assertStringContainsString('Enjoy', $joined);
    }
}
