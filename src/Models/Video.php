<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Video extends Model{
    protected $table = 'video';
    public $incrementing = false;

//    protected $primaryKey = 'MLS_NUM';

    protected $fillable = [
        "id",
        "video_id",
        "spotify_id",
        "title",
        "thumbnail",
        "duration",
        "playlist_id",
        "created_at",
        "updated_at"
    ];

    public function playlist()
    {
        return $this->belongsTo('Playlist', 'playlist_id', 'id');
    }
}