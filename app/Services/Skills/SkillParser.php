<?php

namespace App\Services\Skills;

use App\Services\Skills\DTOs\SkillParseResult;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class SkillParser
{
    private const NAME_PATTERN = '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/';

    private const SEMVER_PATTERN = '/^\d+\.\d+\.\d+$/';

    private const MAX_NAME_LENGTH = 64;

    private const MIN_DESCRIPTION_LENGTH = 20;

    private const MAX_DESCRIPTION_LENGTH = 1024;

    private const VALID_RISK_LEVELS = ['low', 'standard', 'elevated', 'critical'];

    private const KNOWN_X_AGENT_KEYS = [
        'industries',
        'risk_level',
        'requires_approval',
        'memory_blocks',
        'mcp_dependencies',
        'tools',
        'trigger_keywords',
        'run_after',
        'compatibility',
    ];

    private const STOPWORDS = [
        'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
        'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'be',
        'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will',
        'would', 'could', 'should', 'may', 'might', 'shall', 'can', 'need',
        'dare', 'ought', 'used', 'it', 'its', 'this', 'that', 'these', 'those',
        'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves', 'you',
        'your', 'yours', 'yourself', 'yourselves', 'he', 'him', 'his', 'himself',
        'she', 'her', 'hers', 'herself', 'itself', 'they', 'them', 'their',
        'theirs', 'themselves', 'what', 'which', 'who', 'whom', 'when', 'where',
        'why', 'how', 'all', 'each', 'every', 'both', 'few', 'more', 'most',
        'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same',
        'so', 'than', 'too', 'very', 'just', 'because', 'if', 'then', 'else',
        'while', 'about', 'up', 'out', 'off', 'over', 'under', 'again',
        'further', 'once', 'here', 'there', 'any', 'also', 'into', 'through',
        'during', 'before', 'after', 'above', 'below', 'between', 'against',
        'since', 'until', 'unless', 'although', 'though', 'whether', 'either',
        'neither', 'yet', 'still', 'already', 'even', 'much', 'many', 'well',
        'back', 'now', 'way', 'get', 'got', 'make', 'made', 'take', 'like',
        'come', 'go', 'see', 'know', 'think', 'look', 'want', 'give', 'use',
        'find', 'tell', 'ask', 'work', 'seem', 'feel', 'try', 'leave', 'call',
        'set', 'keep', 'let', 'begin', 'show', 'hear', 'play', 'run', 'move',
        'live', 'believe', 'bring', 'happen', 'must', 'provide', 'sit', 'stand',
        'lose', 'pay', 'meet', 'include', 'continue', 'learn', 'change', 'lead',
        'understand', 'watch', 'follow', 'stop', 'create', 'speak', 'read',
        'allow', 'add', 'spend', 'grow', 'open', 'walk', 'win', 'offer',
        'remember', 'consider', 'appear', 'buy', 'wait', 'serve', 'die', 'send',
        'expect', 'build', 'stay', 'fall', 'cut', 'reach', 'kill', 'remain',
    ];

    public function parse(string $skillMdContent): SkillParseResult
    {
        $errors = [];
        $warnings = [];

        if (trim($skillMdContent) === '') {
            return $this->errorResult(['SKILL.md content is empty.']);
        }

        // Split on --- delimiters
        if (! preg_match('/\A---\s*\n(.*?)\n---\s*(?:\n(.*))?\z/s', $skillMdContent, $matches)) {
            return $this->errorResult(['SKILL.md is missing valid YAML frontmatter delimiters (---).']);
        }

        $yamlContent = $matches[1];
        $markdownBody = trim($matches[2] ?? '');

        // Parse YAML
        try {
            $frontmatter = Yaml::parse($yamlContent);
        } catch (ParseException $e) {
            return $this->errorResult(['Invalid YAML in frontmatter: '.$e->getMessage()]);
        }

        if (! is_array($frontmatter)) {
            return $this->errorResult(['Frontmatter must be a YAML mapping.']);
        }

        // Extract standard fields
        $name = $frontmatter['name'] ?? null;
        $description = $frontmatter['description'] ?? null;
        $version = $frontmatter['version'] ?? null;
        $author = $frontmatter['author'] ?? null;
        $license = $frontmatter['license'] ?? null;

        // Extract x-agent block
        $xAgent = $frontmatter['x-agent'] ?? [];
        if (! is_array($xAgent)) {
            $xAgent = [];
            $warnings[] = 'x-agent block is not a valid mapping; using defaults.';
        }

        // Check for unknown x-agent keys
        foreach (array_keys($xAgent) as $key) {
            if (! in_array($key, self::KNOWN_X_AGENT_KEYS, true)) {
                $warnings[] = "Unknown x-agent key: '{$key}'.";
            }
        }

        $industries = (array) ($xAgent['industries'] ?? []);
        $riskLevel = $xAgent['risk_level'] ?? null;
        $requiresApproval = (bool) ($xAgent['requires_approval'] ?? false);
        $memoryBlocks = (array) ($xAgent['memory_blocks'] ?? []);
        $mcpDependencies = (array) ($xAgent['mcp_dependencies'] ?? []);
        $toolsRequired = (array) ($xAgent['tools'] ?? []);
        $authorKeywords = (array) ($xAgent['trigger_keywords'] ?? []);
        $runAfter = (array) ($xAgent['run_after'] ?? []);
        $compatibility = $xAgent['compatibility'] ?? null;

        // Validate risk level
        if ($riskLevel === null || ! in_array($riskLevel, self::VALID_RISK_LEVELS, true)) {
            if ($riskLevel !== null) {
                $warnings[] = "Invalid risk_level '{$riskLevel}'; defaulting to 'standard'.";
            }
            $riskLevel = 'standard';
        }

        // Validate required fields
        $errors = array_merge($errors, $this->validateFrontmatter($frontmatter));

        // Extract trigger keywords
        $triggerKeywords = $this->extractTriggerKeywords(
            is_string($description) ? $description : '',
            $authorKeywords,
        );

        return new SkillParseResult(
            name: is_string($name) ? $name : null,
            description: is_string($description) ? $description : null,
            version: is_string($version) ? (string) $version : null,
            author: is_string($author) ? $author : null,
            license: is_string($license) ? $license : null,
            riskLevel: $riskLevel,
            industries: $industries,
            memoryBlocks: $memoryBlocks,
            mcpDependencies: $mcpDependencies,
            toolsRequired: $toolsRequired,
            triggerKeywords: $triggerKeywords,
            runAfter: $runAfter,
            requiresApproval: $requiresApproval,
            compatibility: is_string($compatibility) ? $compatibility : null,
            markdownBody: $markdownBody,
            warnings: $warnings,
            errors: $errors,
            rawFrontmatter: $frontmatter,
        );
    }

    public function validateFrontmatter(array $parsed): array
    {
        $errors = [];

        // Required: name
        $name = $parsed['name'] ?? null;
        if ($name === null || (is_string($name) && trim($name) === '')) {
            $errors[] = "Required field 'name' is missing.";
        } elseif (is_string($name)) {
            if (strlen($name) > self::MAX_NAME_LENGTH) {
                $errors[] = "Name must be ".self::MAX_NAME_LENGTH." characters or fewer (got ".strlen($name).").";
            }
            if (! preg_match(self::NAME_PATTERN, $name)) {
                $errors[] = "Name must be lowercase alphanumeric with hyphens (matching pattern: ".self::NAME_PATTERN.").";
            }
        }

        // Required: description
        $description = $parsed['description'] ?? null;
        if ($description === null || (is_string($description) && trim($description) === '')) {
            $errors[] = "Required field 'description' is missing.";
        } elseif (is_string($description)) {
            $trimmed = trim($description);
            if (strlen($trimmed) < self::MIN_DESCRIPTION_LENGTH) {
                $errors[] = "Description must be at least ".self::MIN_DESCRIPTION_LENGTH." characters (got ".strlen($trimmed).").";
            }
            if (strlen($trimmed) > self::MAX_DESCRIPTION_LENGTH) {
                $errors[] = "Description must be ".self::MAX_DESCRIPTION_LENGTH." characters or fewer (got ".strlen($trimmed).").";
            }
        }

        // Required: version
        $version = $parsed['version'] ?? null;
        if ($version === null || (is_string($version) && trim($version) === '')) {
            $errors[] = "Required field 'version' is missing.";
        } elseif (is_string($version) && ! preg_match(self::SEMVER_PATTERN, (string) $version)) {
            $errors[] = "Version must be valid semver (e.g., '1.0.0'); got '{$version}'.";
        }

        // Required: author
        $author = $parsed['author'] ?? null;
        if ($author === null || (is_string($author) && trim($author) === '')) {
            $errors[] = "Required field 'author' is missing.";
        }

        return $errors;
    }

    public function extractTriggerKeywords(string $description, array $authorKeywords): array
    {
        $maxKeywords = (int) config('agent.skills.trigger_keyword_extraction.max_keywords', 20);
        $minLength = (int) config('agent.skills.trigger_keyword_extraction.min_keyword_length', 3);

        // Tokenize: lowercase, split on whitespace/punctuation
        $text = mb_strtolower($description);
        $text = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', $text);
        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        // Remove stopwords
        $filtered = array_filter($tokens, function (string $token) use ($minLength) {
            return mb_strlen($token) >= $minLength && ! in_array($token, self::STOPWORDS, true);
        });

        // Count 1-grams
        $scores = [];
        foreach ($filtered as $token) {
            $scores[$token] = ($scores[$token] ?? 0) + 1;
        }

        // Extract 2-grams from filtered tokens
        $filteredValues = array_values($filtered);
        for ($i = 0; $i < count($filteredValues) - 1; $i++) {
            $bigram = $filteredValues[$i].' '.$filteredValues[$i + 1];
            if (mb_strlen($bigram) >= $minLength) {
                $scores[$bigram] = ($scores[$bigram] ?? 0) + 1;
            }
        }

        // Sort by frequency descending
        arsort($scores);

        // Take top N extracted keywords
        $extracted = array_keys(array_slice($scores, 0, $maxKeywords, true));

        // Normalize author keywords to lowercase
        $authorNormalized = array_map('mb_strtolower', array_filter($authorKeywords, 'is_string'));

        // Merge: author keywords first (priority), then extracted
        $merged = $authorNormalized;
        $seen = array_flip($authorNormalized);

        foreach ($extracted as $keyword) {
            if (! isset($seen[$keyword])) {
                $merged[] = $keyword;
                $seen[$keyword] = true;
            }
        }

        // Cap at max
        return array_slice($merged, 0, $maxKeywords);
    }

    private function errorResult(array $errors): SkillParseResult
    {
        return new SkillParseResult(
            name: null,
            description: null,
            version: null,
            author: null,
            license: null,
            riskLevel: 'standard',
            industries: [],
            memoryBlocks: [],
            mcpDependencies: [],
            toolsRequired: [],
            triggerKeywords: [],
            runAfter: [],
            requiresApproval: false,
            compatibility: null,
            markdownBody: '',
            warnings: [],
            errors: $errors,
            rawFrontmatter: [],
        );
    }
}
