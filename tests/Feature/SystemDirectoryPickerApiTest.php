<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\System\DirectoryPicker;
use App\Support\System\DirectoryPickerException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemDirectoryPickerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_picker_requires_authentication(): void
    {
        $this->postJson('/agent/api/v1/system/directory-picker')
            ->assertUnauthorized();
    }

    public function test_directory_picker_returns_selected_absolute_path(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $picker = new class extends DirectoryPicker
        {
            public ?string $receivedCurrentPath = null;

            public function pick(?string $currentPath = null): string
            {
                $this->receivedCurrentPath = $currentPath;

                return base_path();
            }
        };

        $this->app->instance(DirectoryPicker::class, $picker);

        $response = $this->postJson('/agent/api/v1/system/directory-picker', [
            'current_path' => base_path(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.path', base_path());

        $this->assertSame(base_path(), $picker->receivedCurrentPath);
    }

    public function test_directory_picker_returns_error_envelope_on_cancel(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $picker = new class extends DirectoryPicker
        {
            public function pick(?string $currentPath = null): string
            {
                throw new DirectoryPickerException(
                    errorCode: 'DIRECTORY_PICKER_CANCELLED',
                    message: 'Folder selection was cancelled.',
                    statusCode: 422
                );
            }
        };

        $this->app->instance(DirectoryPicker::class, $picker);

        $this->postJson('/agent/api/v1/system/directory-picker')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DIRECTORY_PICKER_CANCELLED')
            ->assertJsonPath('error.message', 'Folder selection was cancelled.');
    }

    public function test_directory_picker_validates_current_path_payload_type(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/agent/api/v1/system/directory-picker', [
            'current_path' => ['not-a-string'],
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.message', 'The given data was invalid.');
    }
}
