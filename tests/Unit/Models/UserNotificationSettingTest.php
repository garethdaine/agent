<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\UserNotificationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserNotificationSettingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $setting = UserNotificationSetting::create([
            'user_id' => $user->id,
            'channel' => 'email',
            'enabled' => true,
        ]);

        $this->assertInstanceOf(User::class, $setting->user);
        $this->assertEquals($user->id, $setting->user->id);
    }

    #[Test]
    public function it_casts_enabled_to_boolean(): void
    {
        $user = User::factory()->create();
        $setting = UserNotificationSetting::create([
            'user_id' => $user->id,
            'channel' => 'email',
            'enabled' => 1,
        ]);

        $this->assertIsBool($setting->enabled);
        $this->assertTrue($setting->enabled);
    }

    #[Test]
    public function it_returns_valid_channel_options(): void
    {
        $options = UserNotificationSetting::getChannelOptions();

        $this->assertContains('email', $options);
        $this->assertContains('in_app', $options);
        $this->assertContains('both', $options);
    }

    #[Test]
    public function it_defaults_channel_to_email(): void
    {
        $user = User::factory()->create();
        $setting = UserNotificationSetting::create([
            'user_id' => $user->id,
        ]);

        $this->assertEquals('email', $setting->channel);
    }
}
