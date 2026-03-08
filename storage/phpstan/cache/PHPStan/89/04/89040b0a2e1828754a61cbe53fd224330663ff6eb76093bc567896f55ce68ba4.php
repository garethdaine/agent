<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Messenger/SlashCommands/CommandsCommandHandler.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Messenger\SlashCommands\CommandsCommandHandler
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-c0e9dfe5ee8d511046ffa1d0143136d033580d9751b87f44a03e4ed7d600c52b',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Messenger\\SlashCommands\\CommandsCommandHandler',
        'filename' => '/Users/garethdaine/Code/agent/app/Messenger/SlashCommands/CommandsCommandHandler.php',
      ),
    ),
    'namespace' => 'App\\Messenger\\SlashCommands',
    'name' => 'App\\Messenger\\SlashCommands\\CommandsCommandHandler',
    'shortName' => 'CommandsCommandHandler',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 11,
    'endLine' => 43,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'App\\Contracts\\Messenger\\SlashCommandHandlerInterface',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'DESCRIPTIONS' => 
      array (
        'declaringClassName' => 'App\\Messenger\\SlashCommands\\CommandsCommandHandler',
        'implementingClassName' => 'App\\Messenger\\SlashCommands\\CommandsCommandHandler',
        'name' => 'DESCRIPTIONS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'jobs\' => \'list, show, create, delete, run, enable, disable\', \'runs\' => \'active, history, show, stop, retry, steer\', \'status\' => \'overall system status\', \'sessions\' => \'list, stop (runtime sessions)\', \'mode\' => \'safe | standard | full\', \'approve\' => \'approve pending tool call\', \'deny\' => \'deny pending tool call\', \'browser\' => \'start, stop, reset, status\', \'ask\' => \'ask a question or run a task\', \'context\' => \'list | detail | json (context usage)\', \'new\' => \'start new runtime session\', \'help\' => \'list commands\', \'commands\' => \'detailed command list\', \'whoami\' => \'your user and connector id\', \'compact\' => \'run compaction (optional instructions)\']',
          'attributes' => 
          array (
            'startLine' => 13,
            'endLine' => 29,
            'startTokenPos' => 50,
            'startFilePos' => 301,
            'endTokenPos' => 157,
            'endFilePos' => 1080,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 13,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
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
            'startLine' => 34,
            'endLine' => 34,
            'startColumn' => 28,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'args' => 
          array (
            'name' => 'args',
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
            'startLine' => 34,
            'endLine' => 34,
            'startColumn' => 40,
            'endColumn' => 50,
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
                'startLine' => 34,
                'endLine' => 34,
                'startTokenPos' => 185,
                'startFilePos' => 1218,
                'endTokenPos' => 185,
                'endFilePos' => 1221,
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
            'startLine' => 34,
            'endLine' => 34,
            'startColumn' => 53,
            'endColumn' => 81,
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
                'startLine' => 34,
                'endLine' => 34,
                'startTokenPos' => 195,
                'startFilePos' => 1254,
                'endTokenPos' => 195,
                'endFilePos' => 1257,
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
            'startLine' => 34,
            'endLine' => 34,
            'startColumn' => 84,
            'endColumn' => 117,
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
            'name' => 'App\\DTOs\\Messenger\\CommandResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array<int, string>  $args
 */',
        'startLine' => 34,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\SlashCommands',
        'declaringClassName' => 'App\\Messenger\\SlashCommands\\CommandsCommandHandler',
        'implementingClassName' => 'App\\Messenger\\SlashCommands\\CommandsCommandHandler',
        'currentClassName' => 'App\\Messenger\\SlashCommands\\CommandsCommandHandler',
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