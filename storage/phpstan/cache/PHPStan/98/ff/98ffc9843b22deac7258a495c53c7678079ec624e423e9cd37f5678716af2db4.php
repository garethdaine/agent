<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Listeners/Org/RitualPhaseOutputCaptureListener.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Listeners\Org\RitualPhaseOutputCaptureListener
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-7ba600f33e314ab482224605985a66ec477770b7e41778909922ae88eb6ba15f',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Listeners\\Org\\RitualPhaseOutputCaptureListener',
        'filename' => '/Users/garethdaine/Code/agent/app/Listeners/Org/RitualPhaseOutputCaptureListener.php',
      ),
    ),
    'namespace' => 'App\\Listeners\\Org',
    'name' => 'App\\Listeners\\Org\\RitualPhaseOutputCaptureListener',
    'shortName' => 'RitualPhaseOutputCaptureListener',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Captures full attempt metadata as phase output when a ritual-linked
 * delegation task completes verification (pass or fail).
 *
 * Writes structured output to OrgRitualRun::phase_outputs via the
 * OrgRitualRunService::completePhase() method.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 97,
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
      'MAX_OUTPUT_BYTES' => 
      array (
        'declaringClassName' => 'App\\Listeners\\Org\\RitualPhaseOutputCaptureListener',
        'implementingClassName' => 'App\\Listeners\\Org\\RitualPhaseOutputCaptureListener',
        'name' => 'MAX_OUTPUT_BYTES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '65536',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 43,
            'startFilePos' => 510,
            'endTokenPos' => 43,
            'endFilePos' => 514,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 43,
      ),
    ),
    'immediateProperties' => 
    array (
      'runService' => 
      array (
        'declaringClassName' => 'App\\Listeners\\Org\\RitualPhaseOutputCaptureListener',
        'implementingClassName' => 'App\\Listeners\\Org\\RitualPhaseOutputCaptureListener',
        'name' => 'runService',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Support\\Org\\OrgRitualRunService',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 9,
        'endColumn' => 56,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'runService' => 
          array (
            'name' => 'runService',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Support\\Org\\OrgRitualRunService',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 22,
            'endLine' => 22,
            'startColumn' => 9,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 21,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Listeners\\Org',
        'declaringClassName' => 'App\\Listeners\\Org\\RitualPhaseOutputCaptureListener',
        'implementingClassName' => 'App\\Listeners\\Org\\RitualPhaseOutputCaptureListener',
        'currentClassName' => 'App\\Listeners\\Org\\RitualPhaseOutputCaptureListener',
        'aliasName' => NULL,
      ),
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Events\\DelegationTaskVerified',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 25,
            'endLine' => 25,
            'startColumn' => 28,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 25,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Listeners\\Org',
        'declaringClassName' => 'App\\Listeners\\Org\\RitualPhaseOutputCaptureListener',
        'implementingClassName' => 'App\\Listeners\\Org\\RitualPhaseOutputCaptureListener',
        'currentClassName' => 'App\\Listeners\\Org\\RitualPhaseOutputCaptureListener',
        'aliasName' => NULL,
      ),
      'collectAgentOutput' => 
      array (
        'name' => 'collectAgentOutput',
        'parameters' => 
        array (
          'agentJobRunId' => 
          array (
            'name' => 'agentJobRunId',
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
                      'name' => 'int',
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
            'startLine' => 79,
            'endLine' => 79,
            'startColumn' => 41,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Collect stdout events from agent_run_events for the given run,
 * concatenated and truncated to MAX_OUTPUT_BYTES from the tail.
 */',
        'startLine' => 79,
        'endLine' => 96,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Listeners\\Org',
        'declaringClassName' => 'App\\Listeners\\Org\\RitualPhaseOutputCaptureListener',
        'implementingClassName' => 'App\\Listeners\\Org\\RitualPhaseOutputCaptureListener',
        'currentClassName' => 'App\\Listeners\\Org\\RitualPhaseOutputCaptureListener',
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