<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Org/OrgRitualRunService.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Org\OrgRitualRunService
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-4115239b9ad216e318c58e4527ed6904646babc54b4c9a4a60552d940a236133',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Org\\OrgRitualRunService',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Org/OrgRitualRunService.php',
      ),
    ),
    'namespace' => 'App\\Support\\Org',
    'name' => 'App\\Support\\Org\\OrgRitualRunService',
    'shortName' => 'OrgRitualRunService',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Service for managing OrgRitualRun lifecycle.
 *
 * Responsibilities:
 * - Create new runs from templates
 * - Manage run state transitions (queued -> running -> succeeded/failed/partial)
 * - Persist phase outputs as phases complete
 * - Support partial retry by preserving successful phase outputs
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 145,
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
      'createRun' => 
      array (
        'name' => 'createRun',
        'parameters' => 
        array (
          'template' => 
          array (
            'name' => 'template',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\OrgRitualTemplate',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 29,
            'endLine' => 29,
            'startColumn' => 31,
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
            'name' => 'App\\Models\\OrgRitualRun',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new ritual run from a template.
 *
 * Initializes the run in \'queued\' state with a new correlation ID.
 * The run inherits the user_id from the template.
 *
 * @param  OrgRitualTemplate  $template  The template to create a run from
 * @return OrgRitualRun The newly created run
 */',
        'startLine' => 29,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Org',
        'declaringClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'implementingClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'currentClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'aliasName' => NULL,
      ),
      'completePhase' => 
      array (
        'name' => 'completePhase',
        'parameters' => 
        array (
          'run' => 
          array (
            'name' => 'run',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\OrgRitualRun',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 50,
            'endLine' => 50,
            'startColumn' => 35,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'phaseId' => 
          array (
            'name' => 'phaseId',
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
            'startLine' => 50,
            'endLine' => 50,
            'startColumn' => 54,
            'endColumn' => 68,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'output' => 
          array (
            'name' => 'output',
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
            'startLine' => 50,
            'endLine' => 50,
            'startColumn' => 71,
            'endColumn' => 83,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Mark a phase as completed and persist its output.
 *
 * Merges the new phase output into the existing phase_outputs array.
 * This preserves outputs from previously completed phases.
 *
 * @param  OrgRitualRun  $run  The run to update
 * @param  string  $phaseId  The ID of the completed phase
 * @param  array  $output  The output data from the phase
 */',
        'startLine' => 50,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Org',
        'declaringClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'implementingClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'currentClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'aliasName' => NULL,
      ),
      'retryFailedPhases' => 
      array (
        'name' => 'retryFailedPhases',
        'parameters' => 
        array (
          'originalRun' => 
          array (
            'name' => 'originalRun',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\OrgRitualRun',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 68,
            'endLine' => 68,
            'startColumn' => 39,
            'endColumn' => 63,
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
            'name' => 'App\\Models\\OrgRitualRun',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Retry failed phases from a partial run.
 *
 * Creates a new run that preserves successful phase outputs from the
 * original run. Only phases with non-null outputs are considered
 * successful and preserved.
 *
 * @param  OrgRitualRun  $originalRun  The partial run to retry from
 * @return OrgRitualRun The new run with preserved outputs
 */',
        'startLine' => 68,
        'endLine' => 83,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Org',
        'declaringClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'implementingClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'currentClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'aliasName' => NULL,
      ),
      'markRunning' => 
      array (
        'name' => 'markRunning',
        'parameters' => 
        array (
          'run' => 
          array (
            'name' => 'run',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\OrgRitualRun',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 92,
            'endLine' => 92,
            'startColumn' => 33,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Mark a run as running.
 *
 * Sets the state to \'running\' and records the start time.
 *
 * @param  OrgRitualRun  $run  The run to start
 */',
        'startLine' => 92,
        'endLine' => 98,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Org',
        'declaringClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'implementingClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'currentClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'aliasName' => NULL,
      ),
      'markSucceeded' => 
      array (
        'name' => 'markSucceeded',
        'parameters' => 
        array (
          'run' => 
          array (
            'name' => 'run',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\OrgRitualRun',
                'isIdentifier' => false,
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
            'startColumn' => 35,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Mark a run as succeeded.
 *
 * Sets the state to \'succeeded\' and records the completion time.
 *
 * @param  OrgRitualRun  $run  The run to mark as succeeded
 */',
        'startLine' => 107,
        'endLine' => 113,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Org',
        'declaringClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'implementingClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'currentClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'aliasName' => NULL,
      ),
      'markFailed' => 
      array (
        'name' => 'markFailed',
        'parameters' => 
        array (
          'run' => 
          array (
            'name' => 'run',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\OrgRitualRun',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 122,
            'endLine' => 122,
            'startColumn' => 32,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Mark a run as failed.
 *
 * Sets the state to \'failed\' and records the completion time.
 *
 * @param  OrgRitualRun  $run  The run to mark as failed
 */',
        'startLine' => 122,
        'endLine' => 128,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Org',
        'declaringClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'implementingClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'currentClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'aliasName' => NULL,
      ),
      'markPartial' => 
      array (
        'name' => 'markPartial',
        'parameters' => 
        array (
          'run' => 
          array (
            'name' => 'run',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\OrgRitualRun',
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Mark a run as partial.
 *
 * Sets the state to \'partial\' and records the completion time.
 * A partial run has some successful phases and some failed phases.
 *
 * @param  OrgRitualRun  $run  The run to mark as partial
 */',
        'startLine' => 138,
        'endLine' => 144,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Org',
        'declaringClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'implementingClassName' => 'App\\Support\\Org\\OrgRitualRunService',
        'currentClassName' => 'App\\Support\\Org\\OrgRitualRunService',
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