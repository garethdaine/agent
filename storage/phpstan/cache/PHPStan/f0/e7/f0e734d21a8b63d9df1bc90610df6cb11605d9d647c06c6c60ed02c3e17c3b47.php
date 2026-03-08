<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Compliance/VerificationEvidenceEvaluator.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Compliance\VerificationEvidenceEvaluator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-fdea814a77e9d57402fa08c6c5288fccdde1a5fd7784564fc353ce644f037764',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Compliance\\VerificationEvidenceEvaluator',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Compliance/VerificationEvidenceEvaluator.php',
      ),
    ),
    'namespace' => 'App\\Support\\Compliance',
    'name' => 'App\\Support\\Compliance\\VerificationEvidenceEvaluator',
    'shortName' => 'VerificationEvidenceEvaluator',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 8,
    'endLine' => 89,
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
      'REQUIREMENTS' => 
      array (
        'declaringClassName' => 'App\\Support\\Compliance\\VerificationEvidenceEvaluator',
        'implementingClassName' => 'App\\Support\\Compliance\\VerificationEvidenceEvaluator',
        'name' => 'REQUIREMENTS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'feature\' => [\'automated_check\', \'ai_critic\'], \'bugfix\' => [\'automated_check\', \'ai_critic\', \'human_approval\'], \'refactor\' => [\'automated_check\'], \'docs\' => [], \'test\' => [], \'custom\' => [\'automated_check\', \'ai_critic\']]',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 29,
            'startTokenPos' => 33,
            'startFilePos' => 592,
            'endTokenPos' => 99,
            'endFilePos' => 866,
          ),
        ),
        'docComment' => '/**
 * Evidence requirements by task category.
 *
 * - feature: automated_check + ai_critic
 * - bugfix: automated_check + ai_critic + human_approval
 * - refactor: automated_check only
 * - docs: no verification required
 * - test: no verification required
 * - custom: inherits feature requirements
 *
 * @var array<string, array<int, string>>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'evaluate' => 
      array (
        'name' => 'evaluate',
        'parameters' => 
        array (
          'category' => 
          array (
            'name' => 'category',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Enums\\TaskCategory',
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
            'startColumn' => 30,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'evidence' => 
          array (
            'name' => 'evidence',
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
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 54,
            'endColumn' => 68,
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
            'name' => 'App\\Support\\Compliance\\DTOs\\VerificationResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Evaluate whether verification evidence satisfies category requirements.
 *
 * @param  TaskCategory  $category  The task category
 * @param  array<string, mixed>  $evidence  Evidence array from metadata_json.verification_evidence
 */',
        'startLine' => 37,
        'endLine' => 61,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Compliance',
        'declaringClassName' => 'App\\Support\\Compliance\\VerificationEvidenceEvaluator',
        'implementingClassName' => 'App\\Support\\Compliance\\VerificationEvidenceEvaluator',
        'currentClassName' => 'App\\Support\\Compliance\\VerificationEvidenceEvaluator',
        'aliasName' => NULL,
      ),
      'getRequirements' => 
      array (
        'name' => 'getRequirements',
        'parameters' => 
        array (
          'category' => 
          array (
            'name' => 'category',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Enums\\TaskCategory',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 68,
            'endLine' => 68,
            'startColumn' => 37,
            'endColumn' => 58,
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
 * Get the evidence requirements for a given task category.
 *
 * @return array<int, string>
 */',
        'startLine' => 68,
        'endLine' => 71,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Compliance',
        'declaringClassName' => 'App\\Support\\Compliance\\VerificationEvidenceEvaluator',
        'implementingClassName' => 'App\\Support\\Compliance\\VerificationEvidenceEvaluator',
        'currentClassName' => 'App\\Support\\Compliance\\VerificationEvidenceEvaluator',
        'aliasName' => NULL,
      ),
      'hasEvidence' => 
      array (
        'name' => 'hasEvidence',
        'parameters' => 
        array (
          'evidence' => 
          array (
            'name' => 'evidence',
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
            'startLine' => 78,
            'endLine' => 78,
            'startColumn' => 34,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'requirement' => 
          array (
            'name' => 'requirement',
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
            'startLine' => 78,
            'endLine' => 78,
            'startColumn' => 51,
            'endColumn' => 69,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Check if evidence is present and truthy for a given requirement.
 *
 * @param  array<string, mixed>  $evidence
 */',
        'startLine' => 78,
        'endLine' => 88,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Compliance',
        'declaringClassName' => 'App\\Support\\Compliance\\VerificationEvidenceEvaluator',
        'implementingClassName' => 'App\\Support\\Compliance\\VerificationEvidenceEvaluator',
        'currentClassName' => 'App\\Support\\Compliance\\VerificationEvidenceEvaluator',
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