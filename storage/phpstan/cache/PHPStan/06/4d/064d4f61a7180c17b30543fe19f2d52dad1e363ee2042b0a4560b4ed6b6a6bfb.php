<?php declare(strict_types = 1);

// ftm-/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v4-2.3.2',
   'data' => 
  array (
    0 => 
    array (
      '2ff4d5931343c2740c133fb115bf530b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'conversationreconstructor' => 'App\\Support\\Interrogation\\ConversationReconstructor',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'summaryopenquestionqueueservice' => 'App\\Support\\Interrogation\\SummaryOpenQuestionQueueService',
          'summarypayloadnormalizer' => 'App\\Support\\Interrogation\\SummaryPayloadNormalizer',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '371491d1ffe33490f7d695a845ff7ae8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Queue',
         'uses' => 
        array (
          'queueablebybus' => 'Illuminate\\Bus\\Queueable',
          'dispatchable' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          'interactswithqueue' => 'Illuminate\\Queue\\InteractsWithQueue',
          'serializesmodels' => 'Illuminate\\Queue\\SerializesModels',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Queue\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Foundation\\Queue\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'b45e4e2e6664b28555c7366e562d7231' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'b567abaf8a84f8441b1dd82ed4873f73' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'dispatch',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '5213ec25c00c31e13646e2c44b559892' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'dispatchIf',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '140cf0f35fe2a16d527346605a923999' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'dispatchUnless',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'cec258e922c52362ebf176d3660ad228' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'dispatchSync',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '69506509e61288c5c5d06462adbd11a7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'dispatchAfterResponse',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '49faa63243dbf047faf9d6be3ed0a67e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'withChain',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '15a8589e23aab424c4597a9d8643a626' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'newPendingDispatch',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'ef796a47bdabd48b955b08978f30205a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '7c69f7f5858354d89f8e12128c1ef597' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Support\\InteractsWithTime',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '1d01f94b0403af6d3c2cb8ebeb512522' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'secondsUntil',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Support\\InteractsWithTime',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '1c563fbde048198624f9d489c3be6c98' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'availableAt',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Support\\InteractsWithTime',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      'ed7b474c6def685536f3c21fe02d1207' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'parseDateInterval',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Support\\InteractsWithTime',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '83831a2dd92c7f37eaf9d86378961a3a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'currentTime',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Support\\InteractsWithTime',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      'efbbdde8d5c5a2b6c8b13666f72aee25' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'runTimeForHumans',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Support\\InteractsWithTime',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '230e59b6876035f871605047d8d233b8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'attempts',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '59801792fcd778e9cbe004ebd032bae9' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'delete',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '290ddb59ac10726a3a02c3461738a17d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'fail',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '270fc9cb782c120e5d5dc80e92e24e92' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'release',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '0278b3c7c56a2af82e2a167a6abd97b7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'withFakeQueueInteractions',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '5df1c7ff3bae1ac3c7d498796dda42c0' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'assertDeleted',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '290cbab893038f19565ea50379e33cc0' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'assertNotDeleted',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '2bbf77aaf39c6799a7d7c876cd2cacb4' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'assertFailed',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'd2e2ab4e7761e827bfb627910accde3a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'assertFailedWith',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'e974ab6de5ecf407132289b920328259' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'assertNotFailed',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '18a5e729f3e1412c38b1fccf78f25623' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'assertReleased',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'b09c3e4c1f3affb911eebed9d56efeb8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'assertNotReleased',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '2b889cc7ee8e8b63d6485c600bf4ba12' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'ensureQueueInteractionsHaveBeenFaked',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '21e481fe69c5f29c4984b9b568fab968' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'datetimeinterface' => 'DateTimeInterface',
          'jobcontract' => 'Illuminate\\Contracts\\Queue\\Job',
          'fakejob' => 'Illuminate\\Queue\\Jobs\\FakeJob',
          'interactswithtime' => 'Illuminate\\Support\\InteractsWithTime',
          'invalidargumentexception' => 'InvalidArgumentException',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'setJob',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\InteractsWithQueue',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '27579d3444f5b4621261ccc5564c8815' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'eeafed53806ec98b52ac17fbcf99cfb0' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'onConnection',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '12da33d4deb88503e05669871e870d9d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'onQueue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '983667c66772c18faec3a923271ad88b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'onGroup',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '74727bf6e8ca4e535bfda032e8adbdcd' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'withDeduplicator',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'd387698b2b6be38b0663871c2943186d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'allOnConnection',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '5b8979d1b550082605bdce51cea1ebae' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'allOnQueue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '0214a7b0798ee7a2d98ab6f9f70f867d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'delay',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'cb7c4daa21d9870d476bb9684040053b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'withoutDelay',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '2963ced20b29f80e98667d3d4f21ede7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'afterCommit',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'beee6ba924b5c4414af67317cfccd014' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'beforeCommit',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '2bcba22804bad8db02f5c32aa2f11498' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'through',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '8f75ed1cce935af04bd742979c8d1e9f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'chain',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '02806668b9cfd15852fb39f5d734d8ef' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'prependToChain',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'b75e6ec74322ca80c223f0b475a7eef5' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'appendToChain',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '2bd91406a88e831f9608451d5de42686' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'serializeJob',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '444b7b9c9dc40ec2b20d15d3b58d9734' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'dispatchNextJobInChain',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '3d90367cd137b4bd463f60277d9e9ae7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'invokeChainCatchCallbacks',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'fb06b7b6571e82d364fa424dfe8330d6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'assertHasChain',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '16db643062e4664a68ad8f94ffa81ce9' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'callqueuedclosure' => 'Illuminate\\Queue\\CallQueuedClosure',
          'arr' => 'Illuminate\\Support\\Arr',
          'collection' => 'Illuminate\\Support\\Collection',
          'serializableclosure' => 'Laravel\\SerializableClosure\\SerializableClosure',
          'phpunit' => 'PHPUnit\\Framework\\Assert',
          'runtimeexception' => 'RuntimeException',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'assertDoesntHaveChain',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Bus\\Queueable',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'ea80dbe3914c5b788ba52a01493e53e2' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesModels',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'a6c25d620daf2e89d47c10784ef6b7d3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'modelidentifier' => 'Illuminate\\Contracts\\Database\\ModelIdentifier',
          'queueablecollection' => 'Illuminate\\Contracts\\Queue\\QueueableCollection',
          'queueableentity' => 'Illuminate\\Contracts\\Queue\\QueueableEntity',
          'eloquentcollection' => 'Illuminate\\Database\\Eloquent\\Collection',
          'aspivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Concerns\\AsPivot',
          'pivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Pivot',
          'collection' => 'Illuminate\\Support\\Collection',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '48892c2909ec5bfdfa64a219b26a02da' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'modelidentifier' => 'Illuminate\\Contracts\\Database\\ModelIdentifier',
          'queueablecollection' => 'Illuminate\\Contracts\\Queue\\QueueableCollection',
          'queueableentity' => 'Illuminate\\Contracts\\Queue\\QueueableEntity',
          'eloquentcollection' => 'Illuminate\\Database\\Eloquent\\Collection',
          'aspivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Concerns\\AsPivot',
          'pivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Pivot',
          'collection' => 'Illuminate\\Support\\Collection',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'getSerializedPropertyValue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '6b493bdc8ac9714e4cda5bc8643ee0bc' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'modelidentifier' => 'Illuminate\\Contracts\\Database\\ModelIdentifier',
          'queueablecollection' => 'Illuminate\\Contracts\\Queue\\QueueableCollection',
          'queueableentity' => 'Illuminate\\Contracts\\Queue\\QueueableEntity',
          'eloquentcollection' => 'Illuminate\\Database\\Eloquent\\Collection',
          'aspivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Concerns\\AsPivot',
          'pivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Pivot',
          'collection' => 'Illuminate\\Support\\Collection',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'getRestoredPropertyValue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '46c3c0a177adba928cfb5b862b01804c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'modelidentifier' => 'Illuminate\\Contracts\\Database\\ModelIdentifier',
          'queueablecollection' => 'Illuminate\\Contracts\\Queue\\QueueableCollection',
          'queueableentity' => 'Illuminate\\Contracts\\Queue\\QueueableEntity',
          'eloquentcollection' => 'Illuminate\\Database\\Eloquent\\Collection',
          'aspivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Concerns\\AsPivot',
          'pivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Pivot',
          'collection' => 'Illuminate\\Support\\Collection',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'restoreCollection',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      'a5165ac33103d03218f5a65a98ab3973' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'modelidentifier' => 'Illuminate\\Contracts\\Database\\ModelIdentifier',
          'queueablecollection' => 'Illuminate\\Contracts\\Queue\\QueueableCollection',
          'queueableentity' => 'Illuminate\\Contracts\\Queue\\QueueableEntity',
          'eloquentcollection' => 'Illuminate\\Database\\Eloquent\\Collection',
          'aspivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Concerns\\AsPivot',
          'pivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Pivot',
          'collection' => 'Illuminate\\Support\\Collection',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'restoreModel',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      'ffc74958896454d85f87c636e3045db9' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'modelidentifier' => 'Illuminate\\Contracts\\Database\\ModelIdentifier',
          'queueablecollection' => 'Illuminate\\Contracts\\Queue\\QueueableCollection',
          'queueableentity' => 'Illuminate\\Contracts\\Queue\\QueueableEntity',
          'eloquentcollection' => 'Illuminate\\Database\\Eloquent\\Collection',
          'aspivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Concerns\\AsPivot',
          'pivot' => 'Illuminate\\Database\\Eloquent\\Relations\\Pivot',
          'collection' => 'Illuminate\\Support\\Collection',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'getQueryForModelRestoration',
         'templatePhpDocNodes' => 
        array (
          'TModel' => 
          array (
            0 => '@template',
            1 => 
            \PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode::__set_state(array(
               'name' => 'TModel',
               'bound' => 
              \PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode::__set_state(array(
                 'name' => '\\Illuminate\\Database\\Eloquent\\Model',
                 'attributes' => 
                array (
                  'startLine' => 4,
                  'endLine' => 4,
                ),
              )),
               'default' => NULL,
               'lowerBound' => NULL,
               'description' => '',
               'attributes' => 
              array (
                'startLine' => 4,
                'endLine' => 4,
              ),
            )),
          ),
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      'ff278fc4fe0283214145083ce5cebe50' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => '__serialize',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesModels',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'b1bdba623b8600ab27cb794835ad294c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => '__unserialize',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesModels',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'ef97cb273c8b11480efef519b4fc083c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'getPropertyValue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => 'Illuminate\\Queue\\SerializesModels',
         'traitData' => 
        array (
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'cffcf858ae94180b91af2fcf4c240e89' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'conversationreconstructor' => 'App\\Support\\Interrogation\\ConversationReconstructor',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'summaryopenquestionqueueservice' => 'App\\Support\\Interrogation\\SummaryOpenQuestionQueueService',
          'summarypayloadnormalizer' => 'App\\Support\\Interrogation\\SummaryPayloadNormalizer',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'a6623cc3238fe27188e6d78ee9115077' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'conversationreconstructor' => 'App\\Support\\Interrogation\\ConversationReconstructor',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'summaryopenquestionqueueservice' => 'App\\Support\\Interrogation\\SummaryOpenQuestionQueueService',
          'summarypayloadnormalizer' => 'App\\Support\\Interrogation\\SummaryPayloadNormalizer',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'handle',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '3ef34428d4c6193efa3a9e4481295fbd' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'conversationreconstructor' => 'App\\Support\\Interrogation\\ConversationReconstructor',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'summaryopenquestionqueueservice' => 'App\\Support\\Interrogation\\SummaryOpenQuestionQueueService',
          'summarypayloadnormalizer' => 'App\\Support\\Interrogation\\SummaryPayloadNormalizer',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'autoReinterrogateIfNeeded',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '4bc9e06f13eafc5031492b9816734758' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'conversationreconstructor' => 'App\\Support\\Interrogation\\ConversationReconstructor',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'summaryopenquestionqueueservice' => 'App\\Support\\Interrogation\\SummaryOpenQuestionQueueService',
          'summarypayloadnormalizer' => 'App\\Support\\Interrogation\\SummaryPayloadNormalizer',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'summaryContextBlock',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'ec100b7b2a8d056788a3c24a4a044f27' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'conversationreconstructor' => 'App\\Support\\Interrogation\\ConversationReconstructor',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'summaryopenquestionqueueservice' => 'App\\Support\\Interrogation\\SummaryOpenQuestionQueueService',
          'summarypayloadnormalizer' => 'App\\Support\\Interrogation\\SummaryPayloadNormalizer',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'validateRevisionOutput',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'b073651522f647491244e22263658cf1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'conversationreconstructor' => 'App\\Support\\Interrogation\\ConversationReconstructor',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'summaryopenquestionqueueservice' => 'App\\Support\\Interrogation\\SummaryOpenQuestionQueueService',
          'summarypayloadnormalizer' => 'App\\Support\\Interrogation\\SummaryPayloadNormalizer',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'buildRevisionStabilityPrompt',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '9674777df73d9b5bc73ba984037b43db' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'conversationreconstructor' => 'App\\Support\\Interrogation\\ConversationReconstructor',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'summaryopenquestionqueueservice' => 'App\\Support\\Interrogation\\SummaryOpenQuestionQueueService',
          'summarypayloadnormalizer' => 'App\\Support\\Interrogation\\SummaryPayloadNormalizer',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'preserveUnrequestedRevisionFields',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'e6b07bdf8cc303c4eb817e532e68505d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'conversationreconstructor' => 'App\\Support\\Interrogation\\ConversationReconstructor',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'summaryopenquestionqueueservice' => 'App\\Support\\Interrogation\\SummaryOpenQuestionQueueService',
          'summarypayloadnormalizer' => 'App\\Support\\Interrogation\\SummaryPayloadNormalizer',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'revisionMentionsPrivateNotes',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '2c2f5613859dd07b697b4e4ff11b3ccc' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'conversationreconstructor' => 'App\\Support\\Interrogation\\ConversationReconstructor',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'summaryopenquestionqueueservice' => 'App\\Support\\Interrogation\\SummaryOpenQuestionQueueService',
          'summarypayloadnormalizer' => 'App\\Support\\Interrogation\\SummaryPayloadNormalizer',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'normalizeStringList',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '8241f2ba641b8fabd9bd2c0e46ad554b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'conversationreconstructor' => 'App\\Support\\Interrogation\\ConversationReconstructor',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'summaryopenquestionqueueservice' => 'App\\Support\\Interrogation\\SummaryOpenQuestionQueueService',
          'summarypayloadnormalizer' => 'App\\Support\\Interrogation\\SummaryPayloadNormalizer',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'runSummaryProcess',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'd84a1983ec8087f413b7a56fc14ac049' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'conversationreconstructor' => 'App\\Support\\Interrogation\\ConversationReconstructor',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'summaryopenquestionqueueservice' => 'App\\Support\\Interrogation\\SummaryOpenQuestionQueueService',
          'summarypayloadnormalizer' => 'App\\Support\\Interrogation\\SummaryPayloadNormalizer',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'runAdversarialReview',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '3596e550c047fb60a291bd7c39c6eca4' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'conversationreconstructor' => 'App\\Support\\Interrogation\\ConversationReconstructor',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'summaryopenquestionqueueservice' => 'App\\Support\\Interrogation\\SummaryOpenQuestionQueueService',
          'summarypayloadnormalizer' => 'App\\Support\\Interrogation\\SummaryPayloadNormalizer',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'hasCriticalIssue',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '6ff3cc2e60b51217e623f59136e87859' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'conversationreconstructor' => 'App\\Support\\Interrogation\\ConversationReconstructor',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'summaryopenquestionqueueservice' => 'App\\Support\\Interrogation\\SummaryOpenQuestionQueueService',
          'summarypayloadnormalizer' => 'App\\Support\\Interrogation\\SummaryPayloadNormalizer',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationSummaryJob',
         'functionName' => 'insertClarificationQuestions',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
    ),
    1 => 
    array (
      '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationSummaryJob.php' => 'ee7c2d48352482aa580ca27168735009eb1c40b9077a1b2c771734394c7a61d9',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Foundation/Queue/Queueable.php' => '3f02abd5d38d7cf07e64a46b9cc5e578004e5ff10401432f683ef354bc8f3419',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Foundation/Bus/Dispatchable.php' => '551294291775e57fbd590f0ed288a91cca683d42fac08e60c87e39b73617d47b',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Queue/InteractsWithQueue.php' => '8d300c3adb967aa56c0827ba587e456e32e40fbb1c0d9f649f6bf7c0d876e937',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Support/InteractsWithTime.php' => 'ee4ef3a2e714fa539b223287a3a62b618b1d3a9e44f2e1f92981f2c3e2773ad5',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Bus/Queueable.php' => '7df8b51aab8bd3196229be1a8e398c2c2ec636ae1767ce499a64bfdbf5675c47',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Queue/SerializesModels.php' => '29ff50de875925956c56b217ef9b78643cef0e12e23885fdf37e0ed9b697e51d',
      '/Users/garethdaine/Code/agent/vendor/composer/../laravel/framework/src/Illuminate/Queue/SerializesAndRestoresModelIdentifiers.php' => 'd4cb97259a134d2089c54c969c2176704f0df2fa2483f149b61029c5c993f82d',
    ),
  ),
));