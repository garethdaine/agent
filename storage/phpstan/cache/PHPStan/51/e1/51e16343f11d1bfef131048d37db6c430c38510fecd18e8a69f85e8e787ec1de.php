<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Interrogation/InterrogationQuestionBankPlanner.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Interrogation\InterrogationQuestionBankPlanner
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-87acec3756637588a5c1ac7ea74445b5696deca14741a78857b15f1a31e262ce',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Interrogation\\InterrogationQuestionBankPlanner',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Interrogation/InterrogationQuestionBankPlanner.php',
      ),
    ),
    'namespace' => 'App\\Support\\Interrogation',
    'name' => 'App\\Support\\Interrogation\\InterrogationQuestionBankPlanner',
    'shortName' => 'InterrogationQuestionBankPlanner',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 5,
    'endLine' => 201,
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
      'normalize' => 
      array (
        'name' => 'normalize',
        'parameters' => 
        array (
          'rawQuestions' => 
          array (
            'name' => 'rawQuestions',
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
            'startLine' => 22,
            'endLine' => 22,
            'startColumn' => 31,
            'endColumn' => 49,
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
 * @param  array<int, array<string, mixed>>  $rawQuestions
 * @return array<int, array{
 *   canonical_key:string,
 *   question_id:string,
 *   prompt:string,
 *   answer_type:string,
 *   options:array<int,string>,
 *   depends_on:array<int,string>,
 *   category:string,
 *   decision_axis:string,
 *   rationale:string,
 *   priority:int
 * }>
 */',
        'startLine' => 22,
        'endLine' => 110,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Interrogation',
        'declaringClassName' => 'App\\Support\\Interrogation\\InterrogationQuestionBankPlanner',
        'implementingClassName' => 'App\\Support\\Interrogation\\InterrogationQuestionBankPlanner',
        'currentClassName' => 'App\\Support\\Interrogation\\InterrogationQuestionBankPlanner',
        'aliasName' => NULL,
      ),
      'questionIdForCanonicalKey' => 
      array (
        'name' => 'questionIdForCanonicalKey',
        'parameters' => 
        array (
          'canonicalKey' => 
          array (
            'name' => 'canonicalKey',
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
            'startLine' => 112,
            'endLine' => 112,
            'startColumn' => 47,
            'endColumn' => 66,
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
        'docComment' => NULL,
        'startLine' => 112,
        'endLine' => 120,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Interrogation',
        'declaringClassName' => 'App\\Support\\Interrogation\\InterrogationQuestionBankPlanner',
        'implementingClassName' => 'App\\Support\\Interrogation\\InterrogationQuestionBankPlanner',
        'currentClassName' => 'App\\Support\\Interrogation\\InterrogationQuestionBankPlanner',
        'aliasName' => NULL,
      ),
      'nextEligibleQuestion' => 
      array (
        'name' => 'nextEligibleQuestion',
        'parameters' => 
        array (
          'questionBank' => 
          array (
            'name' => 'questionBank',
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
            'startLine' => 131,
            'endLine' => 131,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'askedCanonicalKeys' => 
          array (
            'name' => 'askedCanonicalKeys',
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
            'startLine' => 132,
            'endLine' => 132,
            'startColumn' => 9,
            'endColumn' => 33,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'answeredCanonicalKeys' => 
          array (
            'name' => 'answeredCanonicalKeys',
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
            'startLine' => 133,
            'endLine' => 133,
            'startColumn' => 9,
            'endColumn' => 36,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'suppressedCanonicalKeys' => 
          array (
            'name' => 'suppressedCanonicalKeys',
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
            'startLine' => 134,
            'endLine' => 134,
            'startColumn' => 9,
            'endColumn' => 38,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'askedQuestionTexts' => 
          array (
            'name' => 'askedQuestionTexts',
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
            'startLine' => 135,
            'endLine' => 135,
            'startColumn' => 9,
            'endColumn' => 33,
            'parameterIndex' => 4,
            'isOptional' => false,
          ),
          'deduper' => 
          array (
            'name' => 'deduper',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Interrogation\\InterrogationSemanticDeduper',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 136,
            'endLine' => 136,
            'startColumn' => 9,
            'endColumn' => 45,
            'parameterIndex' => 5,
            'isOptional' => false,
          ),
          'threshold' => 
          array (
            'name' => 'threshold',
            'default' => 
            array (
              'code' => '0.88',
              'attributes' => 
              array (
                'startLine' => 137,
                'endLine' => 137,
                'startTokenPos' => 943,
                'startFilePos' => 5044,
                'endTokenPos' => 943,
                'endFilePos' => 5047,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 137,
            'endLine' => 137,
            'startColumn' => 9,
            'endColumn' => 31,
            'parameterIndex' => 6,
            'isOptional' => true,
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
 * @param  array<int, array<string, mixed>>  $questionBank
 * @param  array<int, string>  $askedCanonicalKeys
 * @param  array<int, string>  $answeredCanonicalKeys
 * @param  array<int, string>  $suppressedCanonicalKeys
 * @param  array<int, string>  $askedQuestionTexts
 * @return array{question:?array<string,mixed>,suppressed:array<int,string>}
 */',
        'startLine' => 130,
        'endLine' => 192,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Interrogation',
        'declaringClassName' => 'App\\Support\\Interrogation\\InterrogationQuestionBankPlanner',
        'implementingClassName' => 'App\\Support\\Interrogation\\InterrogationQuestionBankPlanner',
        'currentClassName' => 'App\\Support\\Interrogation\\InterrogationQuestionBankPlanner',
        'aliasName' => NULL,
      ),
      'normalizeToken' => 
      array (
        'name' => 'normalizeToken',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
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
            'startLine' => 194,
            'endLine' => 194,
            'startColumn' => 37,
            'endColumn' => 49,
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
        'docComment' => NULL,
        'startLine' => 194,
        'endLine' => 200,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Interrogation',
        'declaringClassName' => 'App\\Support\\Interrogation\\InterrogationQuestionBankPlanner',
        'implementingClassName' => 'App\\Support\\Interrogation\\InterrogationQuestionBankPlanner',
        'currentClassName' => 'App\\Support\\Interrogation\\InterrogationQuestionBankPlanner',
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