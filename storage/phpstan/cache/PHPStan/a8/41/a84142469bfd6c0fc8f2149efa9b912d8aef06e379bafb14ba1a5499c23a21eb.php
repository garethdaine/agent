<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Delegation/Verification/HumanApprovalStep.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Delegation\Verification\HumanApprovalStep
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-85c154fee341ab6caa7ea4cb246fa9595d06d6d545f37d21b5ef028d686b56cc',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Delegation\\Verification\\HumanApprovalStep',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Delegation/Verification/HumanApprovalStep.php',
      ),
    ),
    'namespace' => 'App\\Support\\Delegation\\Verification',
    'name' => 'App\\Support\\Delegation\\Verification\\HumanApprovalStep',
    'shortName' => 'HumanApprovalStep',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Creates a pending human approval request for task verification.
 *
 * Human approval is asynchronous - it creates a pending verification result
 * with an expiration time and returns immediately. The approval is resolved
 * via API endpoint, which fires DelegationTaskVerified.
 *
 * Expiration:
 * - Default: config(\'delegation.human_approval_timeout_hours\') = 4 hours
 * - Override: stepConfig[\'timeout_hours\'] if provided
 *
 * The reconciler will mark expired approvals as failed.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 22,
    'endLine' => 74,
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
    ),
    'immediateMethods' => 
    array (
      'execute' => 
      array (
        'name' => 'execute',
        'parameters' => 
        array (
          'task' => 
          array (
            'name' => 'task',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\DelegationTask',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 36,
            'endLine' => 36,
            'startColumn' => 9,
            'endColumn' => 28,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'attempt' => 
          array (
            'name' => 'attempt',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\DelegationAttempt',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 9,
            'endColumn' => 34,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'stepConfig' => 
          array (
            'name' => 'stepConfig',
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
            'startLine' => 38,
            'endLine' => 38,
            'startColumn' => 9,
            'endColumn' => 25,
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
            'name' => 'App\\Support\\Delegation\\Verification\\VerificationStepResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the human approval step.
 *
 * Creates a pending verification result with expiration time.
 * Returns pending immediately since approval is manual.
 *
 * @param  DelegationTask  $task  The task being verified
 * @param  DelegationAttempt  $attempt  The attempt being verified
 * @param  array  $stepConfig  Step configuration including optional \'timeout_hours\' and \'instructions\'
 * @return VerificationStepResult Always returns pending
 */',
        'startLine' => 35,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Delegation\\Verification',
        'declaringClassName' => 'App\\Support\\Delegation\\Verification\\HumanApprovalStep',
        'implementingClassName' => 'App\\Support\\Delegation\\Verification\\HumanApprovalStep',
        'currentClassName' => 'App\\Support\\Delegation\\Verification\\HumanApprovalStep',
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