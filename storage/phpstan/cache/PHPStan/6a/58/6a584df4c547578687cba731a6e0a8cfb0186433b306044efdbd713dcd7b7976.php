<?php declare(strict_types = 1);

// ftm-/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v4-2.3.2',
   'data' => 
  array (
    0 => 
    array (
      'b34547f90a23c442980b72f37bd37f0a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
      'f572695df02d667977bafb94b5c76013' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Queue',
         'uses' => 
        array (
          'queueablebybus' => 'Illuminate\\Bus\\Queueable',
          'dispatchable' => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          'interactswithqueue' => 'Illuminate\\Queue\\InteractsWithQueue',
          'serializesmodels' => 'Illuminate\\Queue\\SerializesModels',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Foundation\\Queue\\Queueable',
          3 => NULL,
          4 => NULL,
        ),
      )),
      'e95904220f4c394d2642e09df741fbc4' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '563dff1a096b2490c9b89cf148581a63' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '7af3f2adc3ea9c9d8f02df1f6e016ba5' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '2455047950dfa9f1bffcc79e590b4d36' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'a61170f21edd0367b974e208bab4499c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '56f4cd0c17ed5f2d6cfd9baeb99c4a03' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'b6017c8278a904acd6799741dd9e45fe' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '12b29eb21da1154b685202141adba9d3' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Foundation\\Bus',
         'uses' => 
        array (
          'closure' => 'Closure',
          'dispatcher' => 'Illuminate\\Contracts\\Bus\\Dispatcher',
          'fluent' => 'Illuminate\\Support\\Fluent',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Foundation\\Bus\\Dispatchable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'bb3307287697199c8dfebb6c85fd7570' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'c872930829723ec02b8d65e9e9353236' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      'a8cc0d2d718dfcff0609917959b96b46' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '11906e8137ead352a1ae7f36577c6c23' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '7092fb210f63a2c020dfbc996ec642ec' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '51bf41e6db9c53448ba2ffc2ca3c81ec' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '3435ad802091ac49fd8484b7bb66f9e6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Support',
         'uses' => 
        array (
          'carboninterval' => 'Carbon\\CarbonInterval',
          'dateinterval' => 'DateInterval',
          'datetimeinterface' => 'DateTimeInterface',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Support\\InteractsWithTime',
          3 => 'Illuminate\\Queue\\InteractsWithQueue',
          4 => NULL,
        ),
      )),
      '3e91ccdf7ff53a608b7b4edcb473d60f' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'ac5f1aa6c7782be78a6956a8127d2701' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '0f6aca08be1833c9bd76805539f2a406' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '67fb338d464cdf62dbfef72ebe9a9759' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '4549fd8c0695a8de7f321931f5d316c9' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'df2f341bac5bb45c0954f3129cf26752' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '98e34411e89d75292ed1799da7af02b6' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '5df387bfba92aaf8948b35c2b2014d66' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '9e04c10e64466923e34d619a6d3265b5' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '3a1878f4540497f42f91aca53b3c0806' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '05c959d67814fd6c1c67f6949138066a' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '21440e81a33801a361962309beb86d9d' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '53eaefc36ebb0db64d9416b0d75ec1ff' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'b87cc61cb57694849414c429bb3c80df' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\InteractsWithQueue',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'fba5cdd0f814a8e1155eeae26f618589' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '64febbbf4293771d76dd143961b1bf2c' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '2d9d5ee3e8f88cb9a772e2a105315de0' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '4d7e0f1f2d13f98b4a13da2e4b93d458' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'c693c68fa6ac2e2733a99436489ee276' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'f92eedf6866583bf49b9c6d729b340a2' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '7bc959af70f9cc392474dbb27dedef3d' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'c386a1397774b4fe354d4652520ce411' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '2c1167deed8dab01af912ca4f01f2ee0' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '8c5a85aada7982ada036dbbb8ea2fdf5' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '8b7925e3c7ae78863dff54d31bd5cc0c' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '9d9c0963d1696f45338932d93013d2c1' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '7adda3ebb0aade80e2c043e9f82ae56b' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '63615db47177fab0f7b2894ea88efb1a' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'cb2962e08179db732d73f09df5560783' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'c7b56f0a36170392ec9323af20d46296' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '00d993b013055eee00f9a3d3565848c0' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '6a728d6f9eecf9bfb66ad766601b14af' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'dca211d8aa27f6663b598a155dc6a8fc' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '07d82782b02f494a0877c78e6d845081' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Bus\\Queueable',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'ebded4e2c8a51d310c6b1f3d869417e7' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      '265e5728a22238c4a7de2c9457747f9d' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      'cd988f368979f211221f7b4a8a3b9fb9' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      'a6f252a47d7b58981980368c1d7250ae' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '4d367ad581c9f0524990a4734f9599d2' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      '438f366d6bd02ed1364c55c02bcbe18f' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      'e42df968f5459251eb045f892a1b903c' => 
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
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\SerializesAndRestoresModelIdentifiers',
          3 => 'Illuminate\\Queue\\SerializesModels',
          4 => NULL,
        ),
      )),
      'b0a07095a6f962090e8e100f0bc70663' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'e5706923c332fb7becff75d580a411d9' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'd10176abe5338f4f1f99944e5a088131' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Queue',
         'uses' => 
        array (
          'withoutrelations' => 'Illuminate\\Queue\\Attributes\\WithoutRelations',
          'reflectionclass' => 'ReflectionClass',
          'reflectionproperty' => 'ReflectionProperty',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
          0 => '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php',
          1 => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
          2 => 'Illuminate\\Queue\\SerializesModels',
          3 => 'Illuminate\\Foundation\\Queue\\Queueable',
          4 => NULL,
        ),
      )),
      'bfb21e9e6ed2d0c0c47fef8044ee3686' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
      'bbfbe36290d35d8cfdddacd8e616eddf' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
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
      'a78085fe3520a1c0843c635b8686f017' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'failed',
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
      '48a1f1f32dee0f55440026cf6ae978f1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'pauseForDriftIfNeeded',
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
      'aed2f26db24aa998ed3187f6a9a982d9' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'driftChangedOnlyInToleratedPaths',
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
      'c39e8cc22477aee12e90ef4f12125ea1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'manifestFileHashes',
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
      '2ca2e7c037966432589f583b4cb628e1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'driftToleratedPaths',
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
      '37fd76d6ae1969965f58d090ebf034da' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'normalizeRelativePathRule',
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
      '3cfe19f0313a397139fd12567ae0a860' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'pathMatchesAnyRule',
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
      '8a157203a712a9dd3a032f2e5d422393' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'recoverStaleRunningTasks',
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
      '2fd5136a57d9fcddf656edae453644a1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'nextExecutableTask',
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
      '055416dbc4e4b7c971d6c3469eb092b1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'handleRetryableFailure',
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
      '092972ffc2ab271fab827f5f03e2196c' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'handleNonRetryableFailure',
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
      '69d3adfe1784bdf0a4b778bed9fee266' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'latestRunningTask',
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
      'c1dcbe793cb86d4940bf30e8420221ab' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'queueFailureCode',
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
      '2b5536a2bac139ed7f9ba0e80460c0fe' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'queueFailureSummary',
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
      '733bc835c0411b2e5f2a7a832f399287' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'resolveQueueTimeoutSeconds',
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
      '7ed15f39d547bdfc2980466b6ecf17f5' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'handleTaskTimeoutFailure',
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
      '6a4b141392ac62d4ce53a1a8b7c0779d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Jobs\\RepoAnalysis',
         'uses' => 
        array (
          'lessonextractionjob' => 'App\\Jobs\\Compliance\\LessonExtractionJob',
          'repoanalysisartifact' => 'App\\Models\\RepoAnalysisArtifact',
          'repoanalysissession' => 'App\\Models\\RepoAnalysisSession',
          'repoanalysistask' => 'App\\Models\\RepoAnalysisTask',
          'featureflagmanager' => 'App\\Support\\Agent\\FeatureFlagManager',
          'analyzerregistry' => 'App\\Support\\RepoAnalysis\\Analyzers\\AnalyzerRegistry',
          'aitaskrunner' => 'App\\Support\\RepoAnalysis\\AiTaskRunner',
          'eventwriter' => 'App\\Support\\RepoAnalysis\\EventWriter',
          'repoanalysisexecutionorchestrator' => 'App\\Support\\RepoAnalysis\\RepoAnalysisExecutionOrchestrator',
          'sessionstatetransitionservice' => 'App\\Support\\RepoAnalysis\\SessionStateTransitionService',
          'snapshotbuilder' => 'App\\Support\\RepoAnalysis\\SnapshotBuilder',
          'carbonimmutable' => 'Carbon\\CarbonImmutable',
          'shouldqueue' => 'Illuminate\\Contracts\\Queue\\ShouldQueue',
          'queueable' => 'Illuminate\\Foundation\\Queue\\Queueable',
          'timeoutexceededexception' => 'Illuminate\\Queue\\TimeoutExceededException',
          'log' => 'Illuminate\\Support\\Facades\\Log',
          'processtimedoutexception' => 'Symfony\\Component\\Process\\Exception\\ProcessTimedOutException',
          'throwable' => 'Throwable',
        ),
         'className' => 'App\\Jobs\\RepoAnalysis\\ExecuteRepoAnalysisTaskJob',
         'functionName' => 'dispatchLessonIfEnabled',
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
      '/Users/garethdaine/Code/agent/app/Jobs/RepoAnalysis/ExecuteRepoAnalysisTaskJob.php' => '874ed05f41c5369347e56eaf67d1eadde46210e3af0fbc331defbbb9e1c01de6',
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