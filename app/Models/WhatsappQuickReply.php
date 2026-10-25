<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A reply the merchant sends often, picked by typing «/» in the inbox. */
class WhatsappQuickReply extends Model
{
    protected $fillable = ['store_id', 'title', 'body'];
}
