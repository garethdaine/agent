<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Connectors/Mcp/ConnectorMcpRegistrar.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Connectors\Mcp\ConnectorMcpRegistrar
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-afa6bd1391399e430d33f3eb0919dc19d5be6ff3d7a1fb935ed833e69e45ef83',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Connectors\\Mcp\\ConnectorMcpRegistrar',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Connectors/Mcp/ConnectorMcpRegistrar.php',
      ),
    ),
    'namespace' => 'App\\Support\\Connectors\\Mcp',
    'name' => 'App\\Support\\Connectors\\Mcp\\ConnectorMcpRegistrar',
    'shortName' => 'ConnectorMcpRegistrar',
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
    'endLine' => 97,
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
      'getRegisteredTools' => 
      array (
        'name' => 'getRegisteredTools',
        'parameters' => 
        array (
          'team' => 
          array (
            'name' => 'team',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\Team',
                'isIdentifier' => false,
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
            'startColumn' => 40,
            'endColumn' => 49,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get MCP tool definitions for all connected connectors belonging to a team.
 *
 * Each tool definition includes:
 * - name: {mcp_tool_prefix}.{action_name}
 * - description: Generated from connector and action metadata
 * - input_schema: Request schema for the action
 * - stability: From action manifest or connector risk_level default
 * - scope: {tenant, environment, role}
 *
 * @return array<int, array{name: string, description: string, input_schema: array, stability: string, scope: array}>
 */',
        'startLine' => 24,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Connectors\\Mcp',
        'declaringClassName' => 'App\\Support\\Connectors\\Mcp\\ConnectorMcpRegistrar',
        'implementingClassName' => 'App\\Support\\Connectors\\Mcp\\ConnectorMcpRegistrar',
        'currentClassName' => 'App\\Support\\Connectors\\Mcp\\ConnectorMcpRegistrar',
        'aliasName' => NULL,
      ),
      'buildDescription' => 
      array (
        'name' => 'buildDescription',
        'parameters' => 
        array (
          'displayName' => 
          array (
            'name' => 'displayName',
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
            'startLine' => 69,
            'endLine' => 69,
            'startColumn' => 39,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'actionName' => 
          array (
            'name' => 'actionName',
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
            'startLine' => 69,
            'endLine' => 69,
            'startColumn' => 60,
            'endColumn' => 77,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'action' => 
          array (
            'name' => 'action',
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
            'startLine' => 69,
            'endLine' => 69,
            'startColumn' => 80,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 69,
        'endLine' => 75,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Connectors\\Mcp',
        'declaringClassName' => 'App\\Support\\Connectors\\Mcp\\ConnectorMcpRegistrar',
        'implementingClassName' => 'App\\Support\\Connectors\\Mcp\\ConnectorMcpRegistrar',
        'currentClassName' => 'App\\Support\\Connectors\\Mcp\\ConnectorMcpRegistrar',
        'aliasName' => NULL,
      ),
      'buildInputSchema' => 
      array (
        'name' => 'buildInputSchema',
        'parameters' => 
        array (
          'action' => 
          array (
            'name' => 'action',
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
            'startLine' => 77,
            'endLine' => 77,
            'startColumn' => 39,
            'endColumn' => 51,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 77,
        'endLine' => 88,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Connectors\\Mcp',
        'declaringClassName' => 'App\\Support\\Connectors\\Mcp\\ConnectorMcpRegistrar',
        'implementingClassName' => 'App\\Support\\Connectors\\Mcp\\ConnectorMcpRegistrar',
        'currentClassName' => 'App\\Support\\Connectors\\Mcp\\ConnectorMcpRegistrar',
        'aliasName' => NULL,
      ),
      'defaultStability' => 
      array (
        'name' => 'defaultStability',
        'parameters' => 
        array (
          'riskLevel' => 
          array (
            'name' => 'riskLevel',
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
            'startLine' => 90,
            'endLine' => 90,
            'startColumn' => 39,
            'endColumn' => 55,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 90,
        'endLine' => 96,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Connectors\\Mcp',
        'declaringClassName' => 'App\\Support\\Connectors\\Mcp\\ConnectorMcpRegistrar',
        'implementingClassName' => 'App\\Support\\Connectors\\Mcp\\ConnectorMcpRegistrar',
        'currentClassName' => 'App\\Support\\Connectors\\Mcp\\ConnectorMcpRegistrar',
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