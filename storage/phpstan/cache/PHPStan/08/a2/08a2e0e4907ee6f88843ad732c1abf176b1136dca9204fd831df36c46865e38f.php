<?php declare(strict_types = 1);

// osfsl-/Users/garethdaine/Code/agent/vendor/composer/../yethee/tiktoken/src/EncoderProvider.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Yethee\Tiktoken\EncoderProvider
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-8faf272f20046856671d4e258de87186f72d188d8a17c3d8d15e76031dfac648-8.4.18-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Yethee\\Tiktoken\\EncoderProvider',
        'filename' => '/Users/garethdaine/Code/agent/vendor/composer/../yethee/tiktoken/src/EncoderProvider.php',
      ),
    ),
    'namespace' => 'Yethee\\Tiktoken',
    'name' => 'Yethee\\Tiktoken\\EncoderProvider',
    'shortName' => 'EncoderProvider',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 26,
    'endLine' => 223,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Symfony\\Contracts\\Service\\ResetInterface',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'ENCODINGS' => 
      array (
        'declaringClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'implementingClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'name' => 'ENCODINGS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'r50k_base\' => [\'vocab\' => \'https://openaipublic.blob.core.windows.net/encodings/r50k_base.tiktoken\', \'hash\' => \'306cd27f03c1a714eca7108e03d66b7dc042abe8c258b44c199a7ed9838dd930\', \'pat\' => \'\\\'s|\\\'t|\\\'re|\\\'ve|\\\'m|\\\'ll|\\\'d| ?\\p{L}+| ?\\p{N}+| ?[^\\s\\p{L}\\p{N}]+|\\s+(?!\\S)|\\s+\'], \'p50k_base\' => [\'vocab\' => \'https://openaipublic.blob.core.windows.net/encodings/p50k_base.tiktoken\', \'hash\' => \'94b5ca7dff4d00767bc256fdd1b27e5b17361d7b8a5f968547f9f23eb70d2069\', \'pat\' => \'\\\'s|\\\'t|\\\'re|\\\'ve|\\\'m|\\\'ll|\\\'d| ?\\p{L}+| ?\\p{N}+| ?[^\\s\\p{L}\\p{N}]+|\\s+(?!\\S)|\\s+\'], \'p50k_edit\' => [\'vocab\' => \'https://openaipublic.blob.core.windows.net/encodings/p50k_base.tiktoken\', \'hash\' => \'94b5ca7dff4d00767bc256fdd1b27e5b17361d7b8a5f968547f9f23eb70d2069\', \'pat\' => \'\\\'s|\\\'t|\\\'re|\\\'ve|\\\'m|\\\'ll|\\\'d| ?\\p{L}+| ?\\p{N}+| ?[^\\s\\p{L}\\p{N}]+|\\s+(?!\\S)|\\s+\'], \'cl100k_base\' => [\'vocab\' => \'https://openaipublic.blob.core.windows.net/encodings/cl100k_base.tiktoken\', \'hash\' => \'223921b76ee99bde995b7ff738513eef100fb51d18c93597a113bcffe865b2a7\', \'pat\' => \'(?i:\\\'s|\\\'t|\\\'re|\\\'ve|\\\'m|\\\'ll|\\\'d)|[^\\r\\n\\p{L}\\p{N}]?\\p{L}+|\\p{N}{1,3}| ?[^\\s\\p{L}\\p{N}]+[\\r\\n]*|\\s*[\\r\\n]+|\\s+(?!\\S)|\\s+\'], \'o200k_base\' => [\'vocab\' => \'https://openaipublic.blob.core.windows.net/encodings/o200k_base.tiktoken\', \'hash\' => \'446a9538cb6c348e3516120d7c08b09f57c36495e2acfffe59a5bf8b0cfb1a2d\', \'pat\' => \'[^\\r\\n\\p{L}\\p{N}]?[\\p{Lu}\\p{Lt}\\p{Lm}\\p{Lo}\\p{M}]*[\\p{Ll}\\p{Lm}\\p{Lo}\\p{M}]+(?i:\\\'s|\\\'t|\\\'re|\\\'ve|\\\'m|\\\'ll|\\\'d)?|[^\\r\\n\\p{L}\\p{N}]?[\\p{Lu}\\p{Lt}\\p{Lm}\\p{Lo}\\p{M}]+[\\p{Ll}\\p{Lm}\\p{Lo}\\p{M}]*(?i:\\\'s|\\\'t|\\\'re|\\\'ve|\\\'m|\\\'ll|\\\'d)?|\\p{N}{1,3}| ?[^\\s\\p{L}\\p{N}]+[\\r\\n\\/]*|\\s*[\\r\\n]+|\\s+(?!\\S)|\\s+\']]',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 54,
            'startTokenPos' => 127,
            'startFilePos' => 640,
            'endTokenPos' => 279,
            'endFilePos' => 2551,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'MODEL_PREFIX_TO_ENCODING' => 
      array (
        'declaringClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'implementingClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'name' => 'MODEL_PREFIX_TO_ENCODING',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'o1-\' => \'o200k_base\', \'o3-\' => \'o200k_base\', \'o4-mini-\' => \'o200k_base\', \'chatgpt-4o-\' => \'o200k_base\', \'gpt-5-\' => \'o200k_base\', \'gpt-5.1-\' => \'o200k_base\', \'gpt-5.2-\' => \'o200k_base\', \'gpt-4-\' => \'cl100k_base\', \'gpt-4.1-\' => \'o200k_base\', \'gpt-4.5-\' => \'o200k_base\', \'gpt-4o-\' => \'o200k_base\', \'gpt-3.5-turbo-\' => \'cl100k_base\', \'gpt-oss-\' => \'o200k_base\']',
          'attributes' => 
          array (
            'startLine' => 55,
            'endLine' => 69,
            'startTokenPos' => 290,
            'startFilePos' => 2599,
            'endTokenPos' => 383,
            'endFilePos' => 3069,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 55,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'MODEL_TO_ENCODING' => 
      array (
        'declaringClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'implementingClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'name' => 'MODEL_TO_ENCODING',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'o1\' => \'o200k_base\', \'o3\' => \'o200k_base\', \'o4-mini\' => \'o200k_base\', \'gpt-5\' => \'o200k_base\', \'gpt-5.1\' => \'o200k_base\', \'gpt-5.2\' => \'o200k_base\', \'gpt-4\' => \'cl100k_base\', \'gpt-4.1\' => \'o200k_base\', \'gpt-4o\' => \'o200k_base\', \'gpt-3.5-turbo\' => \'cl100k_base\', \'gpt-3.5\' => \'cl100k_base\', \'davinci-002\' => \'cl100k_base\', \'babbage-002\' => \'cl100k_base\', \'text-embedding-ada-002\' => \'cl100k_base\', \'text-embedding-3-small\' => \'cl100k_base\', \'text-embedding-3-large\' => \'cl100k_base\', \'text-davinci-003\' => \'p50k_base\', \'text-davinci-002\' => \'p50k_base\', \'text-davinci-001\' => \'r50k_base\', \'text-curie-001\' => \'r50k_base\', \'text-babbage-001\' => \'r50k_base\', \'text-ada-001\' => \'r50k_base\', \'davinci\' => \'r50k_base\', \'curie\' => \'r50k_base\', \'babbage\' => \'r50k_base\', \'ada\' => \'r50k_base\', \'code-davinci-002\' => \'p50k_base\', \'code-davinci-001\' => \'p50k_base\', \'code-cushman-002\' => \'p50k_base\', \'code-cushman-001\' => \'p50k_base\', \'davinci-codex\' => \'p50k_base\', \'cushman-codex\' => \'p50k_base\', \'text-davinci-edit-001\' => \'p50k_edit\', \'code-davinci-edit-001\' => \'p50k_edit\', \'text-similarity-davinci-001\' => \'r50k_base\', \'text-similarity-curie-001\' => \'r50k_base\', \'text-similarity-babbage-001\' => \'r50k_base\', \'text-similarity-ada-001\' => \'r50k_base\', \'text-search-davinci-doc-001\' => \'r50k_base\', \'text-search-curie-doc-001\' => \'r50k_base\', \'text-search-babbage-doc-001\' => \'r50k_base\', \'text-search-ada-doc-001\' => \'r50k_base\', \'code-search-babbage-code-001\' => \'r50k_base\', \'code-search-ada-code-001\' => \'r50k_base\']',
          'attributes' => 
          array (
            'startLine' => 70,
            'endLine' => 115,
            'startTokenPos' => 394,
            'startFilePos' => 3110,
            'endTokenPos' => 704,
            'endFilePos' => 4984,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 70,
        'endLine' => 115,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'vocabLoader' => 
      array (
        'declaringClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'implementingClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'name' => 'vocabLoader',
        'modifiers' => 4,
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
                  'name' => 'Yethee\\Tiktoken\\Vocab\\VocabLoader',
                  'isIdentifier' => false,
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
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 117,
            'endLine' => 117,
            'startTokenPos' => 717,
            'startFilePos' => 5032,
            'endTokenPos' => 717,
            'endFilePos' => 5035,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 117,
        'endLine' => 117,
        'startColumn' => 5,
        'endColumn' => 49,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'vocabCacheDir' => 
      array (
        'declaringClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'implementingClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'name' => 'vocabCacheDir',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => '/** @var non-empty-string */',
        'attributes' => 
        array (
        ),
        'startLine' => 120,
        'endLine' => 120,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'encoders' => 
      array (
        'declaringClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'implementingClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'name' => 'encoders',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 123,
            'endLine' => 123,
            'startTokenPos' => 739,
            'startFilePos' => 5187,
            'endTokenPos' => 740,
            'endFilePos' => 5188,
          ),
        ),
        'docComment' => '/** @var array<non-empty-string, Encoder> */',
        'attributes' => 
        array (
        ),
        'startLine' => 123,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'vocabs' => 
      array (
        'declaringClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'implementingClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'name' => 'vocabs',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 126,
            'endLine' => 126,
            'startTokenPos' => 753,
            'startFilePos' => 5257,
            'endTokenPos' => 754,
            'endFilePos' => 5258,
          ),
        ),
        'docComment' => '/** @var array<string, Vocab> */',
        'attributes' => 
        array (
        ),
        'startLine' => 126,
        'endLine' => 126,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'useLib' => 
      array (
        'declaringClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'implementingClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'name' => 'useLib',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 128,
        'endLine' => 128,
        'startColumn' => 33,
        'endColumn' => 60,
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
          'useLib' => 
          array (
            'name' => 'useLib',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 128,
                'endLine' => 128,
                'startTokenPos' => 771,
                'startFilePos' => 5317,
                'endTokenPos' => 771,
                'endFilePos' => 5321,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 128,
            'endLine' => 128,
            'startColumn' => 33,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 128,
        'endLine' => 141,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Yethee\\Tiktoken',
        'declaringClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'implementingClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'currentClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'aliasName' => NULL,
      ),
      'getForModel' => 
      array (
        'name' => 'getForModel',
        'parameters' => 
        array (
          'model' => 
          array (
            'name' => 'model',
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
            'startLine' => 144,
            'endLine' => 144,
            'startColumn' => 33,
            'endColumn' => 45,
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
            'name' => 'Yethee\\Tiktoken\\Encoder',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @param non-empty-string $model */',
        'startLine' => 144,
        'endLine' => 157,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Yethee\\Tiktoken',
        'declaringClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'implementingClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'currentClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'aliasName' => NULL,
      ),
      'get' => 
      array (
        'name' => 'get',
        'parameters' => 
        array (
          'encodingName' => 
          array (
            'name' => 'encodingName',
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
            'startLine' => 160,
            'endLine' => 160,
            'startColumn' => 25,
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
            'name' => 'Yethee\\Tiktoken\\Encoder',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @param non-empty-string $encodingName */',
        'startLine' => 160,
        'endLine' => 181,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Yethee\\Tiktoken',
        'declaringClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'implementingClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'currentClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'aliasName' => NULL,
      ),
      'setVocabCache' => 
      array (
        'name' => 'setVocabCache',
        'parameters' => 
        array (
          'cacheDir' => 
          array (
            'name' => 'cacheDir',
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
            'startLine' => 184,
            'endLine' => 184,
            'startColumn' => 35,
            'endColumn' => 50,
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
        'docComment' => '/** @param non-empty-string $cacheDir */',
        'startLine' => 184,
        'endLine' => 188,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Yethee\\Tiktoken',
        'declaringClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'implementingClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'currentClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'aliasName' => NULL,
      ),
      'setVocabLoader' => 
      array (
        'name' => 'setVocabLoader',
        'parameters' => 
        array (
          'loader' => 
          array (
            'name' => 'loader',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Yethee\\Tiktoken\\Vocab\\VocabLoader',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 191,
            'endLine' => 191,
            'startColumn' => 36,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @psalm-api */',
        'startLine' => 191,
        'endLine' => 194,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Yethee\\Tiktoken',
        'declaringClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'implementingClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'currentClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'aliasName' => NULL,
      ),
      'reset' => 
      array (
        'name' => 'reset',
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
          0 => 
          array (
            'name' => 'Override',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => NULL,
        'startLine' => 196,
        'endLine' => 201,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Yethee\\Tiktoken',
        'declaringClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'implementingClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'currentClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'aliasName' => NULL,
      ),
      'getVocabLoader' => 
      array (
        'name' => 'getVocabLoader',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Yethee\\Tiktoken\\Vocab\\VocabLoader',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 203,
        'endLine' => 210,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Yethee\\Tiktoken',
        'declaringClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'implementingClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'currentClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'aliasName' => NULL,
      ),
      'getVocab' => 
      array (
        'name' => 'getVocab',
        'parameters' => 
        array (
          'encodingName' => 
          array (
            'name' => 'encodingName',
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
            'startLine' => 212,
            'endLine' => 212,
            'startColumn' => 31,
            'endColumn' => 50,
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
            'name' => 'Yethee\\Tiktoken\\Vocab\\Vocab',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 212,
        'endLine' => 222,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Yethee\\Tiktoken',
        'declaringClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'implementingClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
        'currentClassName' => 'Yethee\\Tiktoken\\EncoderProvider',
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