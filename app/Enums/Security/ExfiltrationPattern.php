<?php

declare(strict_types=1);

namespace App\Enums\Security;

enum ExfiltrationPattern: string
{
    case SessionTokenEcho = 'session_token_echo';
    case ConversationReflection = 'conversation_reflection';
    case SystemPromptLeak = 'system_prompt_leak';
    case CredentialPattern = 'credential_pattern';
    case PiiEcho = 'pii_echo';
}
