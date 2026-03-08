<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Services/Messenger/SlashCommandRegistrar.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Messenger\SlashCommandRegistrar
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-86748517217a7374078269bced905b80b3df65e347b7ef7132cc800cc40996ad',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'filename' => '/Users/garethdaine/Code/agent/app/Services/Messenger/SlashCommandRegistrar.php',
      ),
    ),
    'namespace' => 'App\\Services\\Messenger',
    'name' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
    'shortName' => 'SlashCommandRegistrar',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Registers Discord slash commands for the agent bot.
 *
 * Single source of truth for all 10 top-level commands. Supports both
 * credentials array (API test) and ConnectorAccount (agent:install).
 * Version tracking triggers re-registration when schema changes.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 19,
    'endLine' => 306,
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
      'COMMAND_VERSION' => 
      array (
        'declaringClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'implementingClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'name' => 'COMMAND_VERSION',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'2.3.0\'',
          'attributes' => 
          array (
            'startLine' => 21,
            'endLine' => 21,
            'startTokenPos' => 53,
            'startFilePos' => 535,
            'endTokenPos' => 53,
            'endFilePos' => 541,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 44,
      ),
      'DISCORD_API_BASE' => 
      array (
        'declaringClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'implementingClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'name' => 'DISCORD_API_BASE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'https://discord.com/api/v10\'',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 64,
            'startFilePos' => 582,
            'endTokenPos' => 64,
            'endFilePos' => 610,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 67,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'getCommands' => 
      array (
        'name' => 'getCommands',
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
 * Full Discord slash command schema (10 commands).
 *
 * @return array<int, array<string, mixed>>
 */',
        'startLine' => 30,
        'endLine' => 185,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Messenger',
        'declaringClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'implementingClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'currentClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'aliasName' => NULL,
      ),
      'register' => 
      array (
        'name' => 'register',
        'parameters' => 
        array (
          'accountOrCredentials' => 
          array (
            'name' => 'accountOrCredentials',
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
                      'name' => 'App\\Models\\ConnectorAccount',
                      'isIdentifier' => false,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'array',
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
            'startLine' => 192,
            'endLine' => 192,
            'startColumn' => 30,
            'endColumn' => 73,
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
            'name' => 'App\\Services\\Messenger\\RegistrationResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register slash commands with Discord.
 *
 * @param  ConnectorAccount|array{bot_token: string, application_id: string}  $accountOrCredentials
 */',
        'startLine' => 192,
        'endLine' => 251,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Messenger',
        'declaringClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'implementingClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'currentClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'aliasName' => NULL,
      ),
      'needsUpdate' => 
      array (
        'name' => 'needsUpdate',
        'parameters' => 
        array (
          'account' => 
          array (
            'name' => 'account',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\ConnectorAccount',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 253,
            'endLine' => 253,
            'startColumn' => 33,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 253,
        'endLine' => 258,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Messenger',
        'declaringClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'implementingClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'currentClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'aliasName' => NULL,
      ),
      'getVersion' => 
      array (
        'name' => 'getVersion',
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
        'startLine' => 260,
        'endLine' => 263,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Messenger',
        'declaringClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'implementingClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'currentClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'aliasName' => NULL,
      ),
      'updateConnectorMetadata' => 
      array (
        'name' => 'updateConnectorMetadata',
        'parameters' => 
        array (
          'account' => 
          array (
            'name' => 'account',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\ConnectorAccount',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 268,
            'endLine' => 268,
            'startColumn' => 46,
            'endColumn' => 70,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'commands' => 
          array (
            'name' => 'commands',
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
            'startLine' => 268,
            'endLine' => 268,
            'startColumn' => 73,
            'endColumn' => 87,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array<int, array<string, mixed>>  $commands
 */',
        'startLine' => 268,
        'endLine' => 283,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Messenger',
        'declaringClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'implementingClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'currentClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'aliasName' => NULL,
      ),
      'parseErrorResponse' => 
      array (
        'name' => 'parseErrorResponse',
        'parameters' => 
        array (
          'response' => 
          array (
            'name' => 'response',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 288,
            'endLine' => 288,
            'startColumn' => 41,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  \\Illuminate\\Http\\Client\\Response  $response
 */',
        'startLine' => 288,
        'endLine' => 305,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Services\\Messenger',
        'declaringClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'implementingClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
        'currentClassName' => 'App\\Services\\Messenger\\SlashCommandRegistrar',
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