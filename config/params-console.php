<?php

declare(strict_types=1);

use Codechap\Yii3ContextTrimmer\Command\TrimCommand;

return [
    'yiisoft/yii-console' => [
        'commands' => [
            'context:trim' => TrimCommand::class,
        ],
    ],
];
