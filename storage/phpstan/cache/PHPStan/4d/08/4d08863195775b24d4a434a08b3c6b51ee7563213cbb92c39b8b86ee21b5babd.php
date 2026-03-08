<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Http/Controllers/Api/V1/Memory/MemoryRetrievalController.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Http\Controllers\Api\V1\Memory\MemoryRetrievalController
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-72f9ba4f9dafc3dc55edac53012304f8e3c23d9b4437690b9ad20ccf35c251a4',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryRetrievalController',
        'filename' => '/Users/garethdaine/Code/agent/app/Http/Controllers/Api/V1/Memory/MemoryRetrievalController.php',
      ),
    ),
    'namespace' => 'App\\Http\\Controllers\\Api\\V1\\Memory',
    'name' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryRetrievalController',
    'shortName' => 'MemoryRetrievalController',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Memory retrieval API controller.
 *
 * Endpoints:
 * - POST /memory/retrieve - Hybrid retrieval query
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 52,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Http\\Controllers\\Controller',
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
      'hybridRetriever' => 
      array (
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryRetrievalController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryRetrievalController',
        'name' => 'hybridRetriever',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Support\\Memory\\HybridRetriever',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 9,
        'endColumn' => 57,
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
          'hybridRetriever' => 
          array (
            'name' => 'hybridRetriever',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Memory\\HybridRetriever',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 21,
            'endLine' => 21,
            'startColumn' => 9,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 20,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers\\Api\\V1\\Memory',
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryRetrievalController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryRetrievalController',
        'currentClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryRetrievalController',
        'aliasName' => NULL,
      ),
      'retrieve' => 
      array (
        'name' => 'retrieve',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Http\\Requests\\Memory\\RetrieveMemoryRequest',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 30,
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
            'name' => 'Illuminate\\Http\\JsonResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * POST /memory/retrieve
 *
 * Execute hybrid retrieval across semantic, keyword, and graph sources.
 */',
        'startLine' => 29,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Http\\Controllers\\Api\\V1\\Memory',
        'declaringClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryRetrievalController',
        'implementingClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryRetrievalController',
        'currentClassName' => 'App\\Http\\Controllers\\Api\\V1\\Memory\\MemoryRetrievalController',
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