<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Support/NlOrg/NlOrgPromptBuilder.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Support\NlOrg\NlOrgPromptBuilder
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-bbb20e974c56ac4c6144dc54905ecda9b80cdf2983ef8b0b00065b9f201f3422',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'filename' => '/Users/garethdaine/Code/agent/app/Support/NlOrg/NlOrgPromptBuilder.php',
      ),
    ),
    'namespace' => 'App\\Support\\NlOrg',
    'name' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
    'shortName' => 'NlOrgPromptBuilder',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 7,
    'endLine' => 232,
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
      'build' => 
      array (
        'name' => 'build',
        'parameters' => 
        array (
          'nlInput' => 
          array (
            'name' => 'nlInput',
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
            'startLine' => 18,
            'endLine' => 18,
            'startColumn' => 27,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'currentOrgState' => 
          array (
            'name' => 'currentOrgState',
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
            'startLine' => 18,
            'endLine' => 18,
            'startColumn' => 44,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'chatHistory' => 
          array (
            'name' => 'chatHistory',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 18,
                'endLine' => 18,
                'startTokenPos' => 42,
                'startFilePos' => 594,
                'endTokenPos' => 43,
                'endFilePos' => 595,
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
            'startLine' => 18,
            'endLine' => 18,
            'startColumn' => 68,
            'endColumn' => 90,
            'parameterIndex' => 2,
            'isOptional' => true,
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
 * Build the complete LLM prompt for NL org parsing.
 *
 * @param  string  $nlInput  The user\'s natural language input
 * @param  array  $currentOrgState  Structured array of current org entities
 * @param  array  $chatHistory  Array of [\'role\' => \'user\'|\'assistant\', \'content\' => string]
 *
 * @throws NlOrgContextOverflowException
 */',
        'startLine' => 18,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\NlOrg',
        'declaringClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'implementingClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'currentClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'aliasName' => NULL,
      ),
      'estimateTokenCount' => 
      array (
        'name' => 'estimateTokenCount',
        'parameters' => 
        array (
          'prompt' => 
          array (
            'name' => 'prompt',
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
            'startLine' => 46,
            'endLine' => 46,
            'startColumn' => 40,
            'endColumn' => 53,
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
        'docComment' => NULL,
        'startLine' => 46,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\NlOrg',
        'declaringClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'implementingClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'currentClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'aliasName' => NULL,
      ),
      'getContextWindowBudget' => 
      array (
        'name' => 'getContextWindowBudget',
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
        'docComment' => NULL,
        'startLine' => 51,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Support\\NlOrg',
        'declaringClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'implementingClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'currentClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'aliasName' => NULL,
      ),
      'buildSystemInstruction' => 
      array (
        'name' => 'buildSystemInstruction',
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
        'startLine' => 58,
        'endLine' => 71,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\NlOrg',
        'declaringClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'implementingClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'currentClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'aliasName' => NULL,
      ),
      'buildOutputSchema' => 
      array (
        'name' => 'buildOutputSchema',
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
        'startLine' => 73,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\NlOrg',
        'declaringClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'implementingClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'currentClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'aliasName' => NULL,
      ),
      'buildOrgStateSection' => 
      array (
        'name' => 'buildOrgStateSection',
        'parameters' => 
        array (
          'currentOrgState' => 
          array (
            'name' => 'currentOrgState',
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
            'startLine' => 125,
            'endLine' => 125,
            'startColumn' => 43,
            'endColumn' => 64,
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
        'docComment' => NULL,
        'startLine' => 125,
        'endLine' => 134,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\NlOrg',
        'declaringClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'implementingClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'currentClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'aliasName' => NULL,
      ),
      'buildFewShotExamples' => 
      array (
        'name' => 'buildFewShotExamples',
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
        'startLine' => 136,
        'endLine' => 204,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\NlOrg',
        'declaringClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'implementingClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'currentClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'aliasName' => NULL,
      ),
      'buildChatHistorySection' => 
      array (
        'name' => 'buildChatHistorySection',
        'parameters' => 
        array (
          'chatHistory' => 
          array (
            'name' => 'chatHistory',
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
            'startLine' => 206,
            'endLine' => 206,
            'startColumn' => 46,
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
        'docComment' => NULL,
        'startLine' => 206,
        'endLine' => 226,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\NlOrg',
        'declaringClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'implementingClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'currentClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'aliasName' => NULL,
      ),
      'buildUserInputSection' => 
      array (
        'name' => 'buildUserInputSection',
        'parameters' => 
        array (
          'nlInput' => 
          array (
            'name' => 'nlInput',
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
            'startColumn' => 44,
            'endColumn' => 58,
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
        'docComment' => NULL,
        'startLine' => 228,
        'endLine' => 231,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Support\\NlOrg',
        'declaringClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'implementingClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
        'currentClassName' => 'App\\Support\\NlOrg\\NlOrgPromptBuilder',
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