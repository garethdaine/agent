<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/Delegation/Verification/AiCriticStep.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\Delegation\Verification\AiCriticStep
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-cf49b1be0c30836922b52f6d3323bb2b5b8de163a75f61e8b5e51b8df9c82543',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\Delegation\\Verification\\AiCriticStep',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/Delegation/Verification/AiCriticStep.php',
      ),
    ),
    'namespace' => 'App\\Support\\Delegation\\Verification',
    'name' => 'App\\Support\\Delegation\\Verification\\AiCriticStep',
    'shortName' => 'AiCriticStep',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Spawns an AI agent to review task output and provide verification.
 *
 * The AI critic step is asynchronous - it creates an AgentJobRun to execute
 * the review and returns \'pending\' immediately. When the review completes,
 * AiCriticCompletedJob processes the result and fires DelegationTaskVerified.
 *
 * Prompt layering:
 * - Base prompt: config(\'delegation.ai_critic_default_prompt_template\')
 * - Override: stepConfig[\'prompt_template\'] if provided
 *
 * Evidence parsing (hybrid format):
 * - Attempts to parse output as JSON with \'verdict\' and \'issues\' fields
 * - Falls back to raw text if JSON parsing fails or fields are missing
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 30,
    'endLine' => 159,
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
      'execute' => 
      array (
        'name' => 'execute',
        'parameters' => 
        array (
          'task' => 
          array (
            'name' => 'task',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\DelegationTask',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 44,
            'endLine' => 44,
            'startColumn' => 9,
            'endColumn' => 28,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'attempt' => 
          array (
            'name' => 'attempt',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\DelegationAttempt',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 45,
            'endLine' => 45,
            'startColumn' => 9,
            'endColumn' => 34,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'stepConfig' => 
          array (
            'name' => 'stepConfig',
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
            'startLine' => 46,
            'endLine' => 46,
            'startColumn' => 9,
            'endColumn' => 25,
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
            'name' => 'App\\Support\\Delegation\\Verification\\VerificationStepResult',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Execute the AI critic step.
 *
 * Creates an AgentJobRun for the AI review and dispatches the execution.
 * Returns pending immediately since review is asynchronous.
 *
 * @param  DelegationTask  $task  The task being verified
 * @param  DelegationAttempt  $attempt  The attempt being verified
 * @param  array  $stepConfig  Step configuration including optional \'prompt_template\'
 * @return VerificationStepResult Always returns pending
 */',
        'startLine' => 43,
        'endLine' => 107,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Delegation\\Verification',
        'declaringClassName' => 'App\\Support\\Delegation\\Verification\\AiCriticStep',
        'implementingClassName' => 'App\\Support\\Delegation\\Verification\\AiCriticStep',
        'currentClassName' => 'App\\Support\\Delegation\\Verification\\AiCriticStep',
        'aliasName' => NULL,
      ),
      'parseEvidence' => 
      array (
        'name' => 'parseEvidence',
        'parameters' => 
        array (
          'output' => 
          array (
            'name' => 'output',
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
            'startLine' => 118,
            'endLine' => 118,
            'startColumn' => 35,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Parse the AI critic output into structured evidence.
 *
 * Uses hybrid format: attempts JSON parsing with expected fields,
 * falls back to raw text if parsing fails.
 *
 * @param  string  $output  The raw output from the AI critic
 * @return array Parsed evidence array
 */',
        'startLine' => 118,
        'endLine' => 132,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\Delegation\\Verification',
        'declaringClassName' => 'App\\Support\\Delegation\\Verification\\AiCriticStep',
        'implementingClassName' => 'App\\Support\\Delegation\\Verification\\AiCriticStep',
        'currentClassName' => 'App\\Support\\Delegation\\Verification\\AiCriticStep',
        'aliasName' => NULL,
      ),
      'buildPrompt' => 
      array (
        'name' => 'buildPrompt',
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
            'startLine' => 138,
            'endLine' => 138,
            'startColumn' => 9,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'task' => 
          array (
            'name' => 'task',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\DelegationTask',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 139,
            'endLine' => 139,
            'startColumn' => 9,
            'endColumn' => 28,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'attempt' => 
          array (
            'name' => 'attempt',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Models\\DelegationAttempt',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 140,
            'endLine' => 140,
            'startColumn' => 9,
            'endColumn' => 34,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Build the review prompt by substituting task context.
 */',
        'startLine' => 137,
        'endLine' => 158,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\Delegation\\Verification',
        'declaringClassName' => 'App\\Support\\Delegation\\Verification\\AiCriticStep',
        'implementingClassName' => 'App\\Support\\Delegation\\Verification\\AiCriticStep',
        'currentClassName' => 'App\\Support\\Delegation\\Verification\\AiCriticStep',
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