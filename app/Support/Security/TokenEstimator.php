<?php

declare(strict_types=1);

namespace App\Support\Security;

use Yethee\Tiktoken\EncoderProvider;

class TokenEstimator
{
    public function fastEstimate(string $content): int
    {
        return (int) ceil(mb_strlen($content) / 4);
    }

    public function preciseEstimate(string $content): int
    {
        if ($content === '') {
            return 0;
        }

        $provider = new EncoderProvider;
        $encoder = $provider->getForModel('gpt-4');

        return count($encoder->encode($content));
    }

    /**
     * @return array{content: string, truncated: bool, originalTokens: int}
     */
    public function truncateToTokenBudget(string $content, int $budget): array
    {
        $originalTokens = $this->preciseEstimate($content);

        if ($originalTokens <= $budget) {
            return [
                'content' => $content,
                'truncated' => false,
                'originalTokens' => $originalTokens,
            ];
        }

        $provider = new EncoderProvider;
        $encoder = $provider->getForModel('gpt-4');

        $tokens = $encoder->encode($content);
        $truncatedTokens = array_slice($tokens, 0, $budget);
        $truncatedContent = $encoder->decode($truncatedTokens);

        return [
            'content' => $truncatedContent,
            'truncated' => true,
            'originalTokens' => $originalTokens,
        ];
    }
}
