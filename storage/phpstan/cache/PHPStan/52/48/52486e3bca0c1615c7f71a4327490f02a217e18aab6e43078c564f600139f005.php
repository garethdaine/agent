<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Org/Synthesis/SynthesisStrategyInterface.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Org\Synthesis\SynthesisStrategyInterface
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-693c11181ebd631afcefc83a51f8fd59bafb156a9bc9e1349316bd1590208cbd',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Org\\Synthesis\\SynthesisStrategyInterface',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Org/Synthesis/SynthesisStrategyInterface.php',
      ),
    ),
    'namespace' => 'App\\Support\\Org\\Synthesis',
    'name' => 'App\\Support\\Org\\Synthesis\\SynthesisStrategyInterface',
    'shortName' => 'SynthesisStrategyInterface',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Interface for deterministic council synthesis strategies.
 *
 * All synthesis strategies are deterministic - they apply rules-based logic
 * to aggregate council member responses into a single decision. Model-mediated
 * synthesis is opt-in and handled separately.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 30,
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
            'startLine' => 24,
            'endLine' => 24,
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
            'startLine' => 24,
            'endLine' => 24,
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
                'startLine' => 24,
                'endLine' => 24,
                'startTokenPos' => 44,
                'startFilePos' => 1004,
                'endTokenPos' => 45,
                'endFilePos' => 1005,
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
            'startLine' => 24,
            'endLine' => 24,
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
        'docComment' => '/**
 * Synthesize responses from council members into a single result.
 *
 * @param  array  $responses  Array of council member responses, each containing at least the decision field
 * @param  string  $decisionField  The key in each response that contains the decision value
 * @param  array  $memberList  Optional member list from the council template (for weights, chair, etc.)
 * @return SynthesisResult The synthesized result including decision, vote counts, and conflicts
 */',
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 113,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Org\\Synthesis',
        'declaringClassName' => 'App\\Support\\Org\\Synthesis\\SynthesisStrategyInterface',
        'implementingClassName' => 'App\\Support\\Org\\Synthesis\\SynthesisStrategyInterface',
        'currentClassName' => 'App\\Support\\Org\\Synthesis\\SynthesisStrategyInterface',
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
        'docComment' => '/**
 * Get the synthesis mode identifier.
 */',
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 38,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Org\\Synthesis',
        'declaringClassName' => 'App\\Support\\Org\\Synthesis\\SynthesisStrategyInterface',
        'implementingClassName' => 'App\\Support\\Org\\Synthesis\\SynthesisStrategyInterface',
        'currentClassName' => 'App\\Support\\Org\\Synthesis\\SynthesisStrategyInterface',
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