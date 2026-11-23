<?php

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\Excluder\ExcludedLineRange;
use ShipMonk\CoverageGuard\Excluder\ExclusionContext;
use ShipMonk\CoverageGuard\Excluder\ExecutableLineExcluder;
use ShipMonk\CoverageGuard\Excluder\IgnoreThrowNewExceptionLineExcluder;
use ShipMonk\CoverageGuard\Rule\EnforceCoverageForMethodsRule;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\GitFetchError;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\HttpError;
use WyriHaximus\GitHubAction\ComposerPhpVersionsInRange\TransientHttpError;

return (static function (): Config {
    $config = new Config();

    $config->addRule(new EnforceCoverageForMethodsRule(
        requiredCoveragePercentage: 80,
        minExecutableLines: 1,
    ));

    $config->addExecutableLineExcluder(new IgnoreThrowNewExceptionLineExcluder([
        RuntimeException::class,
        GitFetchError::class,
        HttpError::class,
        TransientHttpError::class,
    ]));

    // Exclude async network-dependent code from coverage (can't test without network)
    $config->addExecutableLineExcluder(new class implements ExecutableLineExcluder {
        public function getExcludedLineRange(Node $node, ExclusionContext $context): ExcludedLineRange|null
        {
            if (! $node instanceof ClassMethod) {
                return null;
            }

            $path = $context->getFilePath();
            $name = $node->name->toString();

            // Exclude doGet and retry in HttpClient (network-dependent)
            if (str_contains($path, 'HttpClient') && in_array($name, ['doGet', 'retry', 'get', 'getRequired'], true)) {
                return new ExcludedLineRange($node->getStartLine(), $node->getEndLine());
            }

            return null;
        }
    });

    return $config;
})();
