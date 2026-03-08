<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Org/Synthesis/ChairDecidesSynthesisStrategy.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Org\Synthesis\ChairDecidesSynthesisStrategy
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-cebc8c8710cb68dd8272d8d2c9900301d7fed432a103f28d7121550147cbf6b6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Org\\Synthesis\\ChairDecidesSynthesisStrategy',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Org/Synthesis/ChairDecidesSynthesisStrategy.php',
      ),
    ),
    'namespace' => 'App\\Support\\Org\\Synthesis',
    'name' => 'App\\Support\\Org\\Synthesis\\ChairDecidesSynthesisStrategy',
    'shortName' => 'ChairDecidesSynthesisStrategy',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Chair-decides synthesis strategy.
 *
 * The designated chair\'s decision is final. Other council member votes are
 * recorded for context and conflict reporting but do not affect the outcome.
 *
 * Deterministic behavior:
 * - Finds the chair member from the member list (is_chair = true)
 * - Chair\'s decision becomes the final decision
 * - If chair has no response, result is null decision
 * - Other votes are recorded in vote breakdown
 * - Generates conflict entries when non-chair members disagree with chair
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 20,
    'endLine' => 137,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'App\\Support\\Org\\Synthesis\\SynthesisStrategyInterface',
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
      'synthesize' => 
      array (
        'name' => 'synthesize',
        'parameters' => 
        array (
          'responses' => 
          array (
            'name' => 'responses',
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
            'startColumn' => 32,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'decisionField' => 
          array (
            'name' => 'decisionField',
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
            'startLine' => 22,
            'endLine' => 22,
            'startColumn' => 50,
            'endColumn' => 70,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'memberList' => 
          array (
            'name' => 'memberList',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 22,
                'endLine' => 22,
                'startTokenPos' => 46,
                'startFilePos' => 777,
                'endTokenPos' => 47,
                'endFilePos' => 778,
              ),
            ),
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
            'startColumn' => 73,
            'endColumn' => 94,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Support\\Org\\SynthesisResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 22,
        'endLine' => 102,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Org\\Synthesis',
        'declaringClassName' => 'App\\Support\\Org\\Synthesis\\ChairDecidesSynthesisStrategy',
        'implementingClassName' => 'App\\Support\\Org\\Synthesis\\ChairDecidesSynthesisStrategy',
        'currentClassName' => 'App\\Support\\Org\\Synthesis\\ChairDecidesSynthesisStrategy',
        'aliasName' => NULL,
      ),
      'getMode' => 
      array (
        'name' => 'getMode',
        'parameters' => 
        array (
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
        'startLine' => 104,
        'endLine' => 107,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Org\\Synthesis',
        'declaringClassName' => 'App\\Support\\Org\\Synthesis\\ChairDecidesSynthesisStrategy',
        'implementingClassName' => 'App\\Support\\Org\\Synthesis\\ChairDecidesSynthesisStrategy',
        'currentClassName' => 'App\\Support\\Org\\Synthesis\\ChairDecidesSynthesisStrategy',
        'aliasName' => NULL,
      ),
      'generateConflicts' => 
      array (
        'name' => 'generateConflicts',
        'parameters' => 
        array (
          'responses' => 
          array (
            'name' => 'responses',
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
            'startLine' => 112,
            'endLine' => 112,
            'startColumn' => 40,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'decisionField' => 
          array (
            'name' => 'decisionField',
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
            'startColumn' => 58,
            'endColumn' => 78,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'chairDecision' => 
          array (
            'name' => 'chairDecision',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
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
            'startColumn' => 81,
            'endColumn' => 102,
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
 * Generate conflict entries for members who disagree with the chair\'s decision.
 */',
        'startLine' => 112,
        'endLine' => 136,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Org\\Synthesis',
        'declaringClassName' => 'App\\Support\\Org\\Synthesis\\ChairDecidesSynthesisStrategy',
        'implementingClassName' => 'App\\Support\\Org\\Synthesis\\ChairDecidesSynthesisStrategy',
        'currentClassName' => 'App\\Support\\Org\\Synthesis\\ChairDecidesSynthesisStrategy',
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