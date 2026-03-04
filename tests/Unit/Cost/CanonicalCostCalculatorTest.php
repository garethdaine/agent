<?php

declare(strict_types=1);

namespace Tests\Unit\Cost;

use App\Services\Cost\CanonicalCostCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CanonicalCostCalculatorTest extends TestCase
{
    #[Test]
    public function it_calculates_deterministic_cost_from_token_counts_and_rates(): void
    {
        $calculator = app(CanonicalCostCalculator::class);

        $cost = $calculator->calculate(
            inputTokens: 1500,
            outputTokens: 500,
            inputCostPerThousandUsd: 0.003,
            outputCostPerThousandUsd: 0.009,
        );

        $this->assertSame(0.009, $cost);
    }

    #[Test]
    public function it_returns_zero_when_no_tokens_are_used(): void
    {
        $calculator = app(CanonicalCostCalculator::class);

        $cost = $calculator->calculate(
            inputTokens: 0,
            outputTokens: 0,
            inputCostPerThousandUsd: 0.003,
            outputCostPerThousandUsd: 0.009,
        );

        $this->assertSame(0.0, $cost);
    }

    #[Test]
    public function it_rejects_negative_inputs(): void
    {
        $calculator = app(CanonicalCostCalculator::class);

        $this->expectException(InvalidArgumentException::class);

        $calculator->calculate(
            inputTokens: -1,
            outputTokens: 0,
            inputCostPerThousandUsd: 0.003,
            outputCostPerThousandUsd: 0.009,
        );
    }
}
