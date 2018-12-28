<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Playlist extends Model{
    protected $table = 'playlist';
    public $incrementing = false;


    protected $fillable = [
        "id",
        "title",
        "thumbnail",
        "group",
        "country",
        "channel",
        "description",
        "ytSourceId",
        "created_at",
        "updated_at"
    ];

    public function videos()
    {
        return $this->hasMany('App\Models\Video', 'playlist_id', 'id');
    }
}