<?php

declare(strict_types=1);

use Codechap\ContextTrimmer\ContextTrimmer;
use Codechap\ContextTrimmer\ContextTrimmerInterface;
use Yiisoft\Definitions\Reference;

/** @var array $params */

return [
    ContextTrimmerInterface::class => [
        'class' => ContextTrimmer::class,
        '__construct()' => [
            'maxTokens' => $params['codechap/yii3-context-trimmer']['maxTokens'],
            'removeDuplicateLines' => $params['codechap/yii3-context-trimmer']['removeDuplicateLines'],
            'removeShortWords' => $params['codechap/yii3-context-trimmer']['removeShortWords'],
            'minWordLength' => $params['codechap/yii3-context-trimmer']['minWordLength'],
            'removeExtraneous' => $params['codechap/yii3-context-trimmer']['removeExtraneous'],
            'compressWhitespace' => $params['codechap/yii3-context-trimmer']['compressWhitespace'],
        ],
    ],
    ContextTrimmer::class => Reference::to(ContextTrimmerInterface::class),
];
