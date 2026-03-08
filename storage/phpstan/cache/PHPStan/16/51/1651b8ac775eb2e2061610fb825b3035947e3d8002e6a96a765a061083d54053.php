<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Connectors/Telemetry/ConnectorCostAttributor.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Connectors\Telemetry\ConnectorCostAttributor
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-685e8854a9a166be61e02972ff062eb493d3042c615840d2d98e0ab19671ba85',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Connectors\\Telemetry\\ConnectorCostAttributor',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Connectors/Telemetry/ConnectorCostAttributor.php',
      ),
    ),
    'namespace' => 'App\\Support\\Connectors\\Telemetry',
    'name' => 'App\\Support\\Connectors\\Telemetry\\ConnectorCostAttributor',
    'shortName' => 'ConnectorCostAttributor',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 11,
    'endLine' => 92,
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
      'attributeCost' => 
      array (
        'name' => 'attributeCost',
        'parameters' => 
        array (
          'invocation' => 
          array (
            'name' => 'invocation',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\AgentConnectorInvocation',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 20,
            'endLine' => 20,
            'startColumn' => 35,
            'endColumn' => 70,
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
            'name' => 'float',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Attribute the cost of a connector invocation based on the connector\'s cost model.
 *
 * For \'metered\' connectors: calculates per-call cost from declared pricing
 * (cost_per_call_usd or cost_per_token_usd × token_usage).
 * For \'free\' and \'quota-limited\' connectors: returns 0.0.
 */',
        'startLine' => 20,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Connectors\\Telemetry',
        'declaringClassName' => 'App\\Support\\Connectors\\Telemetry\\ConnectorCostAttributor',
        'implementingClassName' => 'App\\Support\\Connectors\\Telemetry\\ConnectorCostAttributor',
        'currentClassName' => 'App\\Support\\Connectors\\Telemetry\\ConnectorCostAttributor',
        'aliasName' => NULL,
      ),
      'getRemainingQuota' => 
      array (
        'name' => 'getRemainingQuota',
        'parameters' => 
        array (
          'invocation' => 
          array (
            'name' => 'invocation',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\AgentConnectorInvocation',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 54,
            'endLine' => 54,
            'startColumn' => 39,
            'endColumn' => 74,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
                  'name' => 'int',
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
 * Get the remaining quota for a quota-limited connector invocation.
 *
 * Returns null for non-quota-limited connectors or when no daily_limit is configured.
 */',
        'startLine' => 54,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Connectors\\Telemetry',
        'declaringClassName' => 'App\\Support\\Connectors\\Telemetry\\ConnectorCostAttributor',
        'implementingClassName' => 'App\\Support\\Connectors\\Telemetry\\ConnectorCostAttributor',
        'currentClassName' => 'App\\Support\\Connectors\\Telemetry\\ConnectorCostAttributor',
        'aliasName' => NULL,
      ),
      'resolveConnector' => 
      array (
        'name' => 'resolveConnector',
        'parameters' => 
        array (
          'invocation' => 
          array (
            'name' => 'invocation',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\AgentConnectorInvocation',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 75,
            'endLine' => 75,
            'startColumn' => 39,
            'endColumn' => 74,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
                  'name' => 'App\\Models\\AgentConnector',
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
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 75,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Connectors\\Telemetry',
        'declaringClassName' => 'App\\Support\\Connectors\\Telemetry\\ConnectorCostAttributor',
        'implementingClassName' => 'App\\Support\\Connectors\\Telemetry\\ConnectorCostAttributor',
        'currentClassName' => 'App\\Support\\Connectors\\Telemetry\\ConnectorCostAttributor',
        'aliasName' => NULL,
      ),
      'resolveConnection' => 
      array (
        'name' => 'resolveConnection',
        'parameters' => 
        array (
          'invocation' => 
          array (
            'name' => 'invocation',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\AgentConnectorInvocation',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 84,
            'endLine' => 84,
            'startColumn' => 40,
            'endColumn' => 75,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
                  'name' => 'App\\Models\\AgentConnectorConnection',
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
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 84,
        'endLine' => 91,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Connectors\\Telemetry',
        'declaringClassName' => 'App\\Support\\Connectors\\Telemetry\\ConnectorCostAttributor',
        'implementingClassName' => 'App\\Support\\Connectors\\Telemetry\\ConnectorCostAttributor',
        'currentClassName' => 'App\\Support\\Connectors\\Telemetry\\ConnectorCostAttributor',
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