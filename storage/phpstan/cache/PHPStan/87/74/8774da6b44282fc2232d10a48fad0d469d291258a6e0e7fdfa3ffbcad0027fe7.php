<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Messenger/Reliability/DeadLetterManager.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Messenger\Reliability\DeadLetterManager
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-b2a7cbb53f67d677701db7e08a1c2ac26cfaf57dffae882ff5c11485a6c158b8',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'filename' => '/Users/garethdaine/Code/agent/app/Messenger/Reliability/DeadLetterManager.php',
      ),
    ),
    'namespace' => 'App\\Messenger\\Reliability',
    'name' => 'App\\Messenger\\Reliability\\DeadLetterManager',
    'shortName' => 'DeadLetterManager',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Manages the dead-letter queue for failed messenger messages.
 *
 * Messages that exceed retry duration are moved to the DLQ where they can be
 * inspected, manually retried, or discarded. Provides methods for moving
 * messages to the DLQ, retrying individual or bulk messages, and querying
 * dead letters by connector.
 *
 * Conditions for correctness:
 * - Original payload must contain message_id, session_id, and user_id for retry to succeed
 * - Connector account must exist and be active for retry to succeed
 * - Retry operations are atomic: success deletes the record, failure updates error history
 *
 * Known limitations:
 * - Retry does not validate connector health before dispatching
 * - No automatic retry scheduling; all retries are manual
 * - Error history grows unbounded (consider pruning in long-running systems)
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 30,
    'endLine' => 202,
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
    ),
    'immediateMethods' => 
    array (
      'moveToDeadLetter' => 
      array (
        'name' => 'moveToDeadLetter',
        'parameters' => 
        array (
          'connector' => 
          array (
            'name' => 'connector',
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
            'startLine' => 39,
            'endLine' => 39,
            'startColumn' => 9,
            'endColumn' => 35,
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
            'startLine' => 40,
            'endLine' => 40,
            'startColumn' => 9,
            'endColumn' => 22,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'error' => 
          array (
            'name' => 'error',
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
            'startLine' => 41,
            'endLine' => 41,
            'startColumn' => 9,
            'endColumn' => 21,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'attempts' => 
          array (
            'name' => 'attempts',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 9,
            'endColumn' => 21,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Models\\MessengerDeadLetter',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Move a failed message to the dead-letter queue.
 *
 * Creates a new dead letter record capturing the original payload,
 * error message, and number of attempts made before failure.
 */',
        'startLine' => 38,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'implementingClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'currentClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'aliasName' => NULL,
      ),
      'retry' => 
      array (
        'name' => 'retry',
        'parameters' => 
        array (
          'deadLetterId' => 
          array (
            'name' => 'deadLetterId',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
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
            'startColumn' => 27,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Retry a dead letter message by dispatching a new job.
 *
 * On success, deletes the dead letter record.
 * On failure, updates error_history and increments attempts.
 *
 * @param  int  $deadLetterId  The ID of the dead letter to retry
 * @return bool True if retry job was successfully dispatched, false otherwise
 *
 * @throws \\Illuminate\\Database\\Eloquent\\ModelNotFoundException If dead letter not found
 */',
        'startLine' => 77,
        'endLine' => 113,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'implementingClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'currentClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'aliasName' => NULL,
      ),
      'retryBulk' => 
      array (
        'name' => 'retryBulk',
        'parameters' => 
        array (
          'ids' => 
          array (
            'name' => 'ids',
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
            'startLine' => 123,
            'endLine' => 123,
            'startColumn' => 31,
            'endColumn' => 40,
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
 * Retry multiple dead letter messages in bulk.
 *
 * Returns an array of results indicating success/failure for each ID.
 *
 * @param  array<int>  $ids  Array of dead letter IDs to retry
 * @return array<array{id: int, success: bool}> Results for each retry attempt
 */',
        'startLine' => 123,
        'endLine' => 129,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'implementingClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'currentClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'aliasName' => NULL,
      ),
      'getByConnector' => 
      array (
        'name' => 'getByConnector',
        'parameters' => 
        array (
          'connectorAccountId' => 
          array (
            'name' => 'connectorAccountId',
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
            'startLine' => 137,
            'endLine' => 137,
            'startColumn' => 36,
            'endColumn' => 61,
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
            'name' => 'Illuminate\\Support\\Collection',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get dead letters for a specific connector.
 *
 * @param  string  $connectorAccountId  The connector account ID
 * @return Collection<int, MessengerDeadLetter>
 */',
        'startLine' => 137,
        'endLine' => 143,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'implementingClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'currentClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'aliasName' => NULL,
      ),
      'delete' => 
      array (
        'name' => 'delete',
        'parameters' => 
        array (
          'deadLetterId' => 
          array (
            'name' => 'deadLetterId',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 152,
            'endLine' => 152,
            'startColumn' => 28,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Delete a dead letter record.
 *
 * @param  int  $deadLetterId  The ID of the dead letter to delete
 *
 * @throws \\Illuminate\\Database\\Eloquent\\ModelNotFoundException If dead letter not found
 */',
        'startLine' => 152,
        'endLine' => 160,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'implementingClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'currentClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'aliasName' => NULL,
      ),
      'getCount' => 
      array (
        'name' => 'getCount',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get total count of dead letters.
 */',
        'startLine' => 165,
        'endLine' => 168,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'implementingClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'currentClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'aliasName' => NULL,
      ),
      'getCountForConnector' => 
      array (
        'name' => 'getCountForConnector',
        'parameters' => 
        array (
          'connectorAccountId' => 
          array (
            'name' => 'connectorAccountId',
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
            'startLine' => 173,
            'endLine' => 173,
            'startColumn' => 42,
            'endColumn' => 67,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get count of dead letters for a specific connector.
 */',
        'startLine' => 173,
        'endLine' => 178,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'implementingClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'currentClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'aliasName' => NULL,
      ),
      'validatePayloadForRetry' => 
      array (
        'name' => 'validatePayloadForRetry',
        'parameters' => 
        array (
          'payload' => 
          array (
            'name' => 'payload',
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
            'startLine' => 185,
            'endLine' => 185,
            'startColumn' => 46,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Validate that the payload contains required fields for retry.
 *
 * @throws \\InvalidArgumentException If required fields are missing
 */',
        'startLine' => 185,
        'endLine' => 201,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Messenger\\Reliability',
        'declaringClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'implementingClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
        'currentClassName' => 'App\\Messenger\\Reliability\\DeadLetterManager',
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