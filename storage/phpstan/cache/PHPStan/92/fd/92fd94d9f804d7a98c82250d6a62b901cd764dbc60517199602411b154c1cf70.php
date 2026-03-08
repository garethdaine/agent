<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/RepoAnalysis/Analyzers/CodeQualityStandardsAnalyzer.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\RepoAnalysis\Analyzers\CodeQualityStandardsAnalyzer
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-3054626e56cdc2033a4c041cc05c31b3dad4968f6680c1688f33b1359f8f7950',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/RepoAnalysis/Analyzers/CodeQualityStandardsAnalyzer.php',
      ),
    ),
    'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
    'name' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
    'shortName' => 'CodeQualityStandardsAnalyzer',
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
    'endLine' => 418,
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
      'TOOL_RULES' => 
      array (
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'name' => 'TOOL_RULES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[[\'tool\' => \'EditorConfig\', \'category\' => \'formatting\', \'pattern\' => \'/(?:^|\\/)\\.editorconfig$/i\'], [\'tool\' => \'Prettier\', \'category\' => \'formatting\', \'pattern\' => \'/(?:^|\\/)(?:prettier\\.config\\.(?:js|cjs|mjs|ts)|\\.prettierrc(?:\\.(?:js|json|yaml|yml))?)$/i\'], [\'tool\' => \'ESLint\', \'category\' => \'linting\', \'pattern\' => \'/(?:^|\\/)(?:eslint\\.config\\.(?:js|cjs|mjs|ts)|\\.eslintrc(?:\\.(?:js|json|yaml|yml))?)$/i\'], [\'tool\' => \'Stylelint\', \'category\' => \'linting\', \'pattern\' => \'/(?:^|\\/)(?:stylelint\\.config\\.(?:js|cjs|mjs|ts)|\\.stylelintrc(?:\\.(?:js|json|yaml|yml))?)$/i\'], [\'tool\' => \'PHP CS Fixer / PHPCS\', \'category\' => \'linting\', \'pattern\' => \'/(?:^|\\/)(?:\\.php-cs-fixer\\.php|phpcs\\.xml(?:\\.dist)?|\\.phpcs\\.xml)$/i\'], [\'tool\' => \'Laravel Pint\', \'category\' => \'formatting\', \'pattern\' => \'/(?:^|\\/)pint\\.json$/i\'], [\'tool\' => \'PHPStan\', \'category\' => \'static_analysis\', \'pattern\' => \'/(?:^|\\/)phpstan\\.neon(?:\\.dist)?$/i\'], [\'tool\' => \'Psalm\', \'category\' => \'static_analysis\', \'pattern\' => \'/(?:^|\\/)psalm\\.xml(?:\\.dist)?$/i\'], [\'tool\' => \'Mypy\', \'category\' => \'static_analysis\', \'pattern\' => \'/(?:^|\\/)\\.mypy\\.ini$|(?:^|\\/)mypy\\.ini$/i\'], [\'tool\' => \'Pyright\', \'category\' => \'static_analysis\', \'pattern\' => \'/(?:^|\\/)pyrightconfig\\.json$/i\'], [\'tool\' => \'Ruff\', \'category\' => \'linting\', \'pattern\' => \'/(?:^|\\/)(?:ruff\\.toml|\\.ruff\\.toml)$/i\'], [\'tool\' => \'Flake8\', \'category\' => \'linting\', \'pattern\' => \'/(?:^|\\/)\\.flake8$/i\'], [\'tool\' => \'GolangCI-Lint\', \'category\' => \'linting\', \'pattern\' => \'/(?:^|\\/)(?:\\.golangci\\.(?:yml|yaml)|golangci\\.(?:yml|yaml))$/i\'], [\'tool\' => \'Rustfmt\', \'category\' => \'formatting\', \'pattern\' => \'/(?:^|\\/)rustfmt\\.toml$/i\'], [\'tool\' => \'Clippy\', \'category\' => \'linting\', \'pattern\' => \'/(?:^|\\/)clippy\\.toml$/i\'], [\'tool\' => \'Biome\', \'category\' => \'formatting\', \'pattern\' => \'/(?:^|\\/)biome\\.jsonc?$/i\'], [\'tool\' => \'SwiftLint\', \'category\' => \'linting\', \'pattern\' => \'/(?:^|\\/)\\.swiftlint\\.(?:yml|yaml)$/i\'], [\'tool\' => \'RuboCop\', \'category\' => \'linting\', \'pattern\' => \'/(?:^|\\/)\\.rubocop\\.yml$/i\'], [\'tool\' => \'CI Workflow\', \'category\' => \'ci\', \'pattern\' => \'/(?:^|\\/)\\.github\\/workflows\\/[^\\/]+\\.(?:yml|yaml)$/i\'], [\'tool\' => \'GitLab CI\', \'category\' => \'ci\', \'pattern\' => \'/(?:^|\\/)\\.gitlab-ci\\.yml$/i\'], [\'tool\' => \'CircleCI\', \'category\' => \'ci\', \'pattern\' => \'/(?:^|\\/)\\.circleci\\/config\\.yml$/i\'], [\'tool\' => \'Azure Pipelines\', \'category\' => \'ci\', \'pattern\' => \'/(?:^|\\/)azure-pipelines\\.yml$/i\'], [\'tool\' => \'Jenkins\', \'category\' => \'ci\', \'pattern\' => \'/(?:^|\\/)Jenkinsfile$/i\']]',
          'attributes' => 
          array (
            'startLine' => 12,
            'endLine' => 36,
            'startTokenPos' => 35,
            'startFilePos' => 268,
            'endTokenPos' => 566,
            'endFilePos' => 2972,
          ),
        ),
        'docComment' => '/**
 * @var array<int, array{tool: string, category: string, pattern: string}>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 12,
        'endLine' => 36,
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
        'startLine' => 38,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
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
        'startLine' => 43,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
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
        'startLine' => 51,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
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
            'startLine' => 56,
            'endLine' => 56,
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
        'startLine' => 56,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
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
            'startLine' => 61,
            'endLine' => 61,
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
        'startLine' => 61,
        'endLine' => 115,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'aliasName' => NULL,
      ),
      'discoverStandardsFiles' => 
      array (
        'name' => 'discoverStandardsFiles',
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
            'startLine' => 122,
            'endLine' => 122,
            'startColumn' => 45,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 122,
            'endLine' => 122,
            'startColumn' => 62,
            'endColumn' => 73,
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
 * @param  array<string, mixed>  $snapshot
 * @param  array<int, string>  $paths
 * @return array<int, array{tool: string, category: string, file: string}>
 */',
        'startLine' => 122,
        'endLine' => 164,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'aliasName' => NULL,
      ),
      'detectPyprojectTools' => 
      array (
        'name' => 'detectPyprojectTools',
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
            'startLine' => 170,
            'endLine' => 170,
            'startColumn' => 43,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'path' => 
          array (
            'name' => 'path',
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
            'startLine' => 170,
            'endLine' => 170,
            'startColumn' => 60,
            'endColumn' => 71,
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
 * @param  array<string, mixed>  $snapshot
 * @return array<int, array{tool: string, category: string, file: string}>
 */',
        'startLine' => 170,
        'endLine' => 203,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'aliasName' => NULL,
      ),
      'discoverQualityCommands' => 
      array (
        'name' => 'discoverQualityCommands',
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
            'startLine' => 210,
            'endLine' => 210,
            'startColumn' => 46,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 210,
            'endLine' => 210,
            'startColumn' => 63,
            'endColumn' => 74,
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
 * @param  array<string, mixed>  $snapshot
 * @param  array<int, string>  $paths
 * @return array<int, string>
 */',
        'startLine' => 210,
        'endLine' => 300,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'aliasName' => NULL,
      ),
      'isQualityScriptName' => 
      array (
        'name' => 'isQualityScriptName',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
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
            'startLine' => 302,
            'endLine' => 302,
            'startColumn' => 42,
            'endColumn' => 53,
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
        'startLine' => 302,
        'endLine' => 305,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'aliasName' => NULL,
      ),
      'ciPipelineFiles' => 
      array (
        'name' => 'ciPipelineFiles',
        'parameters' => 
        array (
          'standardsFiles' => 
          array (
            'name' => 'standardsFiles',
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
            'startLine' => 311,
            'endLine' => 311,
            'startColumn' => 38,
            'endColumn' => 58,
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
 * @param  array<int, array{tool: string, category: string, file: string}>  $standardsFiles
 * @return array<int, string>
 */',
        'startLine' => 311,
        'endLine' => 332,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'aliasName' => NULL,
      ),
      'categoryCounts' => 
      array (
        'name' => 'categoryCounts',
        'parameters' => 
        array (
          'standardsFiles' => 
          array (
            'name' => 'standardsFiles',
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
            'startLine' => 338,
            'endLine' => 338,
            'startColumn' => 37,
            'endColumn' => 57,
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
 * @param  array<int, array{tool: string, category: string, file: string}>  $standardsFiles
 * @return array<string, int>
 */',
        'startLine' => 338,
        'endLine' => 354,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'aliasName' => NULL,
      ),
      'qualitySignals' => 
      array (
        'name' => 'qualitySignals',
        'parameters' => 
        array (
          'toolingCategories' => 
          array (
            'name' => 'toolingCategories',
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
            'startLine' => 361,
            'endLine' => 361,
            'startColumn' => 37,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'qualityCommands' => 
          array (
            'name' => 'qualityCommands',
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
            'startLine' => 361,
            'endLine' => 361,
            'startColumn' => 63,
            'endColumn' => 84,
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
 * @param  array<string, int>  $toolingCategories
 * @param  array<int, string>  $qualityCommands
 * @return array<int, string>
 */',
        'startLine' => 361,
        'endLine' => 390,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'aliasName' => NULL,
      ),
      'uniqueRecords' => 
      array (
        'name' => 'uniqueRecords',
        'parameters' => 
        array (
          'records' => 
          array (
            'name' => 'records',
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
            'startLine' => 396,
            'endLine' => 396,
            'startColumn' => 36,
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
 * @param  array<int, array{tool: string, category: string, file: string}>  $records
 * @return array<int, array{tool: string, category: string, file: string}>
 */',
        'startLine' => 396,
        'endLine' => 417,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\RepoAnalysis\\Analyzers',
        'declaringClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'implementingClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
        'currentClassName' => 'App\\Support\\RepoAnalysis\\Analyzers\\CodeQualityStandardsAnalyzer',
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