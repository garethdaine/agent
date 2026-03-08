<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Interrogation/AdversarialReviewerService.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Interrogation\AdversarialReviewerService
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-51a82537abbf4f0e073c9d61c69b148f30ca73dd9b88ccbb5b4ef93247fce2e0',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Interrogation/AdversarialReviewerService.php',
      ),
    ),
    'namespace' => 'App\\Support\\Interrogation',
    'name' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
    'shortName' => 'AdversarialReviewerService',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Orchestrates adversarial review of summary and plan artifacts.
 *
 * Invokes a Claude subprocess to validate generated artifacts against
 * discovery context, then validates and normalizes the reviewer\'s response.
 *
 * @see ReviewerPayloadGuard for validation rules
 * @see ReviewerPayloadNormalizer for normalization behavior
 * @see ReviewerContextBuilder for context assembly
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 22,
    'endLine' => 189,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'testMode' => 
      array (
        'declaringClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'implementingClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'name' => 'testMode',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 53,
            'startFilePos' => 685,
            'endTokenPos' => 53,
            'endFilePos' => 689,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'adapter' => 
      array (
        'declaringClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'implementingClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'name' => 'adapter',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Support\\Interrogation\\Adapters\\ClaudeAdapter',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 9,
        'endColumn' => 47,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'guard' => 
      array (
        'declaringClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'implementingClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'name' => 'guard',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Support\\Interrogation\\ReviewerPayloadGuard',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 9,
        'endColumn' => 52,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'normalizer' => 
      array (
        'declaringClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'implementingClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'name' => 'normalizer',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Support\\Interrogation\\ReviewerPayloadNormalizer',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 9,
        'endColumn' => 62,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'contextBuilder' => 
      array (
        'declaringClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'implementingClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'name' => 'contextBuilder',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Support\\Interrogation\\ReviewerContextBuilder',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 9,
        'endColumn' => 63,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'adapter' => 
          array (
            'name' => 'adapter',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Interrogation\\Adapters\\ClaudeAdapter',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 27,
            'endLine' => 27,
            'startColumn' => 9,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'guard' => 
          array (
            'name' => 'guard',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Interrogation\\ReviewerPayloadGuard',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 28,
            'endLine' => 28,
            'startColumn' => 9,
            'endColumn' => 52,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'normalizer' => 
          array (
            'name' => 'normalizer',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Interrogation\\ReviewerPayloadNormalizer',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 9,
            'endColumn' => 62,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'contextBuilder' => 
          array (
            'name' => 'contextBuilder',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Interrogation\\ReviewerContextBuilder',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 30,
            'endLine' => 30,
            'startColumn' => 9,
            'endColumn' => 63,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 26,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Interrogation',
        'declaringClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'implementingClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'currentClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'aliasName' => NULL,
      ),
      'setTestMode' => 
      array (
        'name' => 'setTestMode',
        'parameters' => 
        array (
          'enabled' => 
          array (
            'name' => 'enabled',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 40,
            'endLine' => 40,
            'startColumn' => 33,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Enable test mode to skip subprocess execution.
 *
 * When test mode is enabled, the service skips actual CLI subprocess
 * invocation and relies on the mocked adapter\'s parseReviewerResponse()
 * to return test data.
 */',
        'startLine' => 40,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Interrogation',
        'declaringClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'implementingClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'currentClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'aliasName' => NULL,
      ),
      'reviewSummary' => 
      array (
        'name' => 'reviewSummary',
        'parameters' => 
        array (
          'session' => 
          array (
            'name' => 'session',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\InterrogationSession',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 56,
            'endLine' => 56,
            'startColumn' => 9,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'summaryCandidate' => 
          array (
            'name' => 'summaryCandidate',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 9,
            'endColumn' => 31,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Review a summary candidate against discovery context.
 *
 * @param  InterrogationSession  $session  The session containing discovery context
 * @param  array<string, mixed>  $summaryCandidate  The summary artifact to review
 * @return array<string, mixed> Validated and normalized reviewer payload
 *
 * @throws \\InvalidArgumentException if the reviewer payload is invalid
 * @throws RuntimeException if subprocess execution fails
 */',
        'startLine' => 55,
        'endLine' => 68,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Interrogation',
        'declaringClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'implementingClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'currentClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'aliasName' => NULL,
      ),
      'reviewPlan' => 
      array (
        'name' => 'reviewPlan',
        'parameters' => 
        array (
          'session' => 
          array (
            'name' => 'session',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\InterrogationSession',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 82,
            'endLine' => 82,
            'startColumn' => 9,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'planCandidate' => 
          array (
            'name' => 'planCandidate',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 83,
            'endLine' => 83,
            'startColumn' => 9,
            'endColumn' => 28,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'lockedSummary' => 
          array (
            'name' => 'lockedSummary',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 84,
            'endLine' => 84,
            'startColumn' => 9,
            'endColumn' => 28,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Review a plan candidate against locked summary and discovery context.
 *
 * @param  InterrogationSession  $session  The session containing discovery context
 * @param  array<string, mixed>  $planCandidate  The plan artifact to review
 * @param  array<string, mixed>  $lockedSummary  The approved summary that plan must align with
 * @return array<string, mixed> Validated and normalized reviewer payload
 *
 * @throws \\InvalidArgumentException if the reviewer payload is invalid (including needs_clarification verdict)
 * @throws RuntimeException if subprocess execution fails
 */',
        'startLine' => 81,
        'endLine' => 96,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Interrogation',
        'declaringClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'implementingClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'currentClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'aliasName' => NULL,
      ),
      'executeReviewer' => 
      array (
        'name' => 'executeReviewer',
        'parameters' => 
        array (
          'session' => 
          array (
            'name' => 'session',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\InterrogationSession',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 103,
            'endLine' => 103,
            'startColumn' => 38,
            'endColumn' => 66,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'prompt' => 
          array (
            'name' => 'prompt',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 103,
            'endLine' => 103,
            'startColumn' => 69,
            'endColumn' => 82,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the reviewer subprocess or return mock output in test mode.
 *
 * @throws RuntimeException if subprocess fails
 */',
        'startLine' => 103,
        'endLine' => 134,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Interrogation',
        'declaringClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'implementingClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'currentClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'aliasName' => NULL,
      ),
      'buildSummaryReviewPrompt' => 
      array (
        'name' => 'buildSummaryReviewPrompt',
        'parameters' => 
        array (
          'context' => 
          array (
            'name' => 'context',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 141,
            'endLine' => 141,
            'startColumn' => 47,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Build the prompt for summary review.
 *
 * @param  array<string, mixed>  $context  Context package from ReviewerContextBuilder
 */',
        'startLine' => 141,
        'endLine' => 161,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Interrogation',
        'declaringClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'implementingClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'currentClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'aliasName' => NULL,
      ),
      'buildPlanReviewPrompt' => 
      array (
        'name' => 'buildPlanReviewPrompt',
        'parameters' => 
        array (
          'context' => 
          array (
            'name' => 'context',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 168,
            'endLine' => 168,
            'startColumn' => 44,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Build the prompt for plan review.
 *
 * @param  array<string, mixed>  $context  Context package from ReviewerContextBuilder
 */',
        'startLine' => 168,
        'endLine' => 188,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Interrogation',
        'declaringClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'implementingClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'currentClassName' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
      ),
    ),
  ),
));