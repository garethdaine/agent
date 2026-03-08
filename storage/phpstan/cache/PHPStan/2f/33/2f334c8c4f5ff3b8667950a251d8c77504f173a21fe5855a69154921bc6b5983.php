<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Messenger/Observability/CorrelationLogTap.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Messenger\Observability\CorrelationLogTap
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-93da1b6baceaea402cfefa63ba176bb3bcc70c74b64f554f342fb02c4c8df2f5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Messenger\\Observability\\CorrelationLogTap',
        'filename' => '/Users/garethdaine/Code/agent/app/Messenger/Observability/CorrelationLogTap.php',
      ),
    ),
    'namespace' => 'App\\Messenger\\Observability',
    'name' => 'App\\Messenger\\Observability\\CorrelationLogTap',
    'shortName' => 'CorrelationLogTap',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Monolog tap that injects correlation IDs into log entries.
 *
 * This tap adds a correlation_id field to the \'extra\' array of each log record,
 * enabling log aggregation tools to correlate all log entries related to a
 * single message lifecycle.
 *
 * Usage in config/logging.php:
 *
 *     \'messenger\' => [
 *         \'driver\' => \'daily\',
 *         \'path\' => storage_path(\'logs/messenger.log\'),
 *         \'tap\' => [App\\Messenger\\Observability\\CorrelationLogTap::class],
 *     ],
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 24,
    'endLine' => 48,
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
      '__invoke' => 
      array (
        'name' => '__invoke',
        'parameters' => 
        array (
          'logger' => 
          array (
            'name' => 'logger',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 31,
            'endLine' => 31,
            'startColumn' => 30,
            'endColumn' => 36,
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
 * Customize the given logger instance.
 *
 * @param  \\Illuminate\\Log\\Logger  $logger
 */',
        'startLine' => 31,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Observability',
        'declaringClassName' => 'App\\Messenger\\Observability\\CorrelationLogTap',
        'implementingClassName' => 'App\\Messenger\\Observability\\CorrelationLogTap',
        'currentClassName' => 'App\\Messenger\\Observability\\CorrelationLogTap',
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