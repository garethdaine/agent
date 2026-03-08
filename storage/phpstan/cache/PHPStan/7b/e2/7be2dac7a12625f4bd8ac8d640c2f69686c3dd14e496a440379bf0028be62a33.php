<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/RepoAnalysis/Analyzers/ArchitecturePatternsAnalyzer.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\RepoAnalysis\Analyzers\ArchitecturePatternsAnalyzer
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-3bbd95aeb49099bb655d18b92ccb24397e4e1e0494d196b6b5805207c17f942b',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/RepoAnalysis/Analyzers/ArchitecturePatternsAnalyzer.php',
      ),
    ),
    'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
    'name' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
    'shortName' => 'ArchitecturePatternsAnalyzer',
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
    'endLine' => 451,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\AbstractAnalyzer',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'SOURCE_EXTENSIONS' => 
      array (
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'name' => 'SOURCE_EXTENSIONS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'php\', \'js\', \'jsx\', \'ts\', \'tsx\', \'vue\', \'svelte\', \'py\', \'go\', \'rs\', \'rb\', \'java\', \'kt\', \'kts\', \'cs\', \'swift\', \'dart\']',
          'attributes' => 
          array (
            'startLine' => 12,
            'endLine' => 15,
            'startTokenPos' => 35,
            'startFilePos' => 227,
            'endTokenPos' => 88,
            'endFilePos' => 367,
          ),
        ),
        'docComment' => '/**
 * @var array<int, string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 12,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'PATTERN_RULES' => 
      array (
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'name' => 'PATTERN_RULES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[[\'key\' => \'repository_pattern\', \'name\' => \'Repository Pattern\', \'path_patterns\' => [\'/(?:^|\\/)repositories?(?:\\/|$)/i\', \'/(?:^|[_.-])repository(?:[_.-]|$)/i\'], \'content_patterns\' => [\'/\\b(interface|class|struct|trait)\\s+\\w*Repository\\b/i\']], [\'key\' => \'service_layer\', \'name\' => \'Service Layer\', \'path_patterns\' => [\'/(?:^|\\/)services?(?:\\/|$)/i\', \'/(?:^|[_.-])service(?:[_.-]|$)/i\'], \'content_patterns\' => [\'/\\b(class|struct)\\s+\\w*Service\\b/i\']], [\'key\' => \'factory_builder\', \'name\' => \'Factory / Builder Pattern\', \'path_patterns\' => [\'/(?:^|[_.-])factory(?:[_.-]|$)/i\', \'/(?:^|[_.-])builder(?:[_.-]|$)/i\'], \'content_patterns\' => [\'/\\b(class|struct|interface|trait)\\s+\\w*(Factory|Builder)\\b/i\']], [\'key\' => \'strategy_policy\', \'name\' => \'Strategy / Policy Pattern\', \'path_patterns\' => [\'/(?:^|[_.-])strategy(?:[_.-]|$)/i\', \'/(?:^|[_.-])policy(?:[_.-]|$)/i\'], \'content_patterns\' => [\'/\\b(class|interface|struct|trait)\\s+\\w*(Strategy|Policy)\\b/i\']], [\'key\' => \'event_observer_pubsub\', \'name\' => \'Event / Observer / PubSub Pattern\', \'path_patterns\' => [\'/(?:^|\\/)events?(?:\\/|$)/i\', \'/(?:^|\\/)listeners?(?:\\/|$)/i\', \'/(?:^|\\/)subscribers?(?:\\/|$)/i\', \'/(?:^|\\/)pubsub(?:\\/|$)/i\'], \'content_patterns\' => [\'/\\b(event|listener|subscriber|publish|subscribe|emit)\\b/i\']], [\'key\' => \'dependency_injection\', \'name\' => \'Dependency Injection\', \'path_patterns\' => [\'/(?:^|\\/)providers?(?:\\/|$)/i\', \'/(?:^|\\/)container(?:\\/|$)/i\', \'/(?:^|\\/)inject(?:ion)?(?:\\/|$)/i\'], \'content_patterns\' => [\'/\\b__construct\\s*\\(/i\', \'/\\bconstructor\\s*\\(/i\', \'/\\b@Inject\\b/i\', \'/\\binject\\s*\\(/i\', \'/\\bservice\\s+container\\b/i\']], [\'key\' => \'middleware_pipeline\', \'name\' => \'Middleware / Pipeline Pattern\', \'path_patterns\' => [\'/(?:^|\\/)middleware(?:\\/|$)/i\', \'/(?:^|\\/)pipeline(?:\\/|$)/i\', \'/(?:^|\\/)interceptors?(?:\\/|$)/i\'], \'content_patterns\' => [\'/\\bmiddleware\\b/i\', \'/\\bpipeline\\b/i\', \'/\\binterceptor\\b/i\']]]',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 113,
            'startTokenPos' => 101,
            'startFilePos' => 596,
            'endTokenPos' => 455,
            'endFilePos' => 3659,
          ),
        ),
        'docComment' => '/**
 * @var array<int, array{
 *   key: string,
 *   name: string,
 *   path_patterns: array<int, string>,
 *   content_patterns: array<int, string>
 * }>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 113,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'key' => 
      array (
        'name' => 'key',
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
        'docComment' => NULL,
        'startLine' => 115,
        'endLine' => 118,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'aliasName' => NULL,
      ),
      'version' => 
      array (
        'name' => 'version',
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
        'docComment' => NULL,
        'startLine' => 120,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'aliasName' => NULL,
      ),
      'dependencies' => 
      array (
        'name' => 'dependencies',
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
 * @return array<int, string>
 */',
        'startLine' => 128,
        'endLine' => 131,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'aliasName' => NULL,
      ),
      'supports' => 
      array (
        'name' => 'supports',
        'parameters' => 
        array (
          'snapshot' => 
          array (
            'name' => 'snapshot',
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
            'startLine' => 133,
            'endLine' => 133,
            'startColumn' => 30,
            'endColumn' => 44,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 133,
        'endLine' => 136,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'aliasName' => NULL,
      ),
      'analyze' => 
      array (
        'name' => 'analyze',
        'parameters' => 
        array (
          'snapshot' => 
          array (
            'name' => 'snapshot',
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
            'startLine' => 138,
            'endLine' => 138,
            'startColumn' => 29,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 138,
        'endLine' => 203,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'aliasName' => NULL,
      ),
      'sourceFiles' => 
      array (
        'name' => 'sourceFiles',
        'parameters' => 
        array (
          'paths' => 
          array (
            'name' => 'paths',
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
            'startLine' => 209,
            'endLine' => 209,
            'startColumn' => 34,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array<int, string>  $paths
 * @return array<int, string>
 */',
        'startLine' => 209,
        'endLine' => 225,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'aliasName' => NULL,
      ),
      'detectPattern' => 
      array (
        'name' => 'detectPattern',
        'parameters' => 
        array (
          'snapshot' => 
          array (
            'name' => 'snapshot',
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
            'startLine' => 238,
            'endLine' => 238,
            'startColumn' => 36,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'sourceFiles' => 
          array (
            'name' => 'sourceFiles',
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
            'startLine' => 238,
            'endLine' => 238,
            'startColumn' => 53,
            'endColumn' => 70,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'rule' => 
          array (
            'name' => 'rule',
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
            'startLine' => 238,
            'endLine' => 238,
            'startColumn' => 73,
            'endColumn' => 83,
            'parameterIndex' => 2,
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
 * @param  array<string, mixed>  $snapshot
 * @param  array<int, string>  $sourceFiles
 * @param  array{
 *   key: string,
 *   name: string,
 *   path_patterns: array<int, string>,
 *   content_patterns: array<int, string>
 * }  $rule
 * @return array<string, mixed>|null
 */',
        'startLine' => 238,
        'endLine' => 292,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'aliasName' => NULL,
      ),
      'compositeArchitectureSignals' => 
      array (
        'name' => 'compositeArchitectureSignals',
        'parameters' => 
        array (
          'paths' => 
          array (
            'name' => 'paths',
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
            'startLine' => 299,
            'endLine' => 299,
            'startColumn' => 51,
            'endColumn' => 62,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'sourceFiles' => 
          array (
            'name' => 'sourceFiles',
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
            'startLine' => 299,
            'endLine' => 299,
            'startColumn' => 65,
            'endColumn' => 82,
            'parameterIndex' => 1,
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
 * @param  array<int, string>  $paths
 * @param  array<int, string>  $sourceFiles
 * @return array{patterns: array<int, array<string, mixed>>, signals: array<int, string>}
 */',
        'startLine' => 299,
        'endLine' => 363,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'aliasName' => NULL,
      ),
      'containsPathToken' => 
      array (
        'name' => 'containsPathToken',
        'parameters' => 
        array (
          'paths' => 
          array (
            'name' => 'paths',
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
            'startLine' => 369,
            'endLine' => 369,
            'startColumn' => 40,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'tokens' => 
          array (
            'name' => 'tokens',
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
            'startLine' => 369,
            'endLine' => 369,
            'startColumn' => 54,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array<int, string>  $paths
 * @param  array<int, string>  $tokens
 */',
        'startLine' => 369,
        'endLine' => 380,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'aliasName' => NULL,
      ),
      'extensionDistribution' => 
      array (
        'name' => 'extensionDistribution',
        'parameters' => 
        array (
          'sourceFiles' => 
          array (
            'name' => 'sourceFiles',
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
            'startLine' => 386,
            'endLine' => 386,
            'startColumn' => 44,
            'endColumn' => 61,
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
 * @param  array<int, string>  $sourceFiles
 * @return array<string, int>
 */',
        'startLine' => 386,
        'endLine' => 402,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'aliasName' => NULL,
      ),
      'moduleRoots' => 
      array (
        'name' => 'moduleRoots',
        'parameters' => 
        array (
          'paths' => 
          array (
            'name' => 'paths',
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
            'startLine' => 408,
            'endLine' => 408,
            'startColumn' => 34,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array<int, string>  $paths
 * @return array<int, array{name: string, file_count: int}>
 */',
        'startLine' => 408,
        'endLine' => 431,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'aliasName' => NULL,
      ),
      'confidenceLevel' => 
      array (
        'name' => 'confidenceLevel',
        'parameters' => 
        array (
          'evidenceCount' => 
          array (
            'name' => 'evidenceCount',
            'default' => NULL,
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
            'startLine' => 436,
            'endLine' => 436,
            'startColumn' => 38,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'signalTypes' => 
          array (
            'name' => 'signalTypes',
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
            'startLine' => 436,
            'endLine' => 436,
            'startColumn' => 58,
            'endColumn' => 75,
            'parameterIndex' => 1,
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
        'docComment' => '/**
 * @param  array<int, string>  $signalTypes
 */',
        'startLine' => 436,
        'endLine' => 450,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\ArchitecturePatternsAnalyzer',
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