<?php

use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withCache(cacheDirectory: __DIR__ . '/.cache/rector', containerCacheDirectory: __DIR__ . '/vendor')
    ->withSkipPath(__DIR__ . '/tests/Fixtures')
    // a fork of symfony/type-info's, kept close to upstream so changes there can be ported
    ->withSkipPath(__DIR__ . '/src/TypeResolver/StringTypeResolver.php')
    ->withSkip([
        NewlineBeforeNewAssignSetRector::class,
        NewlineAfterStatementRector::class,
        ReadOnlyPropertyRector::class,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        phpunitCodeQuality: true,
    )
    ->withAttributesSets(phpunit: true)
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withPhpSets();
