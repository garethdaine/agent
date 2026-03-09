<?php

declare(strict_types=1);

namespace App\Models\Security;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountSecurityOverride extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'account_id',
        'config_key',
        'config_value',
        'created_by',
    ];
}
