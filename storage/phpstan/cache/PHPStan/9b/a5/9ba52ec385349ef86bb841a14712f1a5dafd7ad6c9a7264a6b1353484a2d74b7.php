<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Connectors/Telemetry/ConnectorReliabilityContributor.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Connectors\Telemetry\ConnectorReliabilityContributor
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-7bb9887b43651998298cb89ae56b664b808d78e0438503a22cd6d9af176bf7f7',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Connectors\\Telemetry\\ConnectorReliabilityContributor',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Connectors/Telemetry/ConnectorReliabilityContributor.php',
      ),
    ),
    'namespace' => 'App\\Support\\Connectors\\Telemetry',
    'name' => 'App\\Support\\Connectors\\Telemetry\\ConnectorReliabilityContributor',
    'shortName' => 'ConnectorReliabilityContributor',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 40,
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
      'getReliabilityContribution' => 
      array (
        'name' => 'getReliabilityContribution',
        'parameters' => 
        array (
          'workflowKey' => 
          array (
            'name' => 'workflowKey',
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
            'startLine' => 21,
            'endLine' => 21,
            'startColumn' => 48,
            'endColumn' => 66,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'from' => 
          array (
            'name' => 'from',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Carbon\\Carbon',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 21,
            'endLine' => 21,
            'startColumn' => 69,
            'endColumn' => 80,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'to' => 
          array (
            'name' => 'to',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Carbon\\Carbon',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 21,
            'endLine' => 21,
            'startColumn' => 83,
            'endColumn' => 92,
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
            'name' => 'float',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Calculate the connector reliability contribution for a workflow over a time range.
 *
 * Returns a float between 0.0 and 1.0 representing the success rate of
 * connector invocations for the given workflow. A value of 1.0 means all
 * invocations succeeded; 0.0 means all failed.
 *
 * When no invocations exist for the time range, returns 1.0 (no degradation signal).
 */',
        'startLine' => 21,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Connectors\\Telemetry',
        'declaringClassName' => 'App\\Support\\Connectors\\Telemetry\\ConnectorReliabilityContributor',
        'implementingClassName' => 'App\\Support\\Connectors\\Telemetry\\ConnectorReliabilityContributor',
        'currentClassName' => 'App\\Support\\Connectors\\Telemetry\\ConnectorReliabilityContributor',
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