<?php

declare(strict_types=1);

namespace Tests\Feature\Memory;

use App\Support\Memory\EntitySanitizer;
use Tests\TestCase;

/**
 * Tests for entity sanitization before Neo4j storage.
 *
 * Finding #12, AGE-2292: Ensures crafted conversation content
 * cannot create poisoned graph entities via Cypher injection,
 * oversized names, invalid types, or control characters.
 */
class MemoryFormationSanitizationTest extends TestCase
{
    private EntitySanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['memory.entity_types' => [
            'Person', 'Organization', 'Location', 'Date', 'Concept',
            'File', 'Function', 'Class', 'API', 'Error', 'Dependency',
        ]]);

        $this->sanitizer = new EntitySanitizer;
    }

    public function test_cypher_injection_in_entity_name_is_rejected(): void
    {
        $entities = [
            ['type' => 'Person', 'name' => "'); MATCH (n) DETACH DELETE n; //"],
        ];

        $result = $this->sanitizer->sanitize($entities);

        $this->assertEmpty($result);
    }

    public function test_entity_name_exceeding_max_length_is_truncated(): void
    {
        $longName = str_repeat('a', 300);
        $entities = [
            ['type' => 'Person', 'name' => $longName],
        ];

        $result = $this->sanitizer->sanitize($entities);

        $this->assertCount(1, $result);
        $this->assertLessThanOrEqual(255, mb_strlen($result[0]['name']));
    }

    public function test_entity_type_not_in_allowed_list_is_rejected(): void
    {
        $entities = [
            ['type' => 'MaliciousType', 'name' => 'valid-name'],
        ];

        $result = $this->sanitizer->sanitize($entities);

        $this->assertEmpty($result);
    }

    public function test_entity_content_with_control_characters_is_sanitized(): void
    {
        $entities = [
            ['type' => 'Person', 'name' => "John\x00Doe\x01\x02"],
        ];

        $result = $this->sanitizer->sanitize($entities);

        $this->assertCount(1, $result);
        $this->assertEquals('JohnDoe', $result[0]['name']);
        $this->assertStringNotContainsString("\x00", $result[0]['name']);
    }

    public function test_valid_entities_pass_through_unchanged(): void
    {
        $entities = [
            ['type' => 'Person', 'name' => 'John Doe', 'confidence' => 0.9],
            ['type' => 'File', 'name' => 'app-controller_v2', 'confidence' => 0.8],
        ];

        $result = $this->sanitizer->sanitize($entities);

        $this->assertCount(2, $result);
        $this->assertEquals('John Doe', $result[0]['name']);
        $this->assertEquals('app-controller_v2', $result[1]['name']);
        $this->assertEquals(0.9, $result[0]['confidence']);
    }

    public function test_empty_entity_name_is_rejected(): void
    {
        $entities = [
            ['type' => 'Person', 'name' => ''],
            ['type' => 'Person', 'name' => '   '],
        ];

        $result = $this->sanitizer->sanitize($entities);

        $this->assertEmpty($result);
    }

    public function test_null_entity_type_is_rejected(): void
    {
        $entities = [
            ['type' => null, 'name' => 'valid-name'],
            ['name' => 'missing-type'],
        ];

        $result = $this->sanitizer->sanitize($entities);

        $this->assertEmpty($result);
    }

    public function test_unicode_entity_names_are_allowed(): void
    {
        $entities = [
            ['type' => 'Person', 'name' => '田中太郎'],
            ['type' => 'Organization', 'name' => 'Ünïcödé Corp'],
        ];

        $result = $this->sanitizer->sanitize($entities);

        $this->assertCount(2, $result);
        $this->assertEquals('田中太郎', $result[0]['name']);
        $this->assertEquals('Ünïcödé Corp', $result[1]['name']);
    }

    public function test_duplicate_entities_in_same_batch_are_deduplicated(): void
    {
        $entities = [
            ['type' => 'Person', 'name' => 'John Doe', 'confidence' => 0.9],
            ['type' => 'Person', 'name' => 'John Doe', 'confidence' => 0.7],
            ['type' => 'Person', 'name' => 'john doe', 'confidence' => 0.5],
        ];

        $result = $this->sanitizer->sanitize($entities);

        // Exact duplicates (same type+name) are deduplicated, case-sensitive
        $this->assertCount(2, $result);
        // Keeps the first occurrence
        $this->assertEquals(0.9, $result[0]['confidence']);
    }

    public function test_cypher_special_characters_in_name_are_rejected(): void
    {
        $maliciousNames = [
            "test' OR 1=1",
            'test" OR 1=1',
            'test\\ MATCH (n) DELETE n',
            'test`; DROP',
            'test{injection}',
            'test[0]',
        ];

        foreach ($maliciousNames as $name) {
            $entities = [['type' => 'Person', 'name' => $name]];
            $result = $this->sanitizer->sanitize($entities);
            $this->assertEmpty($result, "Expected rejection for name: {$name}");
        }
    }

    public function test_names_with_allowed_special_characters_pass(): void
    {
        $validNames = [
            'C++ Compiler',
            'Node.js Runtime',
            'user@example.com',
            'path/to/file',
            'version 2.0',
            'My-App_v3',
            'Class::method',
            'error #404',
        ];

        foreach ($validNames as $name) {
            $entities = [['type' => 'Concept', 'name' => $name]];
            $result = $this->sanitizer->sanitize($entities);
            $this->assertCount(1, $result, "Expected pass for name: {$name}");
        }
    }
}
