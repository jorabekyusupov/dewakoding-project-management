<?php

namespace App\Models;

use App\Policies\UserAuthLogPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;



#[UsePolicy(UserAuthLogPolicy::class)]
class UserAuthLog extends Model
{
    protected $table = 'user_auth_log';

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'login_at',
        'logout_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
