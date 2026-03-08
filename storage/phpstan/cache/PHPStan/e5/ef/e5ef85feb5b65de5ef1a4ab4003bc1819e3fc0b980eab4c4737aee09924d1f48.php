<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Delegation/ContractValidator.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Delegation\ContractValidator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-7998ff8295266786696ca900e24baadd28d1fb3ff1bdc42e51bcab2ffbbafa5d',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Delegation\\ContractValidator',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Delegation/ContractValidator.php',
      ),
    ),
    'namespace' => 'App\\Support\\Delegation',
    'name' => 'App\\Support\\Delegation\\ContractValidator',
    'shortName' => 'ContractValidator',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Validates delegation contract configurations against business rules.
 *
 * Validation Rules:
 * - required_capability must reference an active DelegationCapability (by slug)
 * - authority_scope.max_runtime_seconds <= 86400 (24 hours)
 * - criticality must be valid enum: low, medium, high, critical
 * - Either prompt or task_markdown_path must be present (not both)
 * - verification_strategy.automated_check.check_profile must exist in config
 * - verification_strategy.human_approval.timeout_hours <= 4
 *
 * Conditions for Correctness:
 * - DelegationCapability model must have \'slug\' column and \'active\' scope
 * - config(\'delegation.check_profiles\') must be properly defined
 *
 * Not Handled:
 * - Path validation for task_markdown_path (done separately by PathPolicy)
 * - Time constraint deadline validation (future timestamp check)
 * - Deep validation of verification_strategy.ai_critic config
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 27,
    'endLine' => 180,
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
      'MAX_RUNTIME_SECONDS' => 
      array (
        'declaringClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'implementingClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'name' => 'MAX_RUNTIME_SECONDS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '86400',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 28,
            'startFilePos' => 1063,
            'endTokenPos' => 28,
            'endFilePos' => 1067,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 46,
      ),
      'MAX_HUMAN_APPROVAL_TIMEOUT_HOURS' => 
      array (
        'declaringClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'implementingClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'name' => 'MAX_HUMAN_APPROVAL_TIMEOUT_HOURS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 41,
            'startFilePos' => 1136,
            'endTokenPos' => 41,
            'endFilePos' => 1136,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 55,
      ),
      'VALID_CRITICALITY_VALUES' => 
      array (
        'declaringClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'implementingClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'name' => 'VALID_CRITICALITY_VALUES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'low\', \'medium\', \'high\', \'critical\']',
          'attributes' => 
          array (
            'startLine' => 33,
            'endLine' => 33,
            'startTokenPos' => 52,
            'startFilePos' => 1185,
            'endTokenPos' => 63,
            'endFilePos' => 1221,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 83,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'validate' => 
      array (
        'name' => 'validate',
        'parameters' => 
        array (
          'contractJson' => 
          array (
            'name' => 'contractJson',
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
            'startLine' => 40,
            'endLine' => 40,
            'startColumn' => 30,
            'endColumn' => 48,
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
            'name' => 'App\\Support\\Delegation\\ValidationResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Validate a contract configuration.
 *
 * @param  array<string, mixed>  $contractJson
 */',
        'startLine' => 40,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'implementingClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'currentClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'aliasName' => NULL,
      ),
      'validateCapability' => 
      array (
        'name' => 'validateCapability',
        'parameters' => 
        array (
          'contractJson' => 
          array (
            'name' => 'contractJson',
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
            'startLine' => 64,
            'endLine' => 64,
            'startColumn' => 41,
            'endColumn' => 59,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Validate required_capability references an active capability.
 *
 * @param  array<string, mixed>  $contractJson
 * @return array<int, string>
 */',
        'startLine' => 64,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'implementingClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'currentClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'aliasName' => NULL,
      ),
      'validateMaxRuntime' => 
      array (
        'name' => 'validateMaxRuntime',
        'parameters' => 
        array (
          'contractJson' => 
          array (
            'name' => 'contractJson',
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
            'startLine' => 90,
            'endLine' => 90,
            'startColumn' => 41,
            'endColumn' => 59,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Validate max_runtime_seconds does not exceed 24 hours.
 *
 * @param  array<string, mixed>  $contractJson
 * @return array<int, string>
 */',
        'startLine' => 90,
        'endLine' => 99,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'implementingClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'currentClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'aliasName' => NULL,
      ),
      'validateCriticality' => 
      array (
        'name' => 'validateCriticality',
        'parameters' => 
        array (
          'contractJson' => 
          array (
            'name' => 'contractJson',
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
            'startLine' => 107,
            'endLine' => 107,
            'startColumn' => 42,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Validate criticality value is in allowed enum.
 *
 * @param  array<string, mixed>  $contractJson
 * @return array<int, string>
 */',
        'startLine' => 107,
        'endLine' => 118,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'implementingClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'currentClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'aliasName' => NULL,
      ),
      'validatePromptOrPath' => 
      array (
        'name' => 'validatePromptOrPath',
        'parameters' => 
        array (
          'contractJson' => 
          array (
            'name' => 'contractJson',
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
            'startLine' => 126,
            'endLine' => 126,
            'startColumn' => 43,
            'endColumn' => 61,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Validate either prompt or task_markdown_path is present, but not both.
 *
 * @param  array<string, mixed>  $contractJson
 * @return array<int, string>
 */',
        'startLine' => 126,
        'endLine' => 142,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'implementingClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'currentClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'aliasName' => NULL,
      ),
      'validateVerificationStrategy' => 
      array (
        'name' => 'validateVerificationStrategy',
        'parameters' => 
        array (
          'contractJson' => 
          array (
            'name' => 'contractJson',
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
            'startLine' => 150,
            'endLine' => 150,
            'startColumn' => 51,
            'endColumn' => 69,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Validate verification_strategy configuration.
 *
 * @param  array<string, mixed>  $contractJson
 * @return array<int, string>
 */',
        'startLine' => 150,
        'endLine' => 179,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'implementingClassName' => 'App\\Support\\Delegation\\ContractValidator',
        'currentClassName' => 'App\\Support\\Delegation\\ContractValidator',
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