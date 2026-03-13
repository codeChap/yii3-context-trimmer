<?php

declare(strict_types=1);

use Codechap\ContextTrimmer\Command\TrimCommand;

return [
    'yiisoft/yii-console' => [
        'commands' => [
            'context:trim' => TrimCommand::class,
        ],
    ],
];
