<?php declare(strict_types = 1);

// odsl-/Users/garethdaine/Code/agent/app/Services/Skills/Validation/Analyzers/ExfiltrationAnalyzer.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Services\Skills\Validation\Analyzers\ExfiltrationAnalyzer
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.4.18-622029b1f8e314f570001978baca51c6676ad7ae2d804e7383d5ec1aca39bccb',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Services\\Skills\\Validation\\Analyzers\\ExfiltrationAnalyzer',
        'filename' => '/Users/garethdaine/Code/agent/app/Services/Skills/Validation/Analyzers/ExfiltrationAnalyzer.php',
      ),
    ),
    'namespace' => 'App\\Services\\Skills\\Validation\\Analyzers',
    'name' => 'App\\Services\\Skills\\Validation\\Analyzers\\ExfiltrationAnalyzer',
    'shortName' => 'ExfiltrationAnalyzer',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 96,
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
      'URL_CONSTRUCTION_PATTERNS' => 
      array (
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\Analyzers\\ExfiltrationAnalyzer',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\Analyzers\\ExfiltrationAnalyzer',
        'name' => 'URL_CONSTRUCTION_PATTERNS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'/\\$\\w+\\s*\\.\\s*["\\\']https?:/\', \'/["\\\']https?:\\/\\/["\\\']\\s*\\.\\s*\\$/\', \'/url\\s*=\\s*["\\\'].*\\$/\', \'/f["\\\']https?:\\/\\/\\{/\', \'/`https?:\\/\\/\\$\\{/\']',
          'attributes' => 
          array (
            'startLine' => 12,
            'endLine' => 18,
            'startTokenPos' => 39,
            'startFilePos' => 225,
            'endTokenPos' => 56,
            'endFilePos' => 411,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 12,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'NETWORK_CALL_PATTERNS' => 
      array (
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\Analyzers\\ExfiltrationAnalyzer',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\Analyzers\\ExfiltrationAnalyzer',
        'name' => 'NETWORK_CALL_PATTERNS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'/\\bfetch\\s*\\(/\', \'/\\bcurl_exec\\s*\\(/\', \'/\\bcurl_init\\s*\\(/\', \'/\\bwget\\b/\', \'/\\brequests\\.(get|post|put|delete|patch)\\s*\\(/\', \'/\\burllib\\.request/\', \'/\\bhttp\\.client/\', \'/\\bfile_get_contents\\s*\\(\\s*["\\\']https?:/\', \'/\\bfopen\\s*\\(\\s*["\\\']https?:/\']',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 30,
            'startTokenPos' => 67,
            'startFilePos' => 457,
            'endTokenPos' => 96,
            'endFilePos' => 782,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'WEBHOOK_PATTERNS' => 
      array (
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\Analyzers\\ExfiltrationAnalyzer',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\Analyzers\\ExfiltrationAnalyzer',
        'name' => 'WEBHOOK_PATTERNS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'/webhook\\.site/i\', \'/requestbin/i\', \'/ngrok\\.io/i\', \'/burpcollaborator/i\', \'/oastify\\.com/i\', \'/pipedream/i\', \'/hookbin/i\']',
          'attributes' => 
          array (
            'startLine' => 32,
            'endLine' => 40,
            'startTokenPos' => 107,
            'startFilePos' => 823,
            'endTokenPos' => 130,
            'endFilePos' => 1010,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'ENCODE_TRANSMIT_PATTERNS' => 
      array (
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\Analyzers\\ExfiltrationAnalyzer',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\Analyzers\\ExfiltrationAnalyzer',
        'name' => 'ENCODE_TRANSMIT_PATTERNS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'/base64_encode.*(?:curl|fetch|http|request|post)/is\', \'/btoa.*(?:fetch|XMLHttpRequest|axios)/is\', \'/encode.*(?:send|transmit|post|upload)/is\']',
          'attributes' => 
          array (
            'startLine' => 42,
            'endLine' => 46,
            'startTokenPos' => 141,
            'startFilePos' => 1059,
            'endTokenPos' => 152,
            'endFilePos' => 1233,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 42,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'analyze' => 
      array (
        'name' => 'analyze',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 48,
            'endLine' => 48,
            'startColumn' => 29,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'extractedPath' => 
          array (
            'name' => 'extractedPath',
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
            'startLine' => 48,
            'endLine' => 48,
            'startColumn' => 46,
            'endColumn' => 66,
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
            'name' => 'float',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 48,
        'endLine' => 95,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Services\\Skills\\Validation\\Analyzers',
        'declaringClassName' => 'App\\Services\\Skills\\Validation\\Analyzers\\ExfiltrationAnalyzer',
        'implementingClassName' => 'App\\Services\\Skills\\Validation\\Analyzers\\ExfiltrationAnalyzer',
        'currentClassName' => 'App\\Services\\Skills\\Validation\\Analyzers\\ExfiltrationAnalyzer',
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