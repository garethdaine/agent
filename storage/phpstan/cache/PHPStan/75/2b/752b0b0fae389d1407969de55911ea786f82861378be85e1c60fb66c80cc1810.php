<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Org/Synthesis/WeightedSynthesisStrategy.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Org\Synthesis\WeightedSynthesisStrategy
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-f7e2335932d95741f00a298f3b1353360269af8939ef8bd46341c3cf6137066f',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Org\\Synthesis\\WeightedSynthesisStrategy',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Org/Synthesis/WeightedSynthesisStrategy.php',
      ),
    ),
    'namespace' => 'App\\Support\\Org\\Synthesis',
    'name' => 'App\\Support\\Org\\Synthesis\\WeightedSynthesisStrategy',
    'shortName' => 'WeightedSynthesisStrategy',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Weighted vote synthesis strategy.
 *
 * Implements weighted voting - each council member\'s vote is multiplied by
 * their assigned weight. The decision with the highest total weight wins.
 *
 * Deterministic behavior:
 * - Sums weighted votes for each unique decision value
 * - Decision with highest total weighted score wins
 * - Ties (equal weighted scores) result in null decision
 * - Default weight is 1 if not specified in member list
 * - Generates conflict entries when responses disagree
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 20,
    'endLine' => 158,
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
                'startFilePos' => 756,
                'endTokenPos' => 47,
                'endFilePos' => 757,
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
        'endLine' => 116,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Org\\Synthesis',
        'declaringClassName' => 'App\\Support\\Org\\Synthesis\\WeightedSynthesisStrategy',
        'implementingClassName' => 'App\\Support\\Org\\Synthesis\\WeightedSynthesisStrategy',
        'currentClassName' => 'App\\Support\\Org\\Synthesis\\WeightedSynthesisStrategy',
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
        'startLine' => 118,
        'endLine' => 121,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Org\\Synthesis',
        'declaringClassName' => 'App\\Support\\Org\\Synthesis\\WeightedSynthesisStrategy',
        'implementingClassName' => 'App\\Support\\Org\\Synthesis\\WeightedSynthesisStrategy',
        'currentClassName' => 'App\\Support\\Org\\Synthesis\\WeightedSynthesisStrategy',
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
            'startLine' => 126,
            'endLine' => 126,
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
            'startLine' => 126,
            'endLine' => 126,
            'startColumn' => 58,
            'endColumn' => 78,
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
 * Generate conflict entries when council members disagree.
 */',
        'startLine' => 126,
        'endLine' => 157,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Org\\Synthesis',
        'declaringClassName' => 'App\\Support\\Org\\Synthesis\\WeightedSynthesisStrategy',
        'implementingClassName' => 'App\\Support\\Org\\Synthesis\\WeightedSynthesisStrategy',
        'currentClassName' => 'App\\Support\\Org\\Synthesis\\WeightedSynthesisStrategy',
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