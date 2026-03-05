<?php

namespace App\Enums\Runtime;

enum RuntimeArtifactType: string
{
    case File = 'file';
    case Screenshot = 'screenshot';
    case Log = 'log';
    case Report = 'report';
}
