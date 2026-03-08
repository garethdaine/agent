<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Messenger/Validation/WhatsAppCredentialValidator.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Messenger\Validation\WhatsAppCredentialValidator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-3c3e730aa1e956bd0445de88fa8caaeec2849628fdbb66b7f79b6260cac0a503',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Messenger\\Validation\\WhatsAppCredentialValidator',
        'filename' => '/Users/garethdaine/Code/agent/app/Messenger/Validation/WhatsAppCredentialValidator.php',
      ),
    ),
    'namespace' => 'App\\Messenger\\Validation',
    'name' => 'App\\Messenger\\Validation\\WhatsAppCredentialValidator',
    'shortName' => 'WhatsAppCredentialValidator',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Validates WhatsApp credentials.
 *
 * WhatsApp Cloud API is webhook-only. No local mode is supported.
 *
 * Required Credentials (webhook mode only):
 * - access_token: Graph API access token (typically starts with EAA)
 * - phone_number_id: WhatsApp Business Phone Number ID (numeric string)
 * - app_secret: Meta App Secret for webhook signature verification
 * - verify_token: Webhook verification token (user-defined)
 * - webhook_url: Publicly accessible webhook URL (HTTPS required)
 *
 * Validation is performed in phases:
 * 1. Mode check (webhook only)
 * 2. Required fields check
 * 3. Format validation
 * 4. API validation (/me call with access token)
 * 5. Phone number ID validation (/v18.0/{phone_number_id})
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 30,
    'endLine' => 200,
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
      'GRAPH_API_BASE' => 
      array (
        'declaringClassName' => 'App\\Messenger\\Validation\\WhatsAppCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\WhatsAppCredentialValidator',
        'name' => 'GRAPH_API_BASE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'https://graph.facebook.com/v18.0\'',
          'attributes' => 
          array (
            'startLine' => 32,
            'endLine' => 32,
            'startTokenPos' => 46,
            'startFilePos' => 983,
            'endTokenPos' => 46,
            'endFilePos' => 1016,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
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
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Validation',
        'declaringClassName' => 'App\\Messenger\\Validation\\WhatsAppCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\WhatsAppCredentialValidator',
        'currentClassName' => 'App\\Messenger\\Validation\\WhatsAppCredentialValidator',
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
            'startLine' => 70,
            'endLine' => 70,
            'startColumn' => 45,
            'endColumn' => 62,
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
 * Check that all required fields are present.
 *
 * @param  array<string, mixed>  $credentials
 * @return array<string, string>
 */',
        'startLine' => 70,
        'endLine' => 95,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Validation',
        'declaringClassName' => 'App\\Messenger\\Validation\\WhatsAppCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\WhatsAppCredentialValidator',
        'currentClassName' => 'App\\Messenger\\Validation\\WhatsAppCredentialValidator',
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
            'startLine' => 103,
            'endLine' => 103,
            'startColumn' => 38,
            'endColumn' => 55,
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
 * Validate credential formats.
 *
 * @param  array<string, mixed>  $credentials
 * @return array<string, string>
 */',
        'startLine' => 103,
        'endLine' => 129,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Validation',
        'declaringClassName' => 'App\\Messenger\\Validation\\WhatsAppCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\WhatsAppCredentialValidator',
        'currentClassName' => 'App\\Messenger\\Validation\\WhatsAppCredentialValidator',
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
            'startLine' => 136,
            'endLine' => 136,
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
 * Validate credentials by calling Graph API.
 *
 * @param  array<string, mixed>  $credentials
 */',
        'startLine' => 136,
        'endLine' => 199,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Validation',
        'declaringClassName' => 'App\\Messenger\\Validation\\WhatsAppCredentialValidator',
        'implementingClassName' => 'App\\Messenger\\Validation\\WhatsAppCredentialValidator',
        'currentClassName' => 'App\\Messenger\\Validation\\WhatsAppCredentialValidator',
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