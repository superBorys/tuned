<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YTSource extends Model{
    protected $table = 'ytSource';
    public $incrementing = false;

    protected $fillable = [
        "id",
        "title",
        "group",
        "channel",
        "ytSource",
        "type",
        "created_at",
        "updated_at"
    ];
}