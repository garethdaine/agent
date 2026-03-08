<?php

declare(strict_types=1);

test('all models must use $fillable instead of $guarded = []', function () {
    $modelPath = dirname(__DIR__, 3).'/app/Models';
    $modelFiles = glob($modelPath.'/*.php');
    $violations = [];

    foreach ($modelFiles as $file) {
        $contents = file_get_contents($file);
        if (preg_match('/protected\s+\$guarded\s*=\s*\[\s*\]/', $contents)) {
            $violations[] = basename($file, '.php');
        }
    }

    expect($violations)
        ->toBeEmpty(
            'The following models use $guarded = [] instead of $fillable: '.implode(', ', $violations)
        );
});

test('all models must declare a $fillable property', function () {
    $modelPath = dirname(__DIR__, 3).'/app/Models';
    $modelFiles = glob($modelPath.'/*.php');
    $missing = [];

    foreach ($modelFiles as $file) {
        $contents = file_get_contents($file);

        // Skip if it doesn't extend a Model class
        if (! preg_match('/extends\s+\w*Model/', $contents)) {
            continue;
        }

        if (! preg_match('/protected\s+\$fillable\s*=/', $contents)) {
            $missing[] = basename($file, '.php');
        }
    }

    expect($missing)
        ->toBeEmpty(
            'The following models are missing $fillable: '.implode(', ', $missing)
        );
});
