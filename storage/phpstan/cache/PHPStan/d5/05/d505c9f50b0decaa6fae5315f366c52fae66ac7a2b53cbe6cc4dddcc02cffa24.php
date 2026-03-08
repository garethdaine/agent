<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Messenger/Adapters/WhatsAppAdapter.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Messenger\Adapters\WhatsAppAdapter
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-8bbba7bd686396baf09c60f870eecc25e72af817487d21e074a31e985c496a36',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Messenger/Adapters/WhatsAppAdapter.php',
      ),
    ),
    'namespace' => 'App\\Support\\Messenger\\Adapters',
    'name' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
    'shortName' => 'WhatsAppAdapter',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * WhatsApp adapter implementing the messenger connector interface.
 *
 * WhatsApp Cloud API v18+ is used for all API calls.
 * Webhook-only mode (no local/Gateway mode supported).
 *
 * Threading Strategy:
 * - Quote replies: Include context.message_id in message payload
 * - Falls back to single summary message if chain >5 messages
 *
 * Identity Mapping:
 * - Phone numbers (E.164 format) mapped to MessengerIdentityLink
 *
 * Rate Limiting:
 * - Meta API rate limits apply
 * - Should handle 429 responses with backoff
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 36,
    'endLine' => 527,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Support\\Messenger\\Adapters\\AbstractConnectorAdapter',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'API_BASE_URL' => 
      array (
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'name' => 'API_BASE_URL',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'https://graph.facebook.com/v18.0\'',
          'attributes' => 
          array (
            'startLine' => 38,
            'endLine' => 38,
            'startTokenPos' => 90,
            'startFilePos' => 1115,
            'endTokenPos' => 90,
            'endFilePos' => 1148,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 68,
      ),
      'MAX_QUOTE_CHAIN_DEPTH' => 
      array (
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'name' => 'MAX_QUOTE_CHAIN_DEPTH',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '5',
          'attributes' => 
          array (
            'startLine' => 40,
            'endLine' => 40,
            'startTokenPos' => 101,
            'startFilePos' => 1194,
            'endTokenPos' => 101,
            'endFilePos' => 1194,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 40,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 44,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'getProviderName' => 
      array (
        'name' => 'getProviderName',
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
        'startLine' => 42,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'aliasName' => NULL,
      ),
      'verifyWebhookSignature' => 
      array (
        'name' => 'verifyWebhookSignature',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 55,
            'endLine' => 55,
            'startColumn' => 44,
            'endColumn' => 59,
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
 * Verify the webhook signature using HMAC-SHA256.
 *
 * WhatsApp uses HMAC-SHA256 signatures with:
 * - X-Hub-Signature-256: sha256=hex_encoded_signature
 * - Message = raw body
 * - Key = app_secret
 */',
        'startLine' => 55,
        'endLine' => 93,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'aliasName' => NULL,
      ),
      'parseInboundMessage' => 
      array (
        'name' => 'parseInboundMessage',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
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
            'startColumn' => 41,
            'endColumn' => 56,
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
            'name' => 'App\\DTOs\\Messenger\\NormalizedMessage',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Parse an inbound webhook request into a normalized message.
 *
 * Handles:
 * - Text messages
 * - Media messages (image, document, audio, video)
 * - Status updates (sent, delivered, read) - returns empty content
 */',
        'startLine' => 103,
        'endLine' => 133,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'aliasName' => NULL,
      ),
      'sendMessage' => 
      array (
        'name' => 'sendMessage',
        'parameters' => 
        array (
          'session' => 
          array (
            'name' => 'session',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\ChatSession',
                'isIdentifier' => false,
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
            'startColumn' => 33,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'payload' => 
          array (
            'name' => 'payload',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\DTOs\\Messenger\\OutboundPayload',
                'isIdentifier' => false,
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
            'startColumn' => 55,
            'endColumn' => 78,
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
            'name' => 'App\\DTOs\\Messenger\\ProviderResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Send a message to a WhatsApp user.
 */',
        'startLine' => 138,
        'endLine' => 219,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'aliasName' => NULL,
      ),
      'sendTemplate' => 
      array (
        'name' => 'sendTemplate',
        'parameters' => 
        array (
          'session' => 
          array (
            'name' => 'session',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\ChatSession',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 227,
            'endLine' => 227,
            'startColumn' => 9,
            'endColumn' => 28,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'phoneNumber' => 
          array (
            'name' => 'phoneNumber',
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
            'startLine' => 228,
            'endLine' => 228,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'templateName' => 
          array (
            'name' => 'templateName',
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
            'startLine' => 229,
            'endLine' => 229,
            'startColumn' => 9,
            'endColumn' => 28,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'components' => 
          array (
            'name' => 'components',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 230,
                'endLine' => 230,
                'startTokenPos' => 1148,
                'startFilePos' => 7196,
                'endTokenPos' => 1149,
                'endFilePos' => 7197,
              ),
            ),
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
            'startLine' => 230,
            'endLine' => 230,
            'startColumn' => 9,
            'endColumn' => 30,
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
            'name' => 'App\\DTOs\\Messenger\\ProviderResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Send a template message (for outbound outside 24h window).
 *
 * @param  array<int, array<string, mixed>>  $components
 */',
        'startLine' => 226,
        'endLine' => 288,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'aliasName' => NULL,
      ),
      'supportsReactions' => 
      array (
        'name' => 'supportsReactions',
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
        'startLine' => 290,
        'endLine' => 293,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'aliasName' => NULL,
      ),
      'addReaction' => 
      array (
        'name' => 'addReaction',
        'parameters' => 
        array (
          'session' => 
          array (
            'name' => 'session',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\ChatSession',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 295,
            'endLine' => 295,
            'startColumn' => 33,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'messageId' => 
          array (
            'name' => 'messageId',
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
            'startLine' => 295,
            'endLine' => 295,
            'startColumn' => 55,
            'endColumn' => 71,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'emoji' => 
          array (
            'name' => 'emoji',
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
            'startLine' => 295,
            'endLine' => 295,
            'startColumn' => 74,
            'endColumn' => 86,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\DTOs\\Messenger\\ProviderResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 295,
        'endLine' => 343,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'aliasName' => NULL,
      ),
      'supportsThreading' => 
      array (
        'name' => 'supportsThreading',
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
        'startLine' => 345,
        'endLine' => 348,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'aliasName' => NULL,
      ),
      'getThreadingStrategy' => 
      array (
        'name' => 'getThreadingStrategy',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\DTOs\\Messenger\\ThreadingStrategy',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 350,
        'endLine' => 353,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'aliasName' => NULL,
      ),
      'getReplayProtectionStrategy' => 
      array (
        'name' => 'getReplayProtectionStrategy',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\DTOs\\Messenger\\ReplayProtectionStrategy',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 355,
        'endLine' => 358,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'aliasName' => NULL,
      ),
      'parseMessage' => 
      array (
        'name' => 'parseMessage',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
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
            'startLine' => 366,
            'endLine' => 366,
            'startColumn' => 35,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'contact' => 
          array (
            'name' => 'contact',
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
            'startLine' => 366,
            'endLine' => 366,
            'startColumn' => 51,
            'endColumn' => 64,
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
            'name' => 'App\\DTOs\\Messenger\\NormalizedMessage',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Parse a WhatsApp message into a normalized message.
 *
 * @param  array<string, mixed>  $message
 * @param  array<string, mixed>  $contact
 */',
        'startLine' => 366,
        'endLine' => 446,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'aliasName' => NULL,
      ),
      'parseStatusUpdate' => 
      array (
        'name' => 'parseStatusUpdate',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
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
            'startLine' => 455,
            'endLine' => 455,
            'startColumn' => 40,
            'endColumn' => 51,
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
            'name' => 'App\\DTOs\\Messenger\\NormalizedMessage',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Parse a status update into a normalized message.
 *
 * Status updates (sent, delivered, read) don\'t have message content.
 *
 * @param  array<string, mixed>  $value
 */',
        'startLine' => 455,
        'endLine' => 470,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'aliasName' => NULL,
      ),
      'normalizePhone' => 
      array (
        'name' => 'normalizePhone',
        'parameters' => 
        array (
          'phone' => 
          array (
            'name' => 'phone',
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
            'startLine' => 477,
            'endLine' => 477,
            'startColumn' => 37,
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
 * Normalize a phone number to E.164 format.
 *
 * Removes all non-numeric characters except leading +.
 */',
        'startLine' => 477,
        'endLine' => 488,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'aliasName' => NULL,
      ),
      'isChainTooLong' => 
      array (
        'name' => 'isChainTooLong',
        'parameters' => 
        array (
          'session' => 
          array (
            'name' => 'session',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\ChatSession',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 496,
            'endLine' => 496,
            'startColumn' => 37,
            'endColumn' => 56,
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
 * Check if the quote chain is too long.
 *
 * If chain depth exceeds MAX_QUOTE_CHAIN_DEPTH, we should send
 * a summary message instead of another quote reply.
 */',
        'startLine' => 496,
        'endLine' => 502,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'aliasName' => NULL,
      ),
      'getPhoneNumberId' => 
      array (
        'name' => 'getPhoneNumberId',
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
            'startLine' => 507,
            'endLine' => 507,
            'startColumn' => 39,
            'endColumn' => 63,
            'parameterIndex' => 0,
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
 * Get the phone number ID from credentials.
 */',
        'startLine' => 507,
        'endLine' => 510,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'aliasName' => NULL,
      ),
      'getAccessToken' => 
      array (
        'name' => 'getAccessToken',
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
            'startLine' => 515,
            'endLine' => 515,
            'startColumn' => 37,
            'endColumn' => 61,
            'parameterIndex' => 0,
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
 * Get the access token from credentials.
 */',
        'startLine' => 515,
        'endLine' => 518,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'aliasName' => NULL,
      ),
      'getAppSecret' => 
      array (
        'name' => 'getAppSecret',
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
            'startLine' => 523,
            'endLine' => 523,
            'startColumn' => 35,
            'endColumn' => 59,
            'parameterIndex' => 0,
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
 * Get the app secret for signature verification.
 */',
        'startLine' => 523,
        'endLine' => 526,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Messenger\\Adapters',
        'declaringClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'implementingClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
        'currentClassName' => 'App\\Support\\Messenger\\Adapters\\WhatsAppAdapter',
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