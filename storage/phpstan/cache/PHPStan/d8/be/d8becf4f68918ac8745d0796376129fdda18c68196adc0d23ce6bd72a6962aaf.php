<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Org/Synthesis/MajoritySynthesisStrategy.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Org\Synthesis\MajoritySynthesisStrategy
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-c62eb0ef66395f37b792d740d800a3c5b0c009ffeae02c27acc33296fda8914d',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Org\\Synthesis\\MajoritySynthesisStrategy',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Org/Synthesis/MajoritySynthesisStrategy.php',
      ),
    ),
    'namespace' => 'App\\Support\\Org\\Synthesis',
    'name' => 'App\\Support\\Org\\Synthesis\\MajoritySynthesisStrategy',
    'shortName' => 'MajoritySynthesisStrategy',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Majority vote synthesis strategy.
 *
 * Implements simple majority voting - the decision with more than 50% of
 * votes wins. If no decision has a clear majority (tie or three-way split),
 * the result is marked as a tie with no decision.
 *
 * Deterministic behavior:
 * - Counts votes for each unique decision value
 * - Decision with strict majority (> 50%) wins
 * - Ties or pluralities without majority result in null decision
 * - Generates conflict entries when responses disagree
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
                'startFilePos' => 746,
                'endTokenPos' => 47,
                'endFilePos' => 747,
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
        'endLine' => 92,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Org\\Synthesis',
        'declaringClassName' => 'App\\Support\\Org\\Synthesis\\MajoritySynthesisStrategy',
        'implementingClassName' => 'App\\Support\\Org\\Synthesis\\MajoritySynthesisStrategy',
        'currentClassName' => 'App\\Support\\Org\\Synthesis\\MajoritySynthesisStrategy',
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
        'startLine' => 94,
        'endLine' => 97,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Org\\Synthesis',
        'declaringClassName' => 'App\\Support\\Org\\Synthesis\\MajoritySynthesisStrategy',
        'implementingClassName' => 'App\\Support\\Org\\Synthesis\\MajoritySynthesisStrategy',
        'currentClassName' => 'App\\Support\\Org\\Synthesis\\MajoritySynthesisStrategy',
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
            'startLine' => 105,
            'endLine' => 105,
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
            'startLine' => 105,
            'endLine' => 105,
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
 *
 * A conflict is generated for each unique decision position, capturing
 * the perspective and reasoning of the voters who took that position.
 */',
        'startLine' => 105,
        'endLine' => 136,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Org\\Synthesis',
        'declaringClassName' => 'App\\Support\\Org\\Synthesis\\MajoritySynthesisStrategy',
        'implementingClassName' => 'App\\Support\\Org\\Synthesis\\MajoritySynthesisStrategy',
        'currentClassName' => 'App\\Support\\Org\\Synthesis\\MajoritySynthesisStrategy',
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