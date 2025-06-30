<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\CodingStyle\Rector\Use_\SeparateMultiUseImportsRector;
use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertEmptyNullableObjectToAssertInstanceofRector;
use Rector\Privatization\Rector\ClassMethod\PrivatizeFinalClassMethodRector;
use Rector\Strict\Rector\BooleanNot\BooleanInBooleanNotRuleFixerRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddMethodCallBasedStrictParamTypeRector;
use Rector\TypeDeclaration\Rector\Closure\AddClosureNeverReturnTypeRector;
use Rector\TypeDeclaration\Rector\Closure\AddClosureVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\Closure\ClosureReturnTypeRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withPhpSets()
    ->withRules([
        AddOverrideAttributeToOverriddenMethodsRector::class,
        DeclareStrictTypesRector::class,
    ])

    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
        strictBooleans: true,
        carbon: true,
        phpunitCodeQuality: true,
    )
    ->withSkip([
        AddOverrideAttributeToOverriddenMethodsRector::class,
        AddArrowFunctionReturnTypeRector::class,
        AddClosureNeverReturnTypeRector::class,
        AddClosureVoidReturnTypeWhereNoReturnRector::class,
        ClosureReturnTypeRector::class,
        BooleanInBooleanNotRuleFixerRector::class,
        SeparateMultiUseImportsRector::class,
        EncapsedStringsToSprintfRector::class,
        AssertEmptyNullableObjectToAssertInstanceofRector::class,
        IssetOnPropertyObjectToPropertyExistsRector::class,
        AddMethodCallBasedStrictParamTypeRector::class => [__DIR__.'/tests'],
        PrivatizeFinalClassMethodRector::class => [__DIR__.'/src/SmsManager.php'],
    ]);
