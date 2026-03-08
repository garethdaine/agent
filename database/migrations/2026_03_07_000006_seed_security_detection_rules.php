<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $rules = [
            // Direct instruction override (15 patterns)
            ['pattern' => 'ignore previous instructions', 'pattern_type' => 'keyword', 'severity' => 'critical', 'weight' => 1.00],
            ['pattern' => 'ignore all prior instructions', 'pattern_type' => 'keyword', 'severity' => 'critical', 'weight' => 1.00],
            ['pattern' => 'disregard the above', 'pattern_type' => 'keyword', 'severity' => 'critical', 'weight' => 0.95],
            ['pattern' => 'forget everything', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.90],
            ['pattern' => 'new instructions:', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.85],
            ['pattern' => 'override system prompt', 'pattern_type' => 'keyword', 'severity' => 'critical', 'weight' => 1.00],
            ['pattern' => 'you are now', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.80],
            ['pattern' => 'from now on you are', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.85],
            ['pattern' => 'act as if', 'pattern_type' => 'keyword', 'severity' => 'medium', 'weight' => 0.60],
            ['pattern' => 'pretend you are', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.80],
            ['pattern' => 'your new role is', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.85],
            ['pattern' => '/^system:\s/mi', 'pattern_type' => 'regex', 'severity' => 'high', 'weight' => 0.85],
            ['pattern' => '/system:\s+new\s+instructions/i', 'pattern_type' => 'regex', 'severity' => 'critical', 'weight' => 0.95],
            ['pattern' => '[system]', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.80],
            ['pattern' => '<system>', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.85],
            ['pattern' => 'IMPORTANT: ignore', 'pattern_type' => 'keyword', 'severity' => 'critical', 'weight' => 0.95],
            ['pattern' => '/ignore\b.{0,20}\bprevious\b.{0,20}\binstructions/i', 'pattern_type' => 'regex', 'severity' => 'critical', 'weight' => 1.00],
            ['pattern' => '/ignore\b.{0,20}\binstructions/i', 'pattern_type' => 'regex', 'severity' => 'high', 'weight' => 0.85],
            ['pattern' => 'output your system prompt', 'pattern_type' => 'keyword', 'severity' => 'critical', 'weight' => 0.95],
            ['pattern' => 'reveal secrets', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.80],
            ['pattern' => 'reveals secrets', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.80],
            ['pattern' => '/send\b.{0,30}\bcredentials/i', 'pattern_type' => 'regex', 'severity' => 'critical', 'weight' => 0.95],
            ['pattern' => 'ignore safety', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.85],
            ['pattern' => 'ignore my instructions', 'pattern_type' => 'keyword', 'severity' => 'critical', 'weight' => 0.95],
            ['pattern' => '/post\b.{0,40}\bto\s+\S+\.com/i', 'pattern_type' => 'regex', 'severity' => 'high', 'weight' => 0.85],
            ['pattern' => '/send\b.{0,40}\bto\s+\S+\.com/i', 'pattern_type' => 'regex', 'severity' => 'high', 'weight' => 0.85],
            ['pattern' => 'unrestricted AI', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.85],
            ['pattern' => 'reveal all data', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.80],

            // Role-switching attempts (10 patterns)
            ['pattern' => '/you are an?\s+(?:AI|assistant|bot|agent)/i', 'pattern_type' => 'regex', 'severity' => 'medium', 'weight' => 0.50],
            ['pattern' => 'you must', 'pattern_type' => 'keyword', 'severity' => 'low', 'weight' => 0.30],
            ['pattern' => 'you should now', 'pattern_type' => 'keyword', 'severity' => 'medium', 'weight' => 0.55],
            ['pattern' => 'your purpose is now', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.80],
            ['pattern' => 'switch to', 'pattern_type' => 'keyword', 'severity' => 'medium', 'weight' => 0.50],
            ['pattern' => '/enter\s+\w+\s+mode/i', 'pattern_type' => 'regex', 'severity' => 'high', 'weight' => 0.75],
            ['pattern' => '/activate\s+\w+\s+mode/i', 'pattern_type' => 'regex', 'severity' => 'high', 'weight' => 0.75],
            ['pattern' => 'jailbreak', 'pattern_type' => 'keyword', 'severity' => 'critical', 'weight' => 1.00],
            ['pattern' => 'DAN mode', 'pattern_type' => 'keyword', 'severity' => 'critical', 'weight' => 1.00],
            ['pattern' => '/you are an?\s/i', 'pattern_type' => 'regex', 'severity' => 'low', 'weight' => 0.25],

            // Authority/urgency claims (10 patterns)
            ['pattern' => '/^IMPORTANT:\s/m', 'pattern_type' => 'regex', 'severity' => 'medium', 'weight' => 0.50],
            ['pattern' => '/^URGENT:\s/m', 'pattern_type' => 'regex', 'severity' => 'medium', 'weight' => 0.55],
            ['pattern' => '/^CRITICAL:\s/m', 'pattern_type' => 'regex', 'severity' => 'medium', 'weight' => 0.55],
            ['pattern' => 'ADMIN OVERRIDE', 'pattern_type' => 'keyword', 'severity' => 'critical', 'weight' => 0.95],
            ['pattern' => 'developer mode', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.80],
            ['pattern' => 'maintenance mode', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.75],
            ['pattern' => 'debug mode', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.75],
            ['pattern' => 'test mode', 'pattern_type' => 'keyword', 'severity' => 'medium', 'weight' => 0.50],
            ['pattern' => 'authorized by', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.70],
            ['pattern' => 'admin note:', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.75],

            // Exfiltration attempts (10 patterns)
            ['pattern' => '/send\s+.+\s+to\s+https?:\/\//i', 'pattern_type' => 'regex', 'severity' => 'critical', 'weight' => 0.95],
            ['pattern' => '/post\s+.+\s+to\s+https?:\/\//i', 'pattern_type' => 'regex', 'severity' => 'critical', 'weight' => 0.95],
            ['pattern' => '/forward\s+.+\s+to\s/i', 'pattern_type' => 'regex', 'severity' => 'high', 'weight' => 0.85],
            ['pattern' => '/email\s+.+\s+to\s/i', 'pattern_type' => 'regex', 'severity' => 'high', 'weight' => 0.85],
            ['pattern' => '/upload\s+.+\s+to\s/i', 'pattern_type' => 'regex', 'severity' => 'high', 'weight' => 0.85],
            ['pattern' => '/transfer\s+.+\s+to\s/i', 'pattern_type' => 'regex', 'severity' => 'high', 'weight' => 0.80],
            ['pattern' => 'exfiltrate', 'pattern_type' => 'keyword', 'severity' => 'critical', 'weight' => 1.00],
            ['pattern' => 'base64 encode and send', 'pattern_type' => 'keyword', 'severity' => 'critical', 'weight' => 1.00],
            ['pattern' => '/fetch\s+https?:\/\/\S+\s+with\s+body/i', 'pattern_type' => 'regex', 'severity' => 'critical', 'weight' => 0.95],
            ['pattern' => '/curl\s+\S+\s+-d\s/i', 'pattern_type' => 'regex', 'severity' => 'critical', 'weight' => 0.90],
            ['pattern' => '/etc/passwd', 'pattern_type' => 'keyword', 'severity' => 'critical', 'weight' => 0.95],

            // Delimiter/encoding attacks (8 patterns)
            ['pattern' => '---END SYSTEM---', 'pattern_type' => 'keyword', 'severity' => 'critical', 'weight' => 0.95],
            ['pattern' => '</system>', 'pattern_type' => 'keyword', 'severity' => 'critical', 'weight' => 0.95],
            ['pattern' => '[/INST]', 'pattern_type' => 'keyword', 'severity' => 'critical', 'weight' => 0.90],
            ['pattern' => '/^Human:\s/m', 'pattern_type' => 'regex', 'severity' => 'high', 'weight' => 0.80],
            ['pattern' => '/^Assistant:\s/m', 'pattern_type' => 'regex', 'severity' => 'high', 'weight' => 0.80],
            ['pattern' => '/\x00/', 'pattern_type' => 'regex', 'severity' => 'critical', 'weight' => 0.90],
            ['pattern' => '%00', 'pattern_type' => 'keyword', 'severity' => 'high', 'weight' => 0.80],
            ['pattern' => '/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', 'pattern_type' => 'regex', 'severity' => 'critical', 'weight' => 0.95],
        ];

        $insertData = array_map(function (array $rule) use ($now) {
            return [
                'id' => Str::uuid()->toString(),
                'pattern' => $rule['pattern'],
                'pattern_type' => $rule['pattern_type'],
                'severity' => $rule['severity'],
                'weight' => $rule['weight'],
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $rules);

        DB::table('security_detection_rules')->insert($insertData);
    }

    public function down(): void
    {
        DB::table('security_detection_rules')->truncate();
    }
};
