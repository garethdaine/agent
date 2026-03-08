<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Compliance/ComplexityClassifier.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Compliance\ComplexityClassifier
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-fe9f5c2d01d42aa1a51726942b259a15d6ff98ffc77eefdc18b8ec56768cf24c',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Compliance\\ComplexityClassifier',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Compliance/ComplexityClassifier.php',
      ),
    ),
    'namespace' => 'App\\Support\\Compliance',
    'name' => 'App\\Support\\Compliance\\ComplexityClassifier',
    'shortName' => 'ComplexityClassifier',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Classifies task complexity as \'simple\' or \'non_trivial\' based on heuristics and explicit overrides.
 *
 * Heuristics evaluate file count, LOC count, and directory count against configurable thresholds.
 * Explicit overrides (force_simple, force_non_trivial) take precedence over heuristics.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 83,
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
      'thresholds' => 
      array (
        'declaringClassName' => 'App\\Support\\Compliance\\ComplexityClassifier',
        'implementingClassName' => 'App\\Support\\Compliance\\ComplexityClassifier',
        'name' => 'thresholds',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 9,
        'endColumn' => 42,
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
          'thresholds' => 
          array (
            'name' => 'thresholds',
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
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 19,
            'endLine' => 19,
            'startColumn' => 9,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array{file_count: int, loc_count: int, directory_count: int}  $thresholds
 */',
        'startLine' => 18,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Compliance',
        'declaringClassName' => 'App\\Support\\Compliance\\ComplexityClassifier',
        'implementingClassName' => 'App\\Support\\Compliance\\ComplexityClassifier',
        'currentClassName' => 'App\\Support\\Compliance\\ComplexityClassifier',
        'aliasName' => NULL,
      ),
      'fromConfig' => 
      array (
        'name' => 'fromConfig',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a classifier instance using configuration thresholds.
 */',
        'startLine' => 25,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Support\\Compliance',
        'declaringClassName' => 'App\\Support\\Compliance\\ComplexityClassifier',
        'implementingClassName' => 'App\\Support\\Compliance\\ComplexityClassifier',
        'currentClassName' => 'App\\Support\\Compliance\\ComplexityClassifier',
        'aliasName' => NULL,
      ),
      'classify' => 
      array (
        'name' => 'classify',
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
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 30,
            'endColumn' => 43,
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
            'name' => 'App\\Support\\Compliance\\DTOs\\ComplexityResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Classify task complexity based on context metrics and overrides.
 *
 * @param  array<string, mixed>  $context  Context containing file_count, loc_count, directory_count, and optional complexity_override
 */',
        'startLine' => 42,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Compliance',
        'declaringClassName' => 'App\\Support\\Compliance\\ComplexityClassifier',
        'implementingClassName' => 'App\\Support\\Compliance\\ComplexityClassifier',
        'currentClassName' => 'App\\Support\\Compliance\\ComplexityClassifier',
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