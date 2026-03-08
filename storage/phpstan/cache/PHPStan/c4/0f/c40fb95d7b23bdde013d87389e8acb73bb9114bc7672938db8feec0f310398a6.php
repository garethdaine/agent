<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Delegation/DelegationReconciler.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Delegation\DelegationReconciler
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-eb0ba32f9aeb6cd1d7f9998260ff5d8a4faf07c8cb52bff5214b35f53436f92c',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Delegation\\DelegationReconciler',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Delegation/DelegationReconciler.php',
      ),
    ),
    'namespace' => 'App\\Support\\Delegation',
    'name' => 'App\\Support\\Delegation\\DelegationReconciler',
    'shortName' => 'DelegationReconciler',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Scheduled reconciliation service for the Delegation Engine.
 *
 * Handles:
 * - Expired human approval verification results
 * - Blocked tasks awaiting delegatee assignment
 * - Stuck running graphs
 * - Graceful cancellation timeout enforcement
 *
 * Runs every 2 minutes via delegation:reconcile command.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 24,
    'endLine' => 275,
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
      'BACKOFF_DELAYS' => 
      array (
        'declaringClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'name' => 'BACKOFF_DELAYS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[60, 300, 900]',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 58,
            'startFilePos' => 653,
            'endTokenPos' => 66,
            'endFilePos' => 666,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 50,
      ),
      'MAX_RETRIES' => 
      array (
        'declaringClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'name' => 'MAX_RETRIES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '3',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 79,
            'startFilePos' => 737,
            'endTokenPos' => 79,
            'endFilePos' => 737,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 34,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'reconcile' => 
      array (
        'name' => 'reconcile',
        'parameters' => 
        array (
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
 * Run all reconciliation tasks.
 */',
        'startLine' => 33,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'currentClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'aliasName' => NULL,
      ),
      'handleExpiredHumanApprovals' => 
      array (
        'name' => 'handleExpiredHumanApprovals',
        'parameters' => 
        array (
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
 * Mark expired human approval verification results as failed.
 *
 * When a human approval step has an expires_at that is in the past
 * and the verdict is still pending, mark it as failed and fire
 * the DelegationTaskVerified event to resume the pipeline.
 */',
        'startLine' => 48,
        'endLine' => 79,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'currentClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'aliasName' => NULL,
      ),
      'retryBlockedTasks' => 
      array (
        'name' => 'retryBlockedTasks',
        'parameters' => 
        array (
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
 * Retry assignment for blocked tasks with exponential backoff.
 *
 * Tasks become blocked when no matching delegatee profile is available.
 * This method attempts to re-assign them using backoff delays of 1/5/15 minutes.
 * After 3 failed retries, the task is marked as permanently failed.
 */',
        'startLine' => 88,
        'endLine' => 131,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'currentClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'aliasName' => NULL,
      ),
      'handleStuckGraphs' => 
      array (
        'name' => 'handleStuckGraphs',
        'parameters' => 
        array (
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
 * Handle stuck running graphs.
 *
 * Detects graphs that are in RUNNING status but have no active tasks
 * and no incomplete work. Transitions them to appropriate terminal states:
 * - SUCCEEDED: all tasks succeeded
 * - FAILED: any task failed
 * - PARTIAL: mix of succeeded and cancelled (no failures)
 */',
        'startLine' => 142,
        'endLine' => 200,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'currentClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'aliasName' => NULL,
      ),
      'enforceGracefulCancellationTimeout' => 
      array (
        'name' => 'enforceGracefulCancellationTimeout',
        'parameters' => 
        array (
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
 * Force-kill running tasks after graceful cancellation timeout.
 *
 * When a graph is cancelled, we allow running tasks a grace period
 * (default 15 minutes) to complete naturally. After this timeout,
 * we force-kill any remaining running tasks.
 */',
        'startLine' => 209,
        'endLine' => 223,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'currentClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'aliasName' => NULL,
      ),
      'forceKillRunningTasks' => 
      array (
        'name' => 'forceKillRunningTasks',
        'parameters' => 
        array (
          'graph' => 
          array (
            'name' => 'graph',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\DelegationGraph',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 233,
            'endLine' => 233,
            'startColumn' => 44,
            'endColumn' => 65,
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
 * Force-kill all running tasks and their attempts for a graph.
 *
 * Uses a hybrid approach:
 * 1. Update AgentJobRun status to \'killed\' (database status)
 * 2. Mark attempts as failed
 * 3. Mark tasks as cancelled
 */',
        'startLine' => 233,
        'endLine' => 274,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation',
        'declaringClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'implementingClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
        'currentClassName' => 'App\\Support\\Delegation\\DelegationReconciler',
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