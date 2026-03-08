<?php declare(strict_types = 1);

// ftm-/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v4-2.3.2',
   'data' => 
  array (
    0 => 
    array (
      '1a35fb9aab1b9d4df3e13dea7cc9caac' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
      '3414ca6448f3393a349c87063c2c3ee6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Queue',
         'uses' => 
        array (
          'queueablebybus' => 'Illuminate\\Bus\\Queueable',
          'dispatchable' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          'interactswithqueue' => 'Illuminate\\Queue\\InteractsWithQueue',
          'serializesmodels' => 'Illuminate\\Queue\\SerializesModels',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Foundation\\Queue\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'ad29936217e086ce18dd3fe322610af8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '20267f33a9837dfbd561fb1e9446382f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'eecb077a0b81372bca241087e007ac0e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '3b78b488a5aea6cc3e4fb7829323818d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'fd628478b3bcf7c33ad64efdec48c44d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '902ac198ae0197ec88d2e553c2caae26' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '4576590514b9680533d5483d4f60c784' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '519a3259380934f997cded8566c0839b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '1b912005bd11667dcc7056e3c9246b3f' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '452955223b2f154004f2cfca1bc43cf5' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '4f5e7a4a8da1222beaae10605c00e940' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      'e165faf778162a7495ac69a09d6d9b4a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '0984d1be5c8ae1e7662a04aafc7fb972' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      'eda83c73cac576c444d109da8e17698b' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      'd918b64c6b4da1714159f272ae7377a1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '581a043978ff30eccbd1c67a9fff91fc' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '51c50ae7cccb8db22d73052978fed6ff' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'd4fa127c4a53ad16ee581419ad72eb3d' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '3cfb8ae55d6913ef33db46bcf37551ee' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'fa0474797b7e91a66062afc0e0ab419b' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'd0a176ae63288713a0fae245926d8f65' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'd8dadd38f755eb35b7fca644635d3335' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '94fd663c835d5a12eb73a3ec3caf1e78' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '3cf2e139334f3c5aa10151347d239963' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'e5bd352295401c16542f76b594bb7b37' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '79f795c41362fcefcd3bead98831de86' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '0e8f198261db3a293b872a821782fb39' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'a73528519ae796788b0504475b5d0e2b' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '91ea4ccf7d14376c78af61ee6b11bc5c' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '85672d4f2d336577159e6458fe0c6153' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '3250a15612addd4fcd93daf67a5d322d' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'ac3bfd877e4ee5da5ff22d13088204e3' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'd7643abc1476601bd1ab25d7b30c52cf' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'f03bfbd4dcaa4edcc475d57c327f7a3d' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '37ce240e6e5e6f1a94a8c12892e58b4b' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'd4122646025670847a4f0c75994fe565' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '4247aaf03dd940492112f50ca6e68bb1' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '9d110e388aa9939bd79894f47d7ed86e' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '0daf6bae4091a3f4e3866532f056723d' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'b421512a4af4d9683d502f720974952e' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'a5f04cd0cd4034e77dd43352f4a80425' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'fd1b9efae523b30aea50c7f510651ffb' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'b9143c9fc9bc7d70f965b5004c20e043' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '9819a80e48350f6b1c8e37a085eb6a55' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'e25e551ecb4d02df68959e0ae961df3e' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '25965fb72caad71ed4c35d56f5a88364' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '0cdcc34e56eef2e2d05c95b4fd4da677' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '24b314d3fd54234ed97a2db5c4a78805' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '27a53fdcc07557a5a13540aa0230ea9d' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'dcaa8e88b26096d9bdf432b02e598a40' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '13bc667adb23a1586ce56b1914a2ef92' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '22068b1d3b3426df5040171d1732f3ba' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '528eb0f149d0fdba3a1ef822c1989575' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '3844a14b9a02263d7b1b98801f440516' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      'e3648c12f0c1867519ad31b6983cdda3' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '2e1d705b1f1f373dfc2bb1efceb89488' => 
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
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '8df2ba370714121f9163d58f14032eb6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '79163f3d052287456699ca0391f39f7c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '976490f18735d8296324a3ffdea20e51' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php',
          1 => 'App\\Jobs\\ExecuteInterrogationPlanJob',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '1e38289fad4eec784565e8f1c7bea699' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
      '8c943dd20e73d5675637ce67aca69571' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
      'c1f0f2b313bfc2f89c28668a22b138f8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
         'functionName' => 'markRevisionState',
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
      '4f3789f1f5d2100b5bf5d9df4fe253ee' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
         'functionName' => 'markGenerationState',
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
      'a1c365d2e5b902c833379581a396f23f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
         'functionName' => 'runPlanProcess',
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
      'ff9c64d06f34d3e9afa4105bd5ab3a28' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
         'functionName' => 'buildPlanningPrompt',
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
      '1e285eb35d898f548d800fd95e902975' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
         'functionName' => 'planContextBlock',
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
      'be5ab26083542c5e86cfa71100011cf0' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
         'functionName' => 'validatePlanRevisionOutput',
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
      '74a2fccf752a5e438ab2322dcb558b45' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
         'functionName' => 'buildPlanRevisionStabilityRetryPrompt',
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
      '8268a3b214cd258077aa23f7051100bc' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
      '7c9c8c3bb58d7c2e7bcca0d88f0b61c3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
         'functionName' => 'metadataContextBlock',
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
      'e258e3a1dea5851eb84499b8fef95cf0' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
      'f37f99614802e5907a1c14825a9725e7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
         'functionName' => 'validateCodexPlanQuality',
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
      '5bdb4d871d839964e20164d492b67729' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
         'functionName' => 'countConcreteReferences',
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
      '674e841cc07591206f51e564e7198031' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
         'functionName' => 'buildCodexPlanQualityRetryPrompt',
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
      'a9ecf5057250e7a2832bb1b4d7e13ff6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
         'functionName' => 'buildPlanPayloadRetryPrompt',
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
      '71b08a0e9c7055509bb33a6dfcb07164' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
      'b5a9153a120f97b82ff40e35cdf1a111' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs',
         'uses' => 
        array (
          'interrogationbuildtask' => 'App\\Models\\InterrogationBuildTask',
          'interrogationsession' => 'App\\Models\\InterrogationSession',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'adapterfactory' => 'App\\Support\\Interrogation\\AdapterFactory',
          'adversarialreviewerservice' => 'App\\Support\\Interrogation\\AdversarialReviewerService',
          'interrogationrunneradapter' => 'App\\Support\\Interrogation\\Contracts\\InterrogationRunnerAdapter',
          'exportservice' => 'App\\Support\\Interrogation\\ExportService',
          'interrogationeventwriter' => 'App\\Support\\Interrogation\\InterrogationEventWriter',
          'planpayloadguard' => 'App\\Support\\Interrogation\\PlanPayloadGuard',
          'planpayloadnormalizer' => 'App\\Support\\Interrogation\\PlanPayloadNormalizer',
          'sessionstatetransitionservice' => 'App\\Support\\Interrogation\\SessionStateTransitionService',
          'systempromptresolver' => 'App\\Support\\Interrogation\\SystemPromptResolver',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'process' => 'Symfony\\Component\\Process\\Process',
        ),
         'className' => 'App\\Jobs\\ExecuteInterrogationPlanJob',
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
    ),
    1 => 
    array (
      '/Users/garethdaine/Code/agent/app/Jobs/ExecuteInterrogationPlanJob.php' => '284bd47c6fbc19814accfd242d612ca35c97af9331d67b63a468e5c444f4e1f9',
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