<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Navegador vinculado al canal push de un usuario (CHG-067). */
class PushSubscription extends Model
{
    protected $fillable = ['user_id', 'endpoint', 'public_key', 'auth_token', 'content_encoding'];

    protected $hidden = ['public_key', 'auth_token'];
}
