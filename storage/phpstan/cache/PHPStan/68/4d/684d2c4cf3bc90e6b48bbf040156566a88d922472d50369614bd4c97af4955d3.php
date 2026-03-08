<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Providers/MemoryServiceProvider.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Providers\MemoryServiceProvider
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-bbabf3ebe9e71b2d9a85d51d16e2417e91ffedd5d0b993a71ffa7802bac60890',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Providers\\MemoryServiceProvider',
        'filename' => '/Users/garethdaine/Code/agent/app/Providers/MemoryServiceProvider.php',
      ),
    ),
    'namespace' => 'App\\Providers',
    'name' => 'App\\Providers\\MemoryServiceProvider',
    'shortName' => 'MemoryServiceProvider',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Memory Service Provider.
 *
 * Registers all memory system services with FeatureFlagManager-gated bindings.
 * When memory is not enabled (via DB override or config), services throw clear errors.
 *
 * The flag check is deferred to resolution time (not registration time)
 * to allow tests to configure flags before resolving services.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 33,
    'endLine' => 168,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Support\\ServiceProvider',
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
      'register' => 
      array (
        'name' => 'register',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register any application services.
 */',
        'startLine' => 38,
        'endLine' => 139,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Providers',
        'declaringClassName' => 'App\\Providers\\MemoryServiceProvider',
        'implementingClassName' => 'App\\Providers\\MemoryServiceProvider',
        'currentClassName' => 'App\\Providers\\MemoryServiceProvider',
        'aliasName' => NULL,
      ),
      'boot' => 
      array (
        'name' => 'boot',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Bootstrap any application services.
 */',
        'startLine' => 144,
        'endLine' => 151,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Providers',
        'declaringClassName' => 'App\\Providers\\MemoryServiceProvider',
        'implementingClassName' => 'App\\Providers\\MemoryServiceProvider',
        'currentClassName' => 'App\\Providers\\MemoryServiceProvider',
        'aliasName' => NULL,
      ),
      'ensureMemoryEnabled' => 
      array (
        'name' => 'ensureMemoryEnabled',
        'parameters' => 
        array (
          'app' => 
          array (
            'name' => 'app',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 158,
            'endLine' => 158,
            'startColumn' => 42,
            'endColumn' => 45,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Ensure memory is enabled via FeatureFlagManager before resolving a service.
 *
 * @throws \\RuntimeException when memory is disabled
 */',
        'startLine' => 158,
        'endLine' => 167,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Providers',
        'declaringClassName' => 'App\\Providers\\MemoryServiceProvider',
        'implementingClassName' => 'App\\Providers\\MemoryServiceProvider',
        'currentClassName' => 'App\\Providers\\MemoryServiceProvider',
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