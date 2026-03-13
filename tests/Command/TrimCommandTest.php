<?php

declare(strict_types=1);

namespace Codechap\Yii3ContextTrimmer\Tests\Command;

use Codechap\Yii3ContextTrimmer\Command\TrimCommand;
use Codechap\Yii3ContextTrimmer\ContextTrimmer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class TrimCommandTest extends TestCase
{
    private TrimCommand $command;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->command = new TrimCommand(new ContextTrimmer());
        $this->tester = new CommandTester($this->command);
    }

    public function testTrimFromFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ctx_');
        file_put_contents($file, 'Hello world from a file.');

        try {
            $this->tester->execute(['file' => $file]);

            $this->assertSame(0, $this->tester->getStatusCode());
            $this->assertStringContainsString('Hello world from a file.', $this->tester->getDisplay());
        } finally {
            unlink($file);
        }
    }

    public function testTrimWithMaxTokens(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ctx_');
        file_put_contents($file, 'First sentence here. Second sentence here. Third sentence here. Fourth sentence here.');

        try {
            $this->tester->execute([
                'file' => $file,
                '--max-tokens' => '5',
            ]);

            $this->assertSame(0, $this->tester->getStatusCode());
            // Should produce multiple segments separated by ---
            $this->assertStringContainsString('---', $this->tester->getDisplay());
        } finally {
            unlink($file);
        }
    }

    public function testJsonOutput(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ctx_');
        file_put_contents($file, 'Hello world.');

        try {
            $this->tester->execute([
                'file' => $file,
                '--json' => true,
            ]);

            $this->assertSame(0, $this->tester->getStatusCode());

            $decoded = json_decode(trim($this->tester->getDisplay()), true);
            $this->assertIsArray($decoded);
            $this->assertSame('Hello world.', $decoded[0]);
        } finally {
            unlink($file);
        }
    }

    public function testRemoveDuplicatesOption(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ctx_');
        file_put_contents($file, "Line one\nLine two\nLine one");

        try {
            $this->tester->execute([
                'file' => $file,
                '--remove-duplicates' => true,
                '--json' => true,
            ]);

            $this->assertSame(0, $this->tester->getStatusCode());
            $output = trim($this->tester->getDisplay());
            $this->assertEquals(1, substr_count($output, 'Line one'));
        } finally {
            unlink($file);
        }
    }

    public function testRemoveExtraneousOption(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ctx_');
        file_put_contents($file, 'Hello [world] (test)');

        try {
            $this->tester->execute([
                'file' => $file,
                '--remove-extraneous' => true,
            ]);

            $this->assertSame(0, $this->tester->getStatusCode());
            $output = $this->tester->getDisplay();
            $this->assertStringNotContainsString('[', $output);
            $this->assertStringNotContainsString(']', $output);
        } finally {
            unlink($file);
        }
    }

    public function testNonexistentFileReturnsFailure(): void
    {
        $this->tester->execute(['file' => '/nonexistent/path/file.txt']);

        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testInvalidMaxTokensReturnsFailure(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ctx_');
        file_put_contents($file, 'Some text.');

        try {
            $this->tester->execute([
                'file' => $file,
                '--max-tokens' => '0',
            ]);

            $this->assertSame(1, $this->tester->getStatusCode());
        } finally {
            unlink($file);
        }
    }

    public function testNonNumericMaxTokensReturnsFailure(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ctx_');
        file_put_contents($file, 'Some text.');

        try {
            $this->tester->execute([
                'file' => $file,
                '--max-tokens' => 'abc',
            ]);

            $this->assertSame(1, $this->tester->getStatusCode());
            $this->assertStringContainsString('positive integer', $this->tester->getDisplay());
        } finally {
            unlink($file);
        }
    }

    public function testNonNumericMinWordLengthReturnsFailure(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ctx_');
        file_put_contents($file, 'Some text here.');

        try {
            $this->tester->execute([
                'file' => $file,
                '--remove-short-words' => true,
                '--min-word-length' => 'xyz',
            ]);

            $this->assertSame(1, $this->tester->getStatusCode());
            $this->assertStringContainsString('positive integer', $this->tester->getDisplay());
        } finally {
            unlink($file);
        }
    }
}
