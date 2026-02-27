<?php

namespace Tests\Unit\Support\Compliance;

use App\Support\Compliance\ComplexityClassifier;
use App\Support\Compliance\DTOs\ComplexityResult;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComplexityClassifierTest extends TestCase
{
    private ComplexityClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = new ComplexityClassifier([
            'file_count' => 3,
            'loc_count' => 50,
            'directory_count' => 2,
        ]);
    }

    #[Test]
    public function it_returns_simple_when_all_metrics_below_thresholds(): void
    {
        $result = $this->classifier->classify([
            'file_count' => 2,
            'loc_count' => 30,
            'directory_count' => 1,
        ]);

        $this->assertInstanceOf(ComplexityResult::class, $result);
        $this->assertSame('simple', $result->classification);
        $this->assertFalse($result->isNonTrivial());
        $this->assertSame([], $result->evidence);
        $this->assertFalse($result->overrideApplied);
    }

    #[Test]
    public function it_returns_non_trivial_when_file_count_exceeds_threshold(): void
    {
        $result = $this->classifier->classify([
            'file_count' => 5,
            'loc_count' => 30,
            'directory_count' => 1,
        ]);

        $this->assertSame('non_trivial', $result->classification);
        $this->assertTrue($result->isNonTrivial());
        $this->assertContains('file_count:5>3', $result->evidence);
        $this->assertFalse($result->overrideApplied);
    }

    #[Test]
    public function it_returns_non_trivial_when_loc_count_exceeds_threshold(): void
    {
        $result = $this->classifier->classify([
            'file_count' => 2,
            'loc_count' => 100,
            'directory_count' => 1,
        ]);

        $this->assertSame('non_trivial', $result->classification);
        $this->assertTrue($result->isNonTrivial());
        $this->assertContains('loc_count:100>50', $result->evidence);
        $this->assertFalse($result->overrideApplied);
    }

    #[Test]
    public function it_returns_non_trivial_when_directory_count_exceeds_threshold(): void
    {
        $result = $this->classifier->classify([
            'file_count' => 2,
            'loc_count' => 30,
            'directory_count' => 5,
        ]);

        $this->assertSame('non_trivial', $result->classification);
        $this->assertTrue($result->isNonTrivial());
        $this->assertContains('directory_count:5>2', $result->evidence);
        $this->assertFalse($result->overrideApplied);
    }

    #[Test]
    public function it_respects_force_simple_override(): void
    {
        $result = $this->classifier->classify([
            'file_count' => 10,
            'loc_count' => 200,
            'directory_count' => 10,
            'complexity_override' => 'force_simple',
        ]);

        $this->assertSame('simple', $result->classification);
        $this->assertFalse($result->isNonTrivial());
        $this->assertSame([], $result->evidence);
        $this->assertTrue($result->overrideApplied);
    }

    #[Test]
    public function it_respects_force_non_trivial_override(): void
    {
        $result = $this->classifier->classify([
            'file_count' => 1,
            'loc_count' => 10,
            'directory_count' => 1,
            'complexity_override' => 'force_non_trivial',
        ]);

        $this->assertSame('non_trivial', $result->classification);
        $this->assertTrue($result->isNonTrivial());
        $this->assertContains('override', $result->evidence);
        $this->assertTrue($result->overrideApplied);
    }

    #[Test]
    public function it_returns_evidence_array_with_triggered_thresholds(): void
    {
        $result = $this->classifier->classify([
            'file_count' => 5,
            'loc_count' => 100,
            'directory_count' => 5,
        ]);

        $this->assertSame('non_trivial', $result->classification);
        $this->assertCount(3, $result->evidence);
        $this->assertContains('file_count:5>3', $result->evidence);
        $this->assertContains('loc_count:100>50', $result->evidence);
        $this->assertContains('directory_count:5>2', $result->evidence);
    }

    #[Test]
    public function it_treats_missing_metrics_as_zero(): void
    {
        $result = $this->classifier->classify([]);

        $this->assertSame('simple', $result->classification);
        $this->assertFalse($result->isNonTrivial());
        $this->assertSame([], $result->evidence);
    }

    #[Test]
    public function it_treats_exactly_at_threshold_as_simple(): void
    {
        $result = $this->classifier->classify([
            'file_count' => 3,
            'loc_count' => 50,
            'directory_count' => 2,
        ]);

        $this->assertSame('simple', $result->classification);
        $this->assertFalse($result->isNonTrivial());
        $this->assertSame([], $result->evidence);
    }

    #[Test]
    public function from_config_creates_classifier_with_config_thresholds(): void
    {
        config(['agent.compliance.complexity_thresholds' => [
            'file_count' => 5,
            'loc_count' => 100,
            'directory_count' => 3,
        ]]);

        $classifier = ComplexityClassifier::fromConfig();

        // Should be simple with these values since thresholds are higher
        $result = $classifier->classify([
            'file_count' => 4,
            'loc_count' => 80,
            'directory_count' => 2,
        ]);

        $this->assertSame('simple', $result->classification);

        // Should be non_trivial when exceeding configured thresholds
        $result = $classifier->classify([
            'file_count' => 6,
            'loc_count' => 80,
            'directory_count' => 2,
        ]);

        $this->assertSame('non_trivial', $result->classification);
    }
}
