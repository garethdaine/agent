<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Messenger/Observability/CorrelationContext.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Messenger\Observability\CorrelationContext
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-32167bdbc542474043696fb8c960d41cfa4e808eb1f3a7d379996fff702dfe2a',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Messenger\\Observability\\CorrelationContext',
        'filename' => '/Users/garethdaine/Code/agent/app/Messenger/Observability/CorrelationContext.php',
      ),
    ),
    'namespace' => 'App\\Messenger\\Observability',
    'name' => 'App\\Messenger\\Observability\\CorrelationContext',
    'shortName' => 'CorrelationContext',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Manages correlation IDs for tracking message lifecycles through the system.
 *
 * Correlation IDs are stored in Laravel\'s Context for thread-local storage,
 * ensuring they persist across method calls within the same request lifecycle
 * while being automatically isolated between concurrent requests.
 *
 * Scope: single message lifecycle (webhook receipt → parse → execute → response)
 * Resets for each new inbound message.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 20,
    'endLine' => 74,
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
      'KEY' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Observability\\CorrelationContext',
        'implementingClassName' => 'App\\Messenger\\Observability\\CorrelationContext',
        'name' => 'KEY',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'messenger_correlation_id\'',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 41,
            'startFilePos' => 637,
            'endTokenPos' => 41,
            'endFilePos' => 662,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 51,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'generate' => 
      array (
        'name' => 'generate',
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
 * Generate a new correlation ID and store it in context.
 *
 * @return string The newly generated UUID
 */',
        'startLine' => 29,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Observability',
        'declaringClassName' => 'App\\Messenger\\Observability\\CorrelationContext',
        'implementingClassName' => 'App\\Messenger\\Observability\\CorrelationContext',
        'currentClassName' => 'App\\Messenger\\Observability\\CorrelationContext',
        'aliasName' => NULL,
      ),
      'get' => 
      array (
        'name' => 'get',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
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
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the current correlation ID from context.
 *
 * @return string|null The current correlation ID or null if not set
 */',
        'startLine' => 42,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Observability',
        'declaringClassName' => 'App\\Messenger\\Observability\\CorrelationContext',
        'implementingClassName' => 'App\\Messenger\\Observability\\CorrelationContext',
        'currentClassName' => 'App\\Messenger\\Observability\\CorrelationContext',
        'aliasName' => NULL,
      ),
      'reset' => 
      array (
        'name' => 'reset',
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
 * Clear the current correlation ID and generate a new one.
 *
 * Call this at the start of each new inbound message to ensure
 * a fresh correlation ID for the new message lifecycle.
 *
 * @return string The newly generated UUID
 */',
        'startLine' => 55,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Observability',
        'declaringClassName' => 'App\\Messenger\\Observability\\CorrelationContext',
        'implementingClassName' => 'App\\Messenger\\Observability\\CorrelationContext',
        'currentClassName' => 'App\\Messenger\\Observability\\CorrelationContext',
        'aliasName' => NULL,
      ),
      'getOrGenerate' => 
      array (
        'name' => 'getOrGenerate',
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
 * Get the current correlation ID, or generate a new one if not set.
 *
 * Useful for lazy initialization when you want to ensure a correlation
 * ID exists but don\'t want to override an existing one.
 *
 * @return string The current or newly generated UUID
 */',
        'startLine' => 70,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Observability',
        'declaringClassName' => 'App\\Messenger\\Observability\\CorrelationContext',
        'implementingClassName' => 'App\\Messenger\\Observability\\CorrelationContext',
        'currentClassName' => 'App\\Messenger\\Observability\\CorrelationContext',
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