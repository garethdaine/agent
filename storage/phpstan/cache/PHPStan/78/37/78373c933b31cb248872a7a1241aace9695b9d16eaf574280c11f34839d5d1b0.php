<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Messenger/Validation/SlackCredentialValidator.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Messenger\Validation\SlackCredentialValidator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-b22e3db384076d586a5e99300d449054623dde4f63a448c68393e004d7585e6d',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
        'filename' => '/Users/garethdaine/Code/agent/app/Messenger/Validation/SlackCredentialValidator.php',
      ),
    ),
    'namespace' => 'App\\Messenger\\Validation',
    'name' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
    'shortName' => 'SlackCredentialValidator',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Validates Slack credentials based on connection mode.
 *
 * Token Formats:
 * - App tokens (xapp-*): Required for Socket Mode local connections
 *   Format: xapp-{version}-{app_id}-{timestamp}-{hash}
 *
 * - Bot tokens (xoxb-*): Required for all API calls
 *   Format: xoxb-{team_id}-{bot_user_id}-{secret}
 *
 * - Signing secrets: Required for webhook signature verification
 *   Format: 32 character hex string
 *
 * Mode Requirements:
 * - Local (Socket Mode): app_token + bot_token
 * - Webhook: bot_token + signing_secret
 *
 * Validation is performed in two phases:
 * 1. Format validation (regex patterns)
 * 2. API validation (auth.test call)
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 32,
    'endLine' => 194,
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
      'SLACK_API_BASE' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
        'name' => 'SLACK_API_BASE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'https://slack.com/api\'',
          'attributes' => 
          array (
            'startLine' => 34,
            'endLine' => 34,
            'startTokenPos' => 46,
            'startFilePos' => 907,
            'endTokenPos' => 46,
            'endFilePos' => 929,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 59,
      ),
      'APP_TOKEN_PATTERN' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
        'name' => 'APP_TOKEN_PATTERN',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'/^xapp-\\d-[A-Z0-9]+-\\d+-[a-zA-Z0-9]+$/\'',
          'attributes' => 
          array (
            'startLine' => 40,
            'endLine' => 40,
            'startTokenPos' => 59,
            'startFilePos' => 1082,
            'endTokenPos' => 59,
            'endFilePos' => 1121,
          ),
        ),
        'docComment' => '/**
 * Regex pattern for app tokens.
 * Format: xapp-{version}-{app_id}-{timestamp}-{hash}
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 40,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 79,
      ),
      'BOT_TOKEN_PATTERN' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
        'name' => 'BOT_TOKEN_PATTERN',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'/^xoxb-\\d+-\\d+-[a-zA-Z0-9]+$/\'',
          'attributes' => 
          array (
            'startLine' => 46,
            'endLine' => 46,
            'startTokenPos' => 72,
            'startFilePos' => 1269,
            'endTokenPos' => 72,
            'endFilePos' => 1299,
          ),
        ),
        'docComment' => '/**
 * Regex pattern for bot tokens.
 * Format: xoxb-{team_id}-{bot_user_id}-{secret}
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 46,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 70,
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
            'startLine' => 53,
            'endLine' => 53,
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
            'startLine' => 53,
            'endLine' => 53,
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
        'startLine' => 53,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Validation',
        'declaringClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
        'currentClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
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
            'startLine' => 77,
            'endLine' => 77,
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
            'startLine' => 77,
            'endLine' => 77,
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
        'startLine' => 77,
        'endLine' => 99,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Validation',
        'declaringClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
        'currentClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
        'aliasName' => NULL,
      ),
      'validateTokenFormats' => 
      array (
        'name' => 'validateTokenFormats',
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
            'startLine' => 107,
            'endLine' => 107,
            'startColumn' => 43,
            'endColumn' => 60,
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
            'startLine' => 107,
            'endLine' => 107,
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
 * Validate token format using regex patterns.
 *
 * @param  array<string, mixed>  $credentials
 * @return array<string, string>
 */',
        'startLine' => 107,
        'endLine' => 134,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Validation',
        'declaringClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
        'currentClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
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
            'startLine' => 141,
            'endLine' => 141,
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
 * Validate credentials by calling Slack\'s auth.test API.
 *
 * @param  array<string, mixed>  $credentials
 */',
        'startLine' => 141,
        'endLine' => 193,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Validation',
        'declaringClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
        'currentClassName' => 'App\\Messenger\\Validation\\SlackCredentialValidator',
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