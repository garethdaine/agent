<?php

declare(strict_types=1);

namespace Tests\Unit\Tunnel;

use App\Services\Tunnel\CloudflaredInstaller;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class CloudflaredInstallerTest extends TestCase
{
    public function test_detect_platform_macos(): void
    {
        $installer = new CloudflaredInstaller;

        if (PHP_OS_FAMILY !== 'Darwin') {
            $this->markTestSkipped('Requires macOS');
        }

        $this->assertSame('macos', $installer->detectPlatform());
    }

    public function test_detect_platform_linux_apt(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('Requires Linux');
        }

        Process::fake([
            'which apt-get' => Process::result(output: '/usr/bin/apt-get', exitCode: 0),
        ]);

        $installer = new CloudflaredInstaller;
        $this->assertSame('linux-apt', $installer->detectPlatform());
    }

    public function test_detect_platform_linux_yum(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('Requires Linux');
        }

        Process::fake([
            'which apt-get' => Process::result(output: '', exitCode: 1),
            'which yum' => Process::result(output: '/usr/bin/yum', exitCode: 0),
        ]);

        $installer = new CloudflaredInstaller;
        $this->assertSame('linux-yum', $installer->detectPlatform());
    }

    public function test_get_install_command_per_platform(): void
    {
        $installer = new CloudflaredInstaller;

        $this->assertSame('brew install cloudflared', $installer->getInstallCommand('macos'));
        $this->assertSame('sudo apt-get install -y cloudflared', $installer->getInstallCommand('linux-apt'));
        $this->assertSame('sudo yum install -y cloudflared', $installer->getInstallCommand('linux-yum'));
        $this->assertStringContainsString('curl', $installer->getInstallCommand('linux-binary'));
    }

    public function test_get_install_instructions_structure(): void
    {
        $installer = new CloudflaredInstaller;
        $instructions = $installer->getInstallInstructions('macos');

        $this->assertArrayHasKey('platform', $instructions);
        $this->assertArrayHasKey('command', $instructions);
        $this->assertArrayHasKey('description', $instructions);
        $this->assertArrayHasKey('manual_url', $instructions);
        $this->assertSame('macos', $instructions['platform']);
        $this->assertSame('brew install cloudflared', $instructions['command']);
    }
}
