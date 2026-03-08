<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\DTOs\Security\SanitizationResult;
use App\Support\Security\TokenEstimator;

class ContentSanitizer
{
    public function __construct(
        private readonly SecurityConfigProvider $configProvider,
        private readonly TokenEstimator $tokenEstimator,
    ) {}

    public function sanitize(string $content, string $sourceIdentifier, ?int $accountId = null): SanitizationResult
    {
        if ($content === '') {
            return new SanitizationResult(
                content: '',
                sanitized: false,
                truncated: false,
                originalTokenCount: 0,
            );
        }

        $strippedElements = [];
        $sanitized = false;

        if ($this->configProvider->get('strip_html', $accountId)) {
            [$content, $strippedElements] = $this->stripHtml($content);
            if (! empty($strippedElements)) {
                $sanitized = true;
            }
        }

        $maxTokens = (int) $this->configProvider->get('tool_result_max_tokens', $accountId);
        $truncationResult = $this->tokenEstimator->truncateToTokenBudget($content, $maxTokens);
        $truncated = $truncationResult['truncated'];
        $originalTokenCount = $truncationResult['originalTokens'];

        if ($truncated) {
            $content = $truncationResult['content'].'[Content truncated: exceeded '.$maxTokens.' token limit]';
        } else {
            $content = $truncationResult['content'];
        }

        if ($this->configProvider->get('content_wrapping_markers', $accountId)) {
            $content = '<external_content source="'.$sourceIdentifier.'">'.$content.'</external_content>';
        }

        return new SanitizationResult(
            content: $content,
            sanitized: $sanitized,
            truncated: $truncated,
            originalTokenCount: $originalTokenCount,
            strippedElements: $strippedElements,
        );
    }

    /**
     * @return array{0: string, 1: array<string>}
     */
    private function stripHtml(string $content): array
    {
        $strippedElements = [];
        $original = $content;

        // Remove script blocks
        $result = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content);
        if ($result !== $content) {
            $strippedElements[] = 'script';
            $content = $result;
        }

        // Remove style blocks
        $result = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $content);
        if ($result !== $content) {
            $strippedElements[] = 'style';
            $content = $result;
        }

        // Remove event handlers
        $result = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $content);
        if ($result !== $content) {
            $strippedElements[] = 'event_handler';
            $content = $result;
        }

        // Remove remaining HTML tags
        $result = strip_tags($content);
        if ($result !== $content) {
            if (! in_array('html_tags', $strippedElements, true)) {
                $strippedElements[] = 'html_tags';
            }
            $content = $result;
        }

        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return [$content, $strippedElements];
    }
}
