<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Pipeline/Pipeline.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Pipeline\Pipeline
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-c9a4835e189aba69d39ca93f8616db96905aeb0d19d4f263974f0082c77ed974-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Pipeline\\Pipeline',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Pipeline/Pipeline.php',
      ),
    ),
    'namespace' => 'Illuminate\\Pipeline',
    'name' => 'Illuminate\\Pipeline\\Pipeline',
    'shortName' => 'Pipeline',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 325,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Contracts\\Pipeline\\Pipeline',
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Support\\Traits\\Conditionable',
      1 => 'Illuminate\\Support\\Traits\\Macroable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'container' => 
      array (
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'name' => 'container',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The container implementation.
 *
 * @var \\Illuminate\\Contracts\\Container\\Container|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'passable' => 
      array (
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'name' => 'passable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The object being passed through the pipeline.
 *
 * @var mixed
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 24,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'pipes' => 
      array (
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'name' => 'pipes',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 37,
            'endLine' => 37,
            'startTokenPos' => 88,
            'startFilePos' => 737,
            'endTokenPos' => 89,
            'endFilePos' => 738,
          ),
        ),
        'docComment' => '/**
 * The array of class pipes.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 26,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'method' => 
      array (
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'name' => 'method',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'handle\'',
          'attributes' => 
          array (
            'startLine' => 44,
            'endLine' => 44,
            'startTokenPos' => 100,
            'startFilePos' => 848,
            'endTokenPos' => 100,
            'endFilePos' => 855,
          ),
        ),
        'docComment' => '/**
 * The method to call on each pipe.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 44,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'finally' => 
      array (
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'name' => 'finally',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The final callback to be executed after the pipeline ends regardless of the outcome.
 *
 * @var \\Closure|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 51,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'withinTransaction' => 
      array (
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'name' => 'withinTransaction',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 58,
            'endLine' => 58,
            'startTokenPos' => 118,
            'startFilePos' => 1196,
            'endTokenPos' => 118,
            'endFilePos' => 1200,
          ),
        ),
        'docComment' => '/**
 * Indicates whether to wrap the pipeline in a database transaction.
 *
 * @var string|null|\\UnitEnum|false
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 58,
        'endLine' => 58,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
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
          'container' => 
          array (
            'name' => 'container',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 65,
                'endLine' => 65,
                'startTokenPos' => 136,
                'startFilePos' => 1393,
                'endTokenPos' => 136,
                'endFilePos' => 1396,
              ),
            ),
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
                      'name' => 'Illuminate\\Contracts\\Container\\Container',
                      'isIdentifier' => false,
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
            'startLine' => 65,
            'endLine' => 65,
            'startColumn' => 33,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new class instance.
 *
 * @param  \\Illuminate\\Contracts\\Container\\Container|null  $container
 */',
        'startLine' => 65,
        'endLine' => 68,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'aliasName' => NULL,
      ),
      'send' => 
      array (
        'name' => 'send',
        'parameters' => 
        array (
          'passable' => 
          array (
            'name' => 'passable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 76,
            'endLine' => 76,
            'startColumn' => 26,
            'endColumn' => 34,
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
 * Set the object being sent through the pipeline.
 *
 * @param  mixed  $passable
 * @return $this
 */',
        'startLine' => 76,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'aliasName' => NULL,
      ),
      'through' => 
      array (
        'name' => 'through',
        'parameters' => 
        array (
          'pipes' => 
          array (
            'name' => 'pipes',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 89,
            'endLine' => 89,
            'startColumn' => 29,
            'endColumn' => 34,
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
 * Set the array of pipes.
 *
 * @param  mixed  $pipes
 * @return $this
 */',
        'startLine' => 89,
        'endLine' => 94,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'aliasName' => NULL,
      ),
      'pipe' => 
      array (
        'name' => 'pipe',
        'parameters' => 
        array (
          'pipes' => 
          array (
            'name' => 'pipes',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 102,
            'endLine' => 102,
            'startColumn' => 26,
            'endColumn' => 31,
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
 * Push additional pipes onto the pipeline.
 *
 * @param  mixed  $pipes
 * @return $this
 */',
        'startLine' => 102,
        'endLine' => 107,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'aliasName' => NULL,
      ),
      'via' => 
      array (
        'name' => 'via',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 115,
            'endLine' => 115,
            'startColumn' => 25,
            'endColumn' => 31,
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
 * Set the method to call on the pipes.
 *
 * @param  string  $method
 * @return $this
 */',
        'startLine' => 115,
        'endLine' => 120,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'aliasName' => NULL,
      ),
      'then' => 
      array (
        'name' => 'then',
        'parameters' => 
        array (
          'destination' => 
          array (
            'name' => 'destination',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 128,
            'endLine' => 128,
            'startColumn' => 26,
            'endColumn' => 45,
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
 * Run the pipeline with a final destination callback.
 *
 * @param  \\Closure  $destination
 * @return mixed
 */',
        'startLine' => 128,
        'endLine' => 143,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'aliasName' => NULL,
      ),
      'thenReturn' => 
      array (
        'name' => 'thenReturn',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Run the pipeline and return the result.
 *
 * @return mixed
 */',
        'startLine' => 150,
        'endLine' => 155,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'aliasName' => NULL,
      ),
      'finally' => 
      array (
        'name' => 'finally',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 163,
            'endLine' => 163,
            'startColumn' => 29,
            'endColumn' => 45,
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
 * Set a final callback to be executed after the pipeline ends regardless of the outcome.
 *
 * @param  \\Closure  $callback
 * @return $this
 */',
        'startLine' => 163,
        'endLine' => 168,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'aliasName' => NULL,
      ),
      'prepareDestination' => 
      array (
        'name' => 'prepareDestination',
        'parameters' => 
        array (
          'destination' => 
          array (
            'name' => 'destination',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 176,
            'endLine' => 176,
            'startColumn' => 43,
            'endColumn' => 62,
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
 * Get the final piece of the Closure onion.
 *
 * @param  \\Closure  $destination
 * @return \\Closure
 */',
        'startLine' => 176,
        'endLine' => 185,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'aliasName' => NULL,
      ),
      'carry' => 
      array (
        'name' => 'carry',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a Closure that represents a slice of the application onion.
 *
 * @return \\Closure
 */',
        'startLine' => 192,
        'endLine' => 228,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'aliasName' => NULL,
      ),
      'parsePipeString' => 
      array (
        'name' => 'parsePipeString',
        'parameters' => 
        array (
          'pipe' => 
          array (
            'name' => 'pipe',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 236,
            'endLine' => 236,
            'startColumn' => 40,
            'endColumn' => 44,
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
 * Parse full pipe string to get name and parameters.
 *
 * @param  string  $pipe
 * @return array
 */',
        'startLine' => 236,
        'endLine' => 247,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'aliasName' => NULL,
      ),
      'pipes' => 
      array (
        'name' => 'pipes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the array of configured pipes.
 *
 * @return array
 */',
        'startLine' => 254,
        'endLine' => 257,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'aliasName' => NULL,
      ),
      'withinTransaction' => 
      array (
        'name' => 'withinTransaction',
        'parameters' => 
        array (
          'withinTransaction' => 
          array (
            'name' => 'withinTransaction',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 265,
                'endLine' => 265,
                'startTokenPos' => 972,
                'startFilePos' => 7047,
                'endTokenPos' => 972,
                'endFilePos' => 7050,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 265,
            'endLine' => 265,
            'startColumn' => 39,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute each pipeline step within a database transaction.
 *
 * @param  string|null|\\UnitEnum|false  $withinTransaction
 * @return $this
 */',
        'startLine' => 265,
        'endLine' => 270,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'aliasName' => NULL,
      ),
      'getContainer' => 
      array (
        'name' => 'getContainer',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the container instance.
 *
 * @return \\Illuminate\\Contracts\\Container\\Container
 *
 * @throws \\RuntimeException
 */',
        'startLine' => 279,
        'endLine' => 286,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'aliasName' => NULL,
      ),
      'setContainer' => 
      array (
        'name' => 'setContainer',
        'parameters' => 
        array (
          'container' => 
          array (
            'name' => 'container',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Container\\Container',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 294,
            'endLine' => 294,
            'startColumn' => 34,
            'endColumn' => 53,
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
 * Set the container instance.
 *
 * @param  \\Illuminate\\Contracts\\Container\\Container  $container
 * @return $this
 */',
        'startLine' => 294,
        'endLine' => 299,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'aliasName' => NULL,
      ),
      'handleCarry' => 
      array (
        'name' => 'handleCarry',
        'parameters' => 
        array (
          'carry' => 
          array (
            'name' => 'carry',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 307,
            'endLine' => 307,
            'startColumn' => 36,
            'endColumn' => 41,
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
 * Handle the value returned from each pipe before passing it to the next.
 *
 * @param  mixed  $carry
 * @return mixed
 */',
        'startLine' => 307,
        'endLine' => 310,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'aliasName' => NULL,
      ),
      'handleException' => 
      array (
        'name' => 'handleException',
        'parameters' => 
        array (
          'passable' => 
          array (
            'name' => 'passable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 321,
            'endLine' => 321,
            'startColumn' => 40,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'e' => 
          array (
            'name' => 'e',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Throwable',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 321,
            'endLine' => 321,
            'startColumn' => 51,
            'endColumn' => 62,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Handle the given exception.
 *
 * @param  mixed  $passable
 * @param  \\Throwable  $e
 * @return mixed
 *
 * @throws \\Throwable
 */',
        'startLine' => 321,
        'endLine' => 324,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Pipeline',
        'declaringClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'implementingClassName' => 'Illuminate\\Pipeline\\Pipeline',
        'currentClassName' => 'Illuminate\\Pipeline\\Pipeline',
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