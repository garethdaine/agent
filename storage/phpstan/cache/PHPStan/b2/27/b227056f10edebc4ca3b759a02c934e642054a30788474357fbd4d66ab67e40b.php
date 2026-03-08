<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Services/Messenger/CommandRouter.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Messenger\CommandRouter
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-aeda3c693ad75b874d96fbdeeacf014b4635d73ed4a67c00e72e04a4c7ce86f9',
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
    'startLine' => 48,
    'endLine' => 140,
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
          'code' => '[\'jobs\' => \\App\\Messenger\\SlashCommands\\JobsCommandHandler::class, \'runs\' => \\App\\Messenger\\SlashCommands\\RunsCommandHandler::class, \'status\' => \\App\\Messenger\\SlashCommands\\StatusCommandHandler::class, \'sessions\' => \\App\\Messenger\\SlashCommands\\SessionsCommandHandler::class, \'mode\' => \\App\\Messenger\\SlashCommands\\ModeCommandHandler::class, \'approve\' => \\App\\Messenger\\SlashCommands\\ApproveCommandHandler::class, \'deny\' => \\App\\Messenger\\SlashCommands\\DenyCommandHandler::class, \'browser\' => \\App\\Messenger\\SlashCommands\\BrowserCommandHandler::class, \'ask\' => \\App\\Messenger\\SlashCommands\\AskCommandHandler::class, \'context\' => \\App\\Messenger\\SlashCommands\\ContextCommandHandler::class, \'new\' => \\App\\Messenger\\SlashCommands\\NewCommandHandler::class, \'help\' => \\App\\Messenger\\SlashCommands\\HelpCommandHandler::class, \'commands\' => \\App\\Messenger\\SlashCommands\\CommandsCommandHandler::class, \'whoami\' => \\App\\Messenger\\SlashCommands\\WhoamiCommandHandler::class, \'compact\' => \\App\\Messenger\\SlashCommands\\CompactCommandHandler::class, \'subagents\' => \\App\\Messenger\\SlashCommands\\SubAgentsCommandHandler::class, \'progress\' => \\App\\Messenger\\SlashCommands\\ProgressCommandHandler::class, \'skills\' => \\App\\Messenger\\SlashCommands\\SkillsCommandHandler::class]',
          'attributes' => 
          array (
            'startLine' => 53,
            'endLine' => 72,
            'startTokenPos' => 140,
            'startFilePos' => 2148,
            'endTokenPos' => 304,
            'endFilePos' => 3030,
          ),
        ),
        'docComment' => '/**
 * @var array<string, class-string<SlashCommandHandlerInterface>>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 53,
        'endLine' => 72,
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
            'startLine' => 77,
            'endLine' => 77,
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
        'startLine' => 77,
        'endLine' => 82,
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
            'startLine' => 89,
            'endLine' => 89,
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
        'startLine' => 89,
        'endLine' => 106,
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
            'startLine' => 113,
            'endLine' => 113,
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
            'startLine' => 113,
            'endLine' => 113,
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
                'startLine' => 113,
                'endLine' => 113,
                'startTokenPos' => 520,
                'startFilePos' => 4122,
                'endTokenPos' => 520,
                'endFilePos' => 4125,
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
            'startLine' => 113,
            'endLine' => 113,
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
                'startLine' => 113,
                'endLine' => 113,
                'startTokenPos' => 530,
                'startFilePos' => 4158,
                'endTokenPos' => 530,
                'endFilePos' => 4161,
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
            'startLine' => 113,
            'endLine' => 113,
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
        'startLine' => 113,
        'endLine' => 129,
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
        'startLine' => 136,
        'endLine' => 139,
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