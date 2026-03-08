<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Messenger/Validation/DiscordCredentialValidator.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Messenger\Validation\DiscordCredentialValidator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-f75f0890b26165e1578627eb61d6d3b82df6cc9fd4374aa3fef6e93ffe74b6cd',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Messenger\\Validation\\DiscordCredentialValidator',
        'filename' => '/Users/garethdaine/Code/agent/app/Messenger/Validation/DiscordCredentialValidator.php',
      ),
    ),
    'namespace' => 'App\\Messenger\\Validation',
    'name' => 'App\\Messenger\\Validation\\DiscordCredentialValidator',
    'shortName' => 'DiscordCredentialValidator',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Validates Discord credentials based on connection mode.
 *
 * Token Formats:
 * - Bot tokens: Base64-encoded snowflake prefix, followed by timestamp and HMAC
 *   Format: {base64_user_id}.{timestamp_base64}.{hmac}
 *
 * - Application IDs: Snowflake format (numeric string, up to 20 digits)
 *
 * - Public keys: 64-character hex string (Ed25519 public key)
 *
 * Mode Requirements:
 * - Local (Gateway): bot_token, application_id
 * - Webhook: bot_token, application_id, public_key
 *
 * Validation is performed in two phases:
 * 1. Format validation (required fields, basic format checks)
 * 2. API validation (/users/@me call with bot token)
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 30,
    'endLine' => 174,
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
      'DISCORD_API_BASE' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Validation\\DiscordCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\DiscordCredentialValidator',
        'name' => 'DISCORD_API_BASE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'https://discord.com/api/v10\'',
          'attributes' => 
          array (
            'startLine' => 32,
            'endLine' => 32,
            'startTokenPos' => 46,
            'startFilePos' => 903,
            'endTokenPos' => 46,
            'endFilePos' => 931,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 67,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'validate' => 
      array (
        'name' => 'validate',
        'parameters' => 
        array (
          'credentials' => 
          array (
            'name' => 'credentials',
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
            'startLine' => 39,
            'endLine' => 39,
            'startColumn' => 30,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'mode' => 
          array (
            'name' => 'mode',
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
            'startLine' => 39,
            'endLine' => 39,
            'startColumn' => 50,
            'endColumn' => 61,
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
            'name' => 'App\\Messenger\\Validation\\ValidationResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Validate credentials for the given mode.
 *
 * @param  array<string, mixed>  $credentials
 */',
        'startLine' => 39,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Validation',
        'declaringClassName' => 'App\\Messenger\\Validation\\DiscordCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\DiscordCredentialValidator',
        'currentClassName' => 'App\\Messenger\\Validation\\DiscordCredentialValidator',
        'aliasName' => NULL,
      ),
      'validateRequiredFields' => 
      array (
        'name' => 'validateRequiredFields',
        'parameters' => 
        array (
          'credentials' => 
          array (
            'name' => 'credentials',
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
            'startLine' => 63,
            'endLine' => 63,
            'startColumn' => 45,
            'endColumn' => 62,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'mode' => 
          array (
            'name' => 'mode',
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
            'startLine' => 63,
            'endLine' => 63,
            'startColumn' => 65,
            'endColumn' => 76,
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
 * Check that all required fields are present for the mode.
 *
 * @param  array<string, mixed>  $credentials
 * @return array<string, string>
 */',
        'startLine' => 63,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Validation',
        'declaringClassName' => 'App\\Messenger\\Validation\\DiscordCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\DiscordCredentialValidator',
        'currentClassName' => 'App\\Messenger\\Validation\\DiscordCredentialValidator',
        'aliasName' => NULL,
      ),
      'validateFormats' => 
      array (
        'name' => 'validateFormats',
        'parameters' => 
        array (
          'credentials' => 
          array (
            'name' => 'credentials',
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
            'startLine' => 93,
            'endLine' => 93,
            'startColumn' => 38,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'mode' => 
          array (
            'name' => 'mode',
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
            'startLine' => 93,
            'endLine' => 93,
            'startColumn' => 58,
            'endColumn' => 69,
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
 * Validate credential formats.
 *
 * @param  array<string, mixed>  $credentials
 * @return array<string, string>
 */',
        'startLine' => 93,
        'endLine' => 113,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Validation',
        'declaringClassName' => 'App\\Messenger\\Validation\\DiscordCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\DiscordCredentialValidator',
        'currentClassName' => 'App\\Messenger\\Validation\\DiscordCredentialValidator',
        'aliasName' => NULL,
      ),
      'validateViaApi' => 
      array (
        'name' => 'validateViaApi',
        'parameters' => 
        array (
          'credentials' => 
          array (
            'name' => 'credentials',
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
            'startLine' => 120,
            'endLine' => 120,
            'startColumn' => 37,
            'endColumn' => 54,
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
            'name' => 'App\\Messenger\\Validation\\ValidationResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Validate credentials by calling Discord\'s /users/@me API.
 *
 * @param  array<string, mixed>  $credentials
 */',
        'startLine' => 120,
        'endLine' => 173,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Validation',
        'declaringClassName' => 'App\\Messenger\\Validation\\DiscordCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\DiscordCredentialValidator',
        'currentClassName' => 'App\\Messenger\\Validation\\DiscordCredentialValidator',
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