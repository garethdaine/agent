<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Agent/EngineeringRulesInjector.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Agent\EngineeringRulesInjector
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-f4dd6b6e7edfb0cd01df9ebb1610bf10ba6caa32dacbb309054f4bd852bbe8ca',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Agent/EngineeringRulesInjector.php',
      ),
    ),
    'namespace' => 'App\\Support\\Agent',
    'name' => 'App\\Support\\Agent\\EngineeringRulesInjector',
    'shortName' => 'EngineeringRulesInjector',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Reads, compiles, and injects AgentOps Engineering Rules into agent task context.
 *
 * Profiles select context-appropriate rule subsets to manage token budget:
 * - full: All sections (~6250 tokens) for standard agent runs
 * - core: Architecture + SOLID + Security + Planning + Testing + Laravel + AgentOps (~3000 tokens)
 * - interrogation: Planning + Testing + Databases + Laravel + AgentOps (~2000 tokens)
 * - build: All except Marketing/UX/Design/Research (~4500 tokens)
 *
 * Compiled profiles are cached in Redis to avoid re-parsing on every job dispatch.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 22,
    'endLine' => 311,
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
      'SECTION_HEADERS' => 
      array (
        'declaringClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'implementingClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'name' => 'SECTION_HEADERS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'architecture\' => \'## 🏛 Software Architecture\', \'design_patterns\' => \'## 🧩 Design Patterns\', \'security\' => \'## 🔐 Security\', \'ux_ui\' => \'## 🎨 UX / UI\', \'design\' => \'## 🎨 Design\', \'research\' => \'## 🔬 Research & Analysis\', \'solid\' => \'## 🎯 SOLID Principles\', \'dry\' => \'## 🔄 DRY\', \'clean_code\' => \'## ✨ Clean Code\', \'planning\' => \'## 📋 Planning\', \'rest_apis\' => \'## 🌐 REST APIs\', \'refactoring\' => \'## ✂️ Refactoring\', \'coding_standards\' => \'## ✏️ Coding Standards\', \'debugging\' => \'## 🐛 Debugging\', \'marketing\' => \'## 📢 Marketing\', \'server_infra\' => \'## 🖥 Server Infrastructure\', \'terminal_cli\' => \'## 💻 Terminal / CLI\', \'testing\' => \'## 🧪 Testing\', \'devops\' => \'## 🔄 DevOps\', \'tech_specific\' => \'## 🛠 Technology-Specific\', \'databases\' => \'## 🗄 Databases\', \'frontend\' => \'## ⚛️ Frontend\', \'laravel\' => \'## 🏗 Laravel\', \'ai_apis\' => \'## 🤖 AI APIs\', \'agentops\' => \'## ⚙️ AgentOps-Specific\']',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 55,
            'startTokenPos' => 48,
            'startFilePos' => 947,
            'endTokenPos' => 225,
            'endFilePos' => 2115,
          ),
        ),
        'docComment' => '/**
 * Section markers derived from H2 headings in the rules document.
 *
 * @var array<string, string>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'PROFILES' => 
      array (
        'declaringClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'implementingClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'name' => 'PROFILES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'full\' => null, \'core\' => [\'architecture\', \'design_patterns\', \'security\', \'solid\', \'dry\', \'clean_code\', \'planning\', \'testing\', \'laravel\', \'agentops\'], \'interrogation\' => [\'architecture\', \'planning\', \'testing\', \'databases\', \'laravel\', \'agentops\'], \'build\' => [\'architecture\', \'design_patterns\', \'security\', \'solid\', \'dry\', \'clean_code\', \'planning\', \'rest_apis\', \'refactoring\', \'coding_standards\', \'debugging\', \'server_infra\', \'terminal_cli\', \'testing\', \'devops\', \'tech_specific\', \'databases\', \'frontend\', \'laravel\', \'ai_apis\', \'agentops\']]',
          'attributes' => 
          array (
            'startLine' => 63,
            'endLine' => 108,
            'startTokenPos' => 238,
            'startFilePos' => 2339,
            'endTokenPos' => 385,
            'endFilePos' => 3393,
          ),
        ),
        'docComment' => '/**
 * Profile definitions mapping profile name to included section keys.
 * null = all sections (full profile).
 *
 * @var array<string, array<int, string>|null>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 63,
        'endLine' => 108,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'isEnabled' => 
      array (
        'name' => 'isEnabled',
        'parameters' => 
        array (
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
        'startLine' => 110,
        'endLine' => 115,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'implementingClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'currentClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'aliasName' => NULL,
      ),
      'getCompiledRules' => 
      array (
        'name' => 'getCompiledRules',
        'parameters' => 
        array (
          'profile' => 
          array (
            'name' => 'profile',
            'default' => 
            array (
              'code' => '\'full\'',
              'attributes' => 
              array (
                'startLine' => 122,
                'endLine' => 122,
                'startTokenPos' => 436,
                'startFilePos' => 3802,
                'endTokenPos' => 436,
                'endFilePos' => 3807,
              ),
            ),
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
            'startLine' => 122,
            'endLine' => 122,
            'startColumn' => 38,
            'endColumn' => 61,
            'parameterIndex' => 0,
            'isOptional' => true,
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
 * Get compiled rules for a given profile, with Redis caching.
 *
 * @return string|null The compiled rules markdown, or null if disabled/failed.
 */',
        'startLine' => 122,
        'endLine' => 143,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'implementingClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'currentClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'aliasName' => NULL,
      ),
      'inject' => 
      array (
        'name' => 'inject',
        'parameters' => 
        array (
          'existingContent' => 
          array (
            'name' => 'existingContent',
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
            'startLine' => 149,
            'endLine' => 149,
            'startColumn' => 28,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'profile' => 
          array (
            'name' => 'profile',
            'default' => 
            array (
              'code' => '\'full\'',
              'attributes' => 
              array (
                'startLine' => 149,
                'endLine' => 149,
                'startTokenPos' => 612,
                'startFilePos' => 4740,
                'endTokenPos' => 612,
                'endFilePos' => 4745,
              ),
            ),
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
            'startLine' => 149,
            'endLine' => 149,
            'startColumn' => 53,
            'endColumn' => 76,
            'parameterIndex' => 1,
            'isOptional' => true,
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
 * Inject engineering rules into task content by prepending the compiled rules block.
 * Used for file-based task markdown paths (ExecuteAgentRunJob pipeline).
 */',
        'startLine' => 149,
        'endLine' => 160,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'implementingClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'currentClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'aliasName' => NULL,
      ),
      'injectIntoSystemPrompt' => 
      array (
        'name' => 'injectIntoSystemPrompt',
        'parameters' => 
        array (
          'systemPrompt' => 
          array (
            'name' => 'systemPrompt',
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
            'startColumn' => 44,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'profile' => 
          array (
            'name' => 'profile',
            'default' => 
            array (
              'code' => '\'core\'',
              'attributes' => 
              array (
                'startLine' => 166,
                'endLine' => 166,
                'startTokenPos' => 706,
                'startFilePos' => 5308,
                'endTokenPos' => 706,
                'endFilePos' => 5313,
              ),
            ),
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
            'startColumn' => 66,
            'endColumn' => 89,
            'parameterIndex' => 1,
            'isOptional' => true,
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
 * Inject rules into a system prompt string.
 * Used for CLI --system-prompt paths (CliRuntimeExecutor, SystemPromptResolver).
 */',
        'startLine' => 166,
        'endLine' => 177,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'implementingClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'currentClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'aliasName' => NULL,
      ),
      'compile' => 
      array (
        'name' => 'compile',
        'parameters' => 
        array (
          'profile' => 
          array (
            'name' => 'profile',
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
            'startLine' => 182,
            'endLine' => 182,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile the rules document into a profile-specific subset.
 */',
        'startLine' => 182,
        'endLine' => 203,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'implementingClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'currentClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'aliasName' => NULL,
      ),
      'truncateToTokenBudget' => 
      array (
        'name' => 'truncateToTokenBudget',
        'parameters' => 
        array (
          'rules' => 
          array (
            'name' => 'rules',
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
            'startLine' => 208,
            'endLine' => 208,
            'startColumn' => 44,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'profile' => 
          array (
            'name' => 'profile',
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
            'startLine' => 208,
            'endLine' => 208,
            'startColumn' => 59,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Truncate compiled rules to the configured max_tokens budget for the profile.
 */',
        'startLine' => 208,
        'endLine' => 219,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'implementingClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'currentClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'aliasName' => NULL,
      ),
      'stripPreamble' => 
      array (
        'name' => 'stripPreamble',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 225,
            'endLine' => 225,
            'startColumn' => 36,
            'endColumn' => 50,
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
        'docComment' => '/**
 * Strip the document preamble (title, how-to-use, skill format sections).
 * Keeps everything from the first rule section onward.
 */',
        'startLine' => 225,
        'endLine' => 244,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'implementingClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'currentClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'aliasName' => NULL,
      ),
      'extractSections' => 
      array (
        'name' => 'extractSections',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 251,
            'endLine' => 251,
            'startColumn' => 38,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'sectionKeys' => 
          array (
            'name' => 'sectionKeys',
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
            'startLine' => 251,
            'endLine' => 251,
            'startColumn' => 55,
            'endColumn' => 72,
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
 * Extract only the named sections from the full document.
 *
 * @param  array<int, string>  $sectionKeys
 */',
        'startLine' => 251,
        'endLine' => 263,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'implementingClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'currentClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'aliasName' => NULL,
      ),
      'parseSections' => 
      array (
        'name' => 'parseSections',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 270,
            'endLine' => 270,
            'startColumn' => 36,
            'endColumn' => 50,
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
 * Parse the document into named sections keyed by SECTION_HEADERS identifiers.
 *
 * @return array<string, string>
 */',
        'startLine' => 270,
        'endLine' => 310,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Agent',
        'declaringClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'implementingClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
        'currentClassName' => 'App\\Support\\Agent\\EngineeringRulesInjector',
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