<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Documentation/DocsCatalog.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Documentation\DocsCatalog
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-4bfe3fa324159c4bcbc65f45793792d3ab677591f7ca9c7e0da4dab7fc8b1441',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Documentation\\DocsCatalog',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Documentation/DocsCatalog.php',
      ),
    ),
    'namespace' => 'App\\Support\\Documentation',
    'name' => 'App\\Support\\Documentation\\DocsCatalog',
    'shortName' => 'DocsCatalog',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 7,
    'endLine' => 186,
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
      'entries' => 
      array (
        'declaringClassName' => 'App\\Support\\Documentation\\DocsCatalog',
        'implementingClassName' => 'App\\Support\\Documentation\\DocsCatalog',
        'name' => 'entries',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[\'overview\' => [\'slug\' => \'overview\', \'title\' => \'Documentation Overview\', \'summary\' => \'Internal-only documentation entry point.\', \'section\' => \'general\', \'domain\' => \'product_doc\', \'updated_at\' => \'2026-03-02T00:00:00Z\'], \'api-contracts\' => [\'slug\' => \'api-contracts\', \'title\' => \'API Contracts\', \'summary\' => \'Read-only API contract references and usage notes.\', \'section\' => \'api\', \'domain\' => \'api_doc\', \'updated_at\' => \'2026-03-02T00:00:00Z\']]',
          'attributes' => 
          array (
            'startLine' => 12,
            'endLine' => 29,
            'startTokenPos' => 31,
            'startFilePos' => 184,
            'endTokenPos' => 135,
            'endFilePos' => 821,
          ),
        ),
        'docComment' => '/**
 * @var array<string, array<string, mixed>>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 12,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 6,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fragments' => 
      array (
        'declaringClassName' => 'App\\Support\\Documentation\\DocsCatalog',
        'implementingClassName' => 'App\\Support\\Documentation\\DocsCatalog',
        'name' => 'fragments',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[\'docs.overview\' => [\'ui_key\' => \'docs.overview\', \'short_text\' => \'Docs are internal-only and require authentication.\', \'long_text\' => \'This helper text is served from the docs fragments read API.\', \'severity\' => \'info\', \'learn_more_slug\' => \'overview\'], \'sessions.detail\' => [\'ui_key\' => \'sessions.detail\', \'short_text\' => \'Inspect session turns, tool calls, and approvals.\', \'severity\' => \'info\', \'learn_more_slug\' => \'overview\'], \'connectors.overview\' => [\'ui_key\' => \'connectors.overview\', \'short_text\' => \'Browse and manage connected third-party services.\', \'severity\' => \'info\'], \'deployments.overview\' => [\'ui_key\' => \'deployments.overview\', \'short_text\' => \'Deployment history and release status tracking.\', \'severity\' => \'info\'], \'sessions.overview\' => [\'ui_key\' => \'sessions.overview\', \'short_text\' => \'Active runtime sessions and connection status.\', \'severity\' => \'info\'], \'code-analysis.overview\' => [\'ui_key\' => \'code-analysis.overview\', \'short_text\' => \'Code analysis runs, findings, and quality metrics.\', \'severity\' => \'info\'], \'security.audit\' => [\'ui_key\' => \'security.audit\', \'short_text\' => \'Security audit findings and compliance status.\', \'severity\' => \'info\'], \'diagnostics\' => [\'ui_key\' => \'diagnostics\', \'short_text\' => \'System diagnostics and health check results.\', \'severity\' => \'info\'], \'services\' => [\'ui_key\' => \'services\', \'short_text\' => \'Registered services and their operational status.\', \'severity\' => \'info\'], \'logs\' => [\'ui_key\' => \'logs\', \'short_text\' => \'Application log stream and filtering controls.\', \'severity\' => \'info\'], \'audit.log\' => [\'ui_key\' => \'audit.log\', \'short_text\' => \'Audit trail of user and system actions.\', \'severity\' => \'info\'], \'memory.settings\' => [\'ui_key\' => \'memory.settings\', \'short_text\' => \'Memory system configuration and storage settings.\', \'severity\' => \'info\'], \'settings.tunnel\' => [\'ui_key\' => \'settings.tunnel\', \'short_text\' => \'Tunnel configuration for secure remote access.\', \'severity\' => \'info\'], \'settings.configuration\' => [\'ui_key\' => \'settings.configuration\', \'short_text\' => \'Application configuration and environment settings.\', \'severity\' => \'info\'], \'credentials\' => [\'ui_key\' => \'credentials\', \'short_text\' => \'Secret and credential management for integrations.\', \'severity\' => \'info\']]',
          'attributes' => 
          array (
            'startLine' => 34,
            'endLine' => 113,
            'startTokenPos' => 148,
            'startFilePos' => 920,
            'endTokenPos' => 621,
            'endFilePos' => 4063,
          ),
        ),
        'docComment' => '/**
 * @var array<string, array<string, mixed>>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 113,
        'startColumn' => 5,
        'endColumn' => 6,
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
      'search' => 
      array (
        'name' => 'search',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 118,
            'endLine' => 118,
            'startColumn' => 28,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'domain' => 
          array (
            'name' => 'domain',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 118,
                'endLine' => 118,
                'startTokenPos' => 645,
                'startFilePos' => 4192,
                'endTokenPos' => 645,
                'endFilePos' => 4195,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 118,
            'endLine' => 118,
            'startColumn' => 44,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'section' => 
          array (
            'name' => 'section',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 118,
                'endLine' => 118,
                'startTokenPos' => 655,
                'startFilePos' => 4217,
                'endTokenPos' => 655,
                'endFilePos' => 4220,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 118,
            'endLine' => 118,
            'startColumn' => 68,
            'endColumn' => 90,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'limit' => 
          array (
            'name' => 'limit',
            'default' => 
            array (
              'code' => '20',
              'attributes' => 
              array (
                'startLine' => 118,
                'endLine' => 118,
                'startTokenPos' => 664,
                'startFilePos' => 4236,
                'endTokenPos' => 664,
                'endFilePos' => 4237,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 118,
            'endLine' => 118,
            'startColumn' => 93,
            'endColumn' => 107,
            'parameterIndex' => 3,
            'isOptional' => true,
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
 * @return array<int, array<string, mixed>>
 */',
        'startLine' => 118,
        'endLine' => 153,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Documentation',
        'declaringClassName' => 'App\\Support\\Documentation\\DocsCatalog',
        'implementingClassName' => 'App\\Support\\Documentation\\DocsCatalog',
        'currentClassName' => 'App\\Support\\Documentation\\DocsCatalog',
        'aliasName' => NULL,
      ),
      'findEntry' => 
      array (
        'name' => 'findEntry',
        'parameters' => 
        array (
          'slug' => 
          array (
            'name' => 'slug',
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
            'startLine' => 158,
            'endLine' => 158,
            'startColumn' => 31,
            'endColumn' => 42,
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
                  'name' => 'array',
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
 * @return array<string, mixed>|null
 */',
        'startLine' => 158,
        'endLine' => 161,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Documentation',
        'declaringClassName' => 'App\\Support\\Documentation\\DocsCatalog',
        'implementingClassName' => 'App\\Support\\Documentation\\DocsCatalog',
        'currentClassName' => 'App\\Support\\Documentation\\DocsCatalog',
        'aliasName' => NULL,
      ),
      'findFragment' => 
      array (
        'name' => 'findFragment',
        'parameters' => 
        array (
          'uiKey' => 
          array (
            'name' => 'uiKey',
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
            'startLine' => 166,
            'endLine' => 166,
            'startColumn' => 34,
            'endColumn' => 46,
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
                  'name' => 'array',
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
 * @return array<string, mixed>|null
 */',
        'startLine' => 166,
        'endLine' => 169,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Documentation',
        'declaringClassName' => 'App\\Support\\Documentation\\DocsCatalog',
        'implementingClassName' => 'App\\Support\\Documentation\\DocsCatalog',
        'currentClassName' => 'App\\Support\\Documentation\\DocsCatalog',
        'aliasName' => NULL,
      ),
      'coverage' => 
      array (
        'name' => 'coverage',
        'parameters' => 
        array (
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
 * @return array<string, mixed>
 */',
        'startLine' => 174,
        'endLine' => 185,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Documentation',
        'declaringClassName' => 'App\\Support\\Documentation\\DocsCatalog',
        'implementingClassName' => 'App\\Support\\Documentation\\DocsCatalog',
        'currentClassName' => 'App\\Support\\Documentation\\DocsCatalog',
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