<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Support\Security\TokenEstimator;
use PHPUnit\Framework\TestCase;

class TokenEstimatorTest extends TestCase
{
    private TokenEstimator $estimator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->estimator = new TokenEstimator;
    }

    public function test_fast_estimate_400_char_string_returns_100_tokens(): void
    {
        $content = str_repeat('a', 400);
        $this->assertSame(100, $this->estimator->fastEstimate($content));
    }

    public function test_fast_estimate_empty_string_returns_zero(): void
    {
        $this->assertSame(0, $this->estimator->fastEstimate(''));
    }

    public function test_precise_estimate_known_english_text(): void
    {
        $text = 'The quick brown fox jumps over the lazy dog.';
        $tokens = $this->estimator->preciseEstimate($text);

        $this->assertGreaterThan(0, $tokens);
        $this->assertLessThan(20, $tokens);
    }

    public function test_precise_estimate_empty_string_returns_zero(): void
    {
        $this->assertSame(0, $this->estimator->preciseEstimate(''));
    }

    public function test_truncate_to_token_budget_exceeding_budget(): void
    {
        $content = str_repeat('The quick brown fox jumps over the lazy dog. ', 100);
        $budget = 10;

        $result = $this->estimator->truncateToTokenBudget($content, $budget);

        $this->assertTrue($result['truncated']);
        $this->assertGreaterThan($budget, $result['originalTokens']);
        $this->assertLessThan(mb_strlen($content), mb_strlen($result['content']));
    }

    public function test_truncate_to_token_budget_within_budget(): void
    {
        $content = 'Hello world';
        $budget = 1000;

        $result = $this->estimator->truncateToTokenBudget($content, $budget);

        $this->assertFalse($result['truncated']);
        $this->assertSame($content, $result['content']);
        $this->assertGreaterThan(0, $result['originalTokens']);
    }
}
