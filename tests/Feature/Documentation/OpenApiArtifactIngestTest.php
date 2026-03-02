<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use App\Models\ApiDocArtifact;
use App\Models\DocumentationEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class OpenApiArtifactIngestTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureRoot;

    private string $openApiArtifactPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = storage_path('framework/testing/openapi-artifact-ingest');
        File::deleteDirectory($this->fixtureRoot);
        File::ensureDirectoryExists($this->fixtureRoot);

        $this->openApiArtifactPath = $this->fixtureRoot.'/openapi.yaml';

        config()->set('documentation.openapi.artifact_path', $this->openApiArtifactPath);
        config()->set('documentation.openapi.linked_doc_slugs_extension', 'x-linked-doc-slugs');
    }

    public function test_ingest_imports_operations_and_is_idempotent(): void
    {
        $entryOne = $this->createApiNarrativeEntry('chat-sessions-api');
        $entryTwo = $this->createApiNarrativeEntry('chat-stream-api');

        $this->writeOpenApiArtifact([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Messenger API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/chat/sessions' => [
                    'get' => [
                        'operationId' => 'listChatSessions',
                        'summary' => 'List sessions',
                        'description' => 'Returns available sessions.',
                        'tags' => ['chat'],
                        'x-linked-doc-slugs' => ['chat-sessions-api'],
                    ],
                ],
                '/chat/actions/{id}/stream' => [
                    'get' => [
                        'operationId' => 'streamRunUpdates',
                        'summary' => 'Stream updates',
                        'description' => 'Returns SSE payload.',
                        'tags' => ['chat', 'stream'],
                        'x-linked-doc-slugs' => ['chat-stream-api'],
                    ],
                ],
            ],
        ]);

        $this->artisan('docs:openapi:ingest')
            ->assertSuccessful();

        $artifact = ApiDocArtifact::query()
            ->where('operation_id', 'listChatSessions')
            ->firstOrFail();

        $this->assertSame($entryOne->id, $artifact->documentation_entry_id);
        $this->assertSame('GET', $artifact->http_method);
        $this->assertSame('/chat/sessions', $artifact->path);
        $this->assertSame(['chat-sessions-api'], $artifact->linked_doc_slugs);
        $this->assertSame('1.0.0', $artifact->spec_version);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $artifact->spec_checksum);

        $artifactId = $artifact->id;
        $checksum = (string) $artifact->spec_checksum;

        $this->artisan('docs:openapi:ingest')
            ->assertSuccessful();

        $artifact->refresh();
        $this->assertSame($artifactId, $artifact->id);
        $this->assertSame($checksum, $artifact->spec_checksum);
        $this->assertDatabaseCount('api_doc_artifacts', 2);

        $secondArtifact = ApiDocArtifact::query()
            ->where('operation_id', 'streamRunUpdates')
            ->firstOrFail();

        $this->assertSame($entryTwo->id, $secondArtifact->documentation_entry_id);
    }

    public function test_ingest_updates_checksum_version_and_endpoint_metadata(): void
    {
        $entry = $this->createApiNarrativeEntry('chat-sessions-api');

        $this->writeOpenApiArtifact([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Messenger API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/chat/sessions' => [
                    'get' => [
                        'operationId' => 'listChatSessions',
                        'summary' => 'List sessions',
                        'description' => 'Returns available sessions.',
                        'tags' => ['chat'],
                        'x-linked-doc-slugs' => ['chat-sessions-api'],
                    ],
                ],
            ],
        ]);

        $this->artisan('docs:openapi:ingest')
            ->assertSuccessful();

        $artifact = ApiDocArtifact::query()
            ->where('operation_id', 'listChatSessions')
            ->firstOrFail();

        $this->assertSame($entry->id, $artifact->documentation_entry_id);
        $this->assertSame('GET', $artifact->http_method);
        $this->assertSame('/chat/sessions', $artifact->path);
        $this->assertSame('1.0.0', $artifact->spec_version);

        $previousChecksum = (string) $artifact->spec_checksum;

        $this->writeOpenApiArtifact([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Messenger API',
                'version' => '1.1.0',
            ],
            'paths' => [
                '/chat/conversations' => [
                    'post' => [
                        'operationId' => 'listChatSessions',
                        'summary' => 'List and create session projection',
                        'description' => 'Updated endpoint path and method.',
                        'tags' => ['chat', 'write'],
                        'x-linked-doc-slugs' => ['chat-sessions-api'],
                    ],
                ],
            ],
        ]);

        $this->artisan('docs:openapi:ingest')
            ->assertSuccessful();

        $artifact->refresh();
        $this->assertSame('POST', $artifact->http_method);
        $this->assertSame('/chat/conversations', $artifact->path);
        $this->assertSame('1.1.0', $artifact->spec_version);
        $this->assertNotSame($previousChecksum, (string) $artifact->spec_checksum);
        $this->assertSame(['chat-sessions-api'], $artifact->linked_doc_slugs);
    }

    public function test_ingest_fails_when_operation_is_missing_operation_id(): void
    {
        $this->createApiNarrativeEntry('chat-sessions-api');

        $this->writeOpenApiArtifact([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Messenger API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/chat/sessions' => [
                    'get' => [
                        'summary' => 'Missing operation id',
                        'description' => 'Should fail validation.',
                        'tags' => ['chat'],
                        'x-linked-doc-slugs' => ['chat-sessions-api'],
                    ],
                ],
            ],
        ]);

        $this->artisan('docs:openapi:ingest')
            ->assertFailed();

        $this->assertDatabaseCount('api_doc_artifacts', 0);
    }

    public function test_ingest_fails_when_linked_doc_slug_is_unresolved(): void
    {
        $this->writeOpenApiArtifact([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'Messenger API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/chat/sessions' => [
                    'get' => [
                        'operationId' => 'listChatSessions',
                        'summary' => 'List sessions',
                        'description' => 'Missing narrative link.',
                        'tags' => ['chat'],
                        'x-linked-doc-slugs' => ['missing-narrative-slug'],
                    ],
                ],
            ],
        ]);

        $this->artisan('docs:openapi:ingest')
            ->assertFailed();

        $this->assertDatabaseCount('api_doc_artifacts', 0);
    }

    private function createApiNarrativeEntry(string $slug): DocumentationEntry
    {
        return DocumentationEntry::query()->create([
            'domain' => 'api_doc',
            'slug' => $slug,
            'locale' => 'en',
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'summary' => 'Narrative documentation for '.$slug,
            'section' => 'api',
            'audience' => 'developer',
            'status' => 'published',
            'version' => '1.0.0',
            'tags' => ['api'],
            'owner' => 'docs-team',
            'body_markdown' => '# '.$slug,
            'body_html' => '<h1>'.$slug.'</h1>',
            'source_path' => 'api/'.$slug.'.md',
            'source_checksum' => hash('sha256', $slug),
            'published_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function writeOpenApiArtifact(array $spec): void
    {
        File::put(
            $this->openApiArtifactPath,
            Yaml::dump($spec, 12, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
        );
    }
}
