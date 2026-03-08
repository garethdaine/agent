<?php

declare(strict_types=1);

namespace App\DTOs\Messenger;

enum ThreadingStrategy: string
{
    case Native = 'native';
    case ReplyTo = 'reply_to';
    case ChannelTopic = 'channel_topic';
    case None = 'none';
}
