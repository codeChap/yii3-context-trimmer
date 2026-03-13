<?php

declare(strict_types=1);

namespace Codechap\Yii3ContextTrimmer\Command;

use Codechap\Yii3ContextTrimmer\ContextTrimmerInterface;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'context:trim',
    description: 'Trim text to fit within a token budget for LLM context windows',
)]
final class TrimCommand extends Command
{
    public function __construct(
        private readonly ContextTrimmerInterface $trimmer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::OPTIONAL, 'Path to input file (reads from stdin if omitted)')
            ->addOption('max-tokens', 't', InputOption::VALUE_REQUIRED, 'Maximum tokens per segment')
            ->addOption('remove-duplicates', 'd', InputOption::VALUE_NONE, 'Remove duplicate lines')
            ->addOption('remove-short-words', 's', InputOption::VALUE_NONE, 'Remove short words')
            ->addOption('min-word-length', 'l', InputOption::VALUE_REQUIRED, 'Minimum word length to keep (used with --remove-short-words)', '2')
            ->addOption('remove-extraneous', 'x', InputOption::VALUE_NONE, 'Remove extraneous punctuation (brackets, parens, braces, angle brackets, asterisks)')
            ->addOption('no-compress', null, InputOption::VALUE_NONE, 'Disable whitespace compression')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output segments as a JSON array');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $text = $this->readInput($input);

        if ($text === null) {
            $output->writeln('<error>No input text provided. Pass a file path or pipe text via stdin.</error>');

            return Command::FAILURE;
        }

        try {
            $trimmer = $this->buildTrimmer($input);
            $segments = $trimmer->trim($text);
        } catch (InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $this->writeOutput($output, $segments, (bool) $input->getOption('json'));

        return Command::SUCCESS;
    }

    private function readInput(InputInterface $input): ?string
    {
        /** @var string|null $file */
        $file = $input->getArgument('file');

        if ($file !== null) {
            if (!file_exists($file) || !is_readable($file)) {
                return null;
            }

            $content = file_get_contents($file);

            return $content !== false ? $content : null;
        }

        $content = stream_get_contents(STDIN);

        return ($content !== false && $content !== '') ? $content : null;
    }

    private function buildTrimmer(InputInterface $input): ContextTrimmerInterface
    {
        $trimmer = $this->trimmer;

        /** @var string|null $maxTokens */
        $maxTokens = $input->getOption('max-tokens');

        if ($maxTokens !== null) {
            $trimmer = $trimmer->withMaxTokens((int) $maxTokens);
        }

        if ($input->getOption('remove-duplicates')) {
            $trimmer = $trimmer->withRemoveDuplicateLines(true);
        }

        if ($input->getOption('remove-short-words')) {
            /** @var string $minWordLength */
            $minWordLength = $input->getOption('min-word-length');
            $trimmer = $trimmer->withRemoveShortWords(true, (int) $minWordLength);
        }

        if ($input->getOption('remove-extraneous')) {
            $trimmer = $trimmer->withRemoveExtraneous(true);
        }

        if ($input->getOption('no-compress')) {
            $trimmer = $trimmer->withCompressWhitespace(false);
        }

        return $trimmer;
    }

    /**
     * @param list<string> $segments
     */
    private function writeOutput(OutputInterface $output, array $segments, bool $json): void
    {
        if ($json) {
            $output->writeln((string) json_encode($segments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return;
        }

        foreach ($segments as $i => $segment) {
            if ($i > 0) {
                $output->writeln('---');
            }
            $output->writeln($segment);
        }
    }
}
