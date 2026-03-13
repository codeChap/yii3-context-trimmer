# Yii3 Context Trimmer

Tokenizer-agnostic text preprocessor for trimming and optimising content to fit within LLM context windows. Built for Yii3 with full DI container integration, configurable params, and a console command.

## Requirements

- PHP 8.2 - 8.5

## Installation

```bash
composer require codechap/yii3-context-trimmer
```

For Yii3 applications using the config plugin, the DI bindings and params are registered automatically.

## Usage

### Via Dependency Injection (Yii3)

Inject the interface to get a pre-configured trimmer from the DI container:

```php
use Codechap\ContextTrimmer\ContextTrimmerInterface;

final class MyService
{
    public function __construct(
        private readonly ContextTrimmerInterface $trimmer,
    ) {}

    public function process(string $text): array
    {
        return $this->trimmer
            ->withMaxTokens(4096)
            ->withRemoveDuplicateLines(true)
            ->trim($text);
    }
}
```

Default configuration is handled via `params.php` — see [Configuration](#yii3-params) below.

### Standalone

```php
use Codechap\ContextTrimmer\ContextTrimmer;

$trimmer = new ContextTrimmer();

$segments = $trimmer
    ->withMaxTokens(4096)
    ->withRemoveDuplicateLines(true)
    ->trim($longText);
```

### Custom Tokenizer

The default tokenizer splits on spaces, which is a rough heuristic. For accurate token counting, provide a tokenizer matching your LLM's tokenization:

```php
// Example: tiktoken-based tokenizer for OpenAI models
$trimmer = new ContextTrimmer(
    tokenizer: function (string $text): array {
        return your_tiktoken_encode($text);
    },
);
```

## Configuration

### Yii3 Params

Override defaults in your application's `params.php`:

```php
return [
    'codechap/yii3-context-trimmer' => [
        'maxTokens' => 4096,           // Max tokens per segment (default: 8192)
        'removeDuplicateLines' => true, // Remove duplicate lines (default: false)
        'removeShortWords' => false,    // Remove short words (default: false)
        'minWordLength' => 2,           // Min word length to keep (default: 2)
        'removeExtraneous' => false,    // Remove brackets/parens/etc (default: false)
        'compressWhitespace' => true,   // Compress whitespace (default: true)
    ],
];
```

### Options Reference

| Option | Default | Description |
|--------|---------|-------------|
| `maxTokens` | `8192` | Maximum tokens per output segment. Must be >= 2. |
| `removeDuplicateLines` | `false` | Remove duplicate non-blank lines. Blank lines are preserved as structural separators. |
| `removeShortWords` | `false` | Remove purely-alphabetical words shorter than `minWordLength`. **Warning:** This is aggressive and removes articles, prepositions, and pronouns. |
| `minWordLength` | `2` | Minimum word length to keep. Words shorter than this are removed. |
| `removeExtraneous` | `false` | Remove `[](){}<>*` characters. **Warning:** Destroys Markdown, HTML, and code syntax. |
| `compressWhitespace` | `true` | Collapse multiple whitespace characters into single spaces. Disable for code/formatted text. |

## Console Command

Requires `yiisoft/yii-console` for the Yii3 console runner.

```bash
# Trim a file
./yii context:trim path/to/file.txt

# Pipe from stdin
cat document.txt | ./yii context:trim

# With options
./yii context:trim file.txt --max-tokens 4096 --remove-duplicates --json

# All options
./yii context:trim file.txt \
    -t 4096 \
    -d \                     # --remove-duplicates
    -s \                     # --remove-short-words
    -l 3 \                   # --min-word-length
    -x \                     # --remove-extraneous
    --no-compress \
    -j                       # --json output
```

## Testing

```bash
composer test
```

## Static Analysis

```bash
composer analyse
```

## License

MIT
