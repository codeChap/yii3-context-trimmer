<?php

declare(strict_types=1);

namespace Codechap\ContextTrimmer\Exception;

use InvalidArgumentException;

use function sprintf;

final class InvalidTokenLimitException extends InvalidArgumentException
{
    public function __construct(int $limit)
    {
        parent::__construct(
            sprintf('Token limit must be at least 2, %d given.', $limit),
        );
    }
}
