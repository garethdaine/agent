<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_documentation_runtime_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('documentation_entries'));
        $this->assertTrue(Schema::hasColumns('documentation_entries', [
            'id',
            'domain',
            'slug',
            'locale',
            'title',
            'summary',
            'section',
            'body_markdown',
            'body_html',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasTable('documentation_fragments'));
        $this->assertTrue(Schema::hasColumns('documentation_fragments', [
            'id',
            'ui_key',
            'locale',
            'short_text',
            'long_text',
            'learn_more_entry_id',
            'severity',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasTable('documentation_links'));
        $this->assertTrue(Schema::hasColumns('documentation_links', [
            'id',
            'documentation_entry_id',
            'documentation_fragment_id',
            'route_name',
            'setting_key',
            'feature_flag',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasTable('api_doc_artifacts'));
        $this->assertTrue(Schema::hasColumns('api_doc_artifacts', [
            'id',
            'documentation_entry_id',
            'operation_id',
            'http_method',
            'path',
            'summary',
            'description',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_documentation_entries_domain_slug_locale_must_be_unique(): void
    {
        DB::table('documentation_entries')->insert([
            'domain' => 'product_doc',
            'slug' => 'overview',
            'locale' => 'en',
            'title' => 'Overview',
            'summary' => 'Summary',
            'section' => 'getting-started',
            'body_markdown' => '# Overview',
            'body_html' => '<h1>Overview</h1>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('documentation_entries')->insert([
            'domain' => 'product_doc',
            'slug' => 'overview',
            'locale' => 'en',
            'title' => 'Duplicate',
            'summary' => 'Summary',
            'section' => 'getting-started',
            'body_markdown' => '# Duplicate',
            'body_html' => '<h1>Duplicate</h1>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_documentation_fragments_ui_key_locale_must_be_unique(): void
    {
        DB::table('documentation_fragments')->insert([
            'ui_key' => 'docs.monitor.latency',
            'locale' => 'en',
            'short_text' => 'Latency details',
            'long_text' => 'Long form tooltip text.',
            'severity' => 'info',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('documentation_fragments')->insert([
            'ui_key' => 'docs.monitor.latency',
            'locale' => 'en',
            'short_text' => 'Duplicate key',
            'long_text' => 'Duplicate tooltip text.',
            'severity' => 'warning',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_documentation_entries_reject_invalid_domain_values(): void
    {
        $this->expectException(QueryException::class);

        DB::table('documentation_entries')->insert([
            'domain' => 'invalid_domain',
            'slug' => 'bad-domain',
            'locale' => 'en',
            'title' => 'Bad Domain',
            'summary' => 'Summary',
            'section' => 'invalid',
            'body_markdown' => '# Invalid',
            'body_html' => '<h1>Invalid</h1>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_documentation_fragments_reject_invalid_severity_values(): void
    {
        $this->expectException(QueryException::class);

        DB::table('documentation_fragments')->insert([
            'ui_key' => 'docs.risk.critical',
            'locale' => 'en',
            'short_text' => 'Critical',
            'long_text' => 'Invalid severity should fail.',
            'severity' => 'critical',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_documentation_links_enforce_foreign_key_integrity(): void
    {
        $entryId = DB::table('documentation_entries')->insertGetId([
            'domain' => 'product_doc',
            'slug' => 'jobs',
            'locale' => 'en',
            'title' => 'Jobs',
            'summary' => 'Jobs docs',
            'section' => 'jobs',
            'body_markdown' => '# Jobs',
            'body_html' => '<h1>Jobs</h1>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('documentation_links')->insert([
            'documentation_entry_id' => $entryId,
            'documentation_fragment_id' => null,
            'route_name' => 'jobs.index',
            'setting_key' => null,
            'feature_flag' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('documentation_links')->insert([
            'documentation_entry_id' => 999999,
            'documentation_fragment_id' => null,
            'route_name' => 'jobs.show',
            'setting_key' => null,
            'feature_flag' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_api_doc_artifacts_enforce_foreign_key_integrity(): void
    {
        $this->expectException(QueryException::class);

        DB::table('api_doc_artifacts')->insert([
            'documentation_entry_id' => 999999,
            'operation_id' => 'listJobs',
            'http_method' => 'GET',
            'path' => '/agent/api/v1/jobs',
            'summary' => 'List jobs',
            'description' => 'Returns jobs list.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
