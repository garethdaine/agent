<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Services/Messenger/CommandRouter.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Messenger\CommandRouter
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-8b1970266a204636c9f3dd09d2db8b8846b9ba8bf70a5cc4caf8998fedfd3af4',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Messenger\\CommandRouter',
        'filename' => '/Users/garethdaine/Code/agent/app/Services/Messenger/CommandRouter.php',
      ),
    ),
    'namespace' => 'App\\Services\\Messenger',
    'name' => 'App\\Services\\Messenger\\CommandRouter',
    'shortName' => 'CommandRouter',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Router for slash commands.
 *
 * Handles all slash commands with deterministic behavior.
 * Commands are routed to their respective handlers.
 * Takes precedence over AgentRouter for free-form prompts.
 *
 * Supported commands:
 * - /jobs - Manage agent jobs (list, show, create, delete, run, enable, disable)
 * - /runs - Manage agent job runs (active, history, show, stop, retry, steer)
 * - /status - Return current runtime/session/tool state
 * - /sessions - Manage runtime sessions (list, stop)
 * - /mode [safe|standard|full] - View or change execution mode
 * - /approve <id> - Approve pending tool call
 * - /deny <id> [reason] - Deny pending tool call
 * - /browser - Browser sidecar (start, stop, reset, status)
 * - /ask <question> - General question or task
 * - /context [list|detail|json] - Context usage (messages, tokens)
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 49,
    'endLine' => 142,
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
      'handlers' => 
      array (
        'declaringClassName' => 'App\\Services\\Messenger\\CommandRouter',
        'implementingClassName' => 'App\\Services\\Messenger\\CommandRouter',
        'name' => 'handlers',
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
          'code' => '[\'jobs\' => \\App\\Messenger\\SlashCommands\\JobsCommandHandler::class, \'runs\' => \\App\\Messenger\\SlashCommands\\RunsCommandHandler::class, \'status\' => \\App\\Messenger\\SlashCommands\\StatusCommandHandler::class, \'sessions\' => \\App\\Messenger\\SlashCommands\\SessionsCommandHandler::class, \'mode\' => \\App\\Messenger\\SlashCommands\\ModeCommandHandler::class, \'approve\' => \\App\\Messenger\\SlashCommands\\ApproveCommandHandler::class, \'deny\' => \\App\\Messenger\\SlashCommands\\DenyCommandHandler::class, \'browser\' => \\App\\Messenger\\SlashCommands\\BrowserCommandHandler::class, \'ask\' => \\App\\Messenger\\SlashCommands\\AskCommandHandler::class, \'context\' => \\App\\Messenger\\SlashCommands\\ContextCommandHandler::class, \'new\' => \\App\\Messenger\\SlashCommands\\NewCommandHandler::class, \'help\' => \\App\\Messenger\\SlashCommands\\HelpCommandHandler::class, \'commands\' => \\App\\Messenger\\SlashCommands\\CommandsCommandHandler::class, \'whoami\' => \\App\\Messenger\\SlashCommands\\WhoamiCommandHandler::class, \'compact\' => \\App\\Messenger\\SlashCommands\\CompactCommandHandler::class, \'subagents\' => \\App\\Messenger\\SlashCommands\\SubAgentsCommandHandler::class, \'progress\' => \\App\\Messenger\\SlashCommands\\ProgressCommandHandler::class, \'skills\' => \\App\\Messenger\\SlashCommands\\SkillsCommandHandler::class, \'connector\' => \\App\\Messenger\\SlashCommands\\ConnectorCommandHandler::class]',
          'attributes' => 
          array (
            'startLine' => 54,
            'endLine' => 74,
            'startTokenPos' => 145,
            'startFilePos' => 2205,
            'endTokenPos' => 318,
            'endFilePos' => 3142,
          ),
        ),
        'docComment' => '/**
 * @var array<string, class-string<SlashCommandHandlerInterface>>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 54,
        'endLine' => 74,
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
      'isCommand' => 
      array (
        'name' => 'isCommand',
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
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 31,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Check if the content is a slash command.
 */',
        'startLine' => 79,
        'endLine' => 84,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Messenger',
        'declaringClassName' => 'App\\Services\\Messenger\\CommandRouter',
        'implementingClassName' => 'App\\Services\\Messenger\\CommandRouter',
        'currentClassName' => 'App\\Services\\Messenger\\CommandRouter',
        'aliasName' => NULL,
      ),
      'parseCommand' => 
      array (
        'name' => 'parseCommand',
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
            'startLine' => 91,
            'endLine' => 91,
            'startColumn' => 34,
            'endColumn' => 48,
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
 * Parse a command string into name and arguments.
 *
 * @return array{0: string, 1: array<int, string>}
 */',
        'startLine' => 91,
        'endLine' => 108,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Messenger',
        'declaringClassName' => 'App\\Services\\Messenger\\CommandRouter',
        'implementingClassName' => 'App\\Services\\Messenger\\CommandRouter',
        'currentClassName' => 'App\\Services\\Messenger\\CommandRouter',
        'aliasName' => NULL,
      ),
      'route' => 
      array (
        'name' => 'route',
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
            'startLine' => 115,
            'endLine' => 115,
            'startColumn' => 27,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\User',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 115,
            'endLine' => 115,
            'startColumn' => 44,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'chatSessionId' => 
          array (
            'name' => 'chatSessionId',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 115,
                'endLine' => 115,
                'startTokenPos' => 534,
                'startFilePos' => 4234,
                'endTokenPos' => 534,
                'endFilePos' => 4237,
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
            'startLine' => 115,
            'endLine' => 115,
            'startColumn' => 56,
            'endColumn' => 84,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'connectorAccountId' => 
          array (
            'name' => 'connectorAccountId',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 115,
                'endLine' => 115,
                'startTokenPos' => 544,
                'startFilePos' => 4270,
                'endTokenPos' => 544,
                'endFilePos' => 4273,
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
            'startLine' => 115,
            'endLine' => 115,
            'startColumn' => 87,
            'endColumn' => 120,
            'parameterIndex' => 3,
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
                  'name' => 'App\\DTOs\\Messenger\\CommandResult',
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
        'docComment' => '/**
 * Route and execute a command.
 *
 * Returns null if the content is not a command.
 */',
        'startLine' => 115,
        'endLine' => 131,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Messenger',
        'declaringClassName' => 'App\\Services\\Messenger\\CommandRouter',
        'implementingClassName' => 'App\\Services\\Messenger\\CommandRouter',
        'currentClassName' => 'App\\Services\\Messenger\\CommandRouter',
        'aliasName' => NULL,
      ),
      'getAvailableCommands' => 
      array (
        'name' => 'getAvailableCommands',
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
 * Get available command names.
 *
 * @return array<string>
 */',
        'startLine' => 138,
        'endLine' => 141,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Messenger',
        'declaringClassName' => 'App\\Services\\Messenger\\CommandRouter',
        'implementingClassName' => 'App\\Services\\Messenger\\CommandRouter',
        'currentClassName' => 'App\\Services\\Messenger\\CommandRouter',
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