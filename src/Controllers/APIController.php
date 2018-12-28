<?php

namespace App\Controllers;

use App\Models\Playlist;
use App\Models\Video;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;

use Google_Service_YouTube;
use Google_Client;

class APIController extends Controller{

    private $groups = [
        "mood" => [],
        "rock" => [],
        "pop" => [],
        "dance/electronic" => [],
        "metal" => [],
        "alt/indie" => [],
        "punk" => [],
        "r&b/soul" => [],
        "blues" => [],
        "hip-hop/rap" => [],
        "jazz" => [],
        "reggae" => [],
        "country/folk" => [],
        "latin" => [],
        "curator picks" => [],
        "misc" => [],
        "musician's channel" => [],
        "years & decades" => []
    ];
    public function isMobileDevice(){
        $aMobileUA = array(
            '/iphone/i' => 'iPhone',
            '/ipod/i' => 'iPod',
            '/ipad/i' => 'iPad',
            '/android/i' => 'Android',
            '/blackberry/i' => 'BlackBerry',
            '/webos/i' => 'Mobile'
        );

        //Return true if Mobile User Agent is detected
        foreach($aMobileUA as $sMobileKey => $sMobileOS){
            if(preg_match($sMobileKey, $_SERVER['HTTP_USER_AGENT'])){
                return true;
            }
        }
        //Otherwise return false..
        return false;
    }
    function searchParams($request, $response, $args){

        $reply = SharedFolder::orderBy('name')->get()->toArray();

        $folders = [];

        foreach($reply as $item){
            $folders[$item['folderId']] = $item['name'];
        }

        return $response->withJson($folders);
    }

    function time($request, $response, $args){
        return $response->withJson((int)number_format(microtime(true)*1000,0,'.',''));
    }

    function video($request, $response, $args) {
        $country = $this->view->getEnvironment()->getGlobals()['country'];

        $channel = $args['channel'];

        $grouped_playlists = Playlist::selectRaw('`group`, id, title, is_featured')
        ->where([
            ['status', 'active'],
//            ['is_featured', 1]
        ])->where(function($query) use ($country){
            $query->where('country', null);
            if(!is_null($country)) {
                $query->orWhere('country', $country);
            }
        })->orderBy('is_featured', 'desc')->get()->toArray();

        $reformatedData = array();


        foreach($grouped_playlists as $playlsit){
            if(!isset($reformatedData[strtolower($playlsit['group'])])) {
                $reformatedData[strtolower($playlsit['group'])] = $playlsit['id'];
            }
        }

        return $this->view->render($response, 'channel.twig', [
            'rand' => rand(0,2),
            'categories' => $reformatedData,
            'active_channel' => $args['channel']
        ]);
    }

    function channel($request, $response, $args){
        $country = $this->view->getEnvironment()->getGlobals()['country'];

        $channel = $args['channel'];

        $grouped_playlists = Playlist::selectRaw('`group`, id, title, is_featured')
        ->where([
            ['channel', $channel],
            ['status', 'active'],
//            ['is_featured', 1]
        ])->where(function($query) use ($country){
            $query->where('country', null);
            if(!is_null($country)) {
                $query->orWhere('country', $country);
            }
        })->orderBy('is_featured', 'desc')->get()->toArray();

        $reformatedData = array();


        foreach($grouped_playlists as $playlsit){
            if(!isset($reformatedData[strtolower($playlsit['group'])])) {
                $reformatedData[strtolower($playlsit['group'])] = $playlsit['id'];
            }
        }

        return $this->view->render($response, 'channel.twig', [
            'rand' => rand(0,2),
            'categories' => $reformatedData,
            'active_channel' => $args['channel']
        ]);

    }
    function apiGetChannel($request, $response, $args){

        $country = $this->view->getEnvironment()->getGlobals()['country'];
        $channel = $args['channel'];

        $gropedPlaylists = $this->getGroupedPlaylists($channel, $country);

        return json_encode($gropedPlaylists, JSON_PRETTY_PRINT);
    }
    function getGroupedPlaylists($channel, $country){
        $grouped_playlists = Playlist::select(['id', 'title', 'group'])
            ->where([
                ['channel', $channel],
                ['status', 'active']
            ])
            ->where(function($query) use ($country){
                $query->where('country', null);
                if(!is_null($country)) {
                    $query->orWhere('country', $country);
                }
            })
            ->orderBy('is_featured', 'desc')
            ->get()->toArray();

        $groups = $this->groups;

        foreach($grouped_playlists as $playlist){
            $group = strtolower($playlist['group']);
            if(!isset($group)) {
                $groups[$group] = [];
            }
            $id = $playlist['id'];
            unset($playlist['id']);
            unset($playlist['group']);

            $groups[$group][$id] = $playlist;
        }

        //delete empty groups
        foreach($groups as $key => $value){
            if(count($value) == 0){
                unset($groups[$key]);
            }
        }

        return $groups;
    }
    function player($request, $response, $args){
        $country = $this->view->getEnvironment()->getGlobals()['country'];
        $channel = $args['channel'];
        
        $gropedPlaylists = $this->getGroupedPlaylists($channel, $country);

        //get videos for initial playlist
        $videos = Video::select(['video_id','title', 'playlist_id', 'duration'])->where([
            ['playlist_id', $args['playlistId']],
            ['status', 'active']
        ])->orderBy('created_at', 'desc')->get()->toArray();

        $total_duration = 0;
        foreach($videos as $video){
            $total_duration += $video['duration'];
        }
        $playlistVideosObj = array();
        $playlistVideosObj[$args['playlistId']] = new \stdClass();

        $playlistVideosObj[$args['playlistId']]->videos = $videos;
        $playlistVideosObj[$args['playlistId']]->total_duration = $total_duration;

        //get current playlist details
        $playlistDetails = Playlist::where('id', $args['playlistId'])->first();

        return $this->view->render($response, 'player.twig', [
            'initialVideoJSObj' => json_encode($playlistVideosObj),
            'playlistsGrouped' => $gropedPlaylists,
            'pageDetails' => $playlistDetails,
            'playlistId' => $args['playlistId'],
            'active_channel' => $args['channel'],
            'initialGroup' => strtolower($playlistDetails->group),
            'mode' => 'player'
        ]);
    }

    function newHome($request, $response, $args) {
        if($this->isMobileDevice()){
            return $this->view->render($response, 'index-mobile.twig', []);
        }

        $country = $this->view->getEnvironment()->getGlobals()['country'];
        
        $gropedPlaylists = $this->getGroupedPlaylists("music", $country);
        //get videos for initial playlist
        $playlistid = 'e7ca73ea048c292bc7f3dec43cb99ca0';
        $videos = Video::select(['video_id','title', 'playlist_id', 'duration'])->where([
            ['playlist_id', $playlistid],
            ['status', 'active']
        ])->orderBy('created_at', 'desc')->get()->toArray();
        
        $total_duration = 0;
        foreach($videos as $video){
            $total_duration += $video['duration'];
        }
        $playlistVideosObj = array();
        $playlistVideosObj[$playlistid] = new \stdClass();

        $playlistVideosObj[$playlistid]->videos = $videos;
        $playlistVideosObj[$playlistid]->total_duration = $total_duration;

        //get current playlist details
        $playlistDetails = Playlist::where('id', $playlistid)->first();

        return $this->view->render($response, 'player.twig', [
            'active_channel' => 'music',
            'initialVideoJSObj' => json_encode($playlistVideosObj),
            'playlistsGrouped' => $gropedPlaylists,
            'pageDetails' => $playlistDetails,
            // 'playlistId' => $args['playlistId'],
            'playlistId' => $playlistid,
            'active_channel' => "video",
            'initialGroup' => strtolower($playlistDetails->group),
            'mode' => 'player'
        ]);
    }

    function playlist($request, $response, $args){

        $videos = Video::select(['video_id','title', 'playlist_id', 'duration'])->where([
            ['playlist_id', $args['playlistId']],
            ['status', 'active']
        ])->orderBy('created_at', 'desc')->get()->toArray();

        $total_duration = 0;
        foreach($videos as $video){
            $total_duration += $video['duration'];
        }
        $playlistVideosObj = array();
        $playlistVideosObj[$args['playlistId']] = new \stdClass();

        $playlistVideosObj[$args['playlistId']]->videos = $videos;
        $playlistVideosObj[$args['playlistId']]->total_duration = $total_duration;

        $videoDetails = Video::where('video_id', $args['videoId'])->first();

        return $this->view->render($response, 'player.twig', [
            'initialVideoJSObj' => json_encode($playlistVideosObj),
            'videos' => $playlistVideosObj[$args['playlistId']]->videos,
            'pageDetails' => $videoDetails,
            'videoId' => $args['videoId'],
            'playlistId' => $args['playlistId'],
            'active_channel' => $args['channel']
        ]);
    }
    function about($request, $response, $args){
        return $this->view->render($response, 'about.twig', [
        ]);
    }
    function contact($request, $response, $args){
        return $this->view->render($response, 'contact.twig', [
        ]);
    }
    function videos($request, $response, $args){

        $videos = Video::select(['video_id','title', 'playlist_id', 'duration'])->where([
            ['playlist_id', $args['playlistId']],
            ['status', 'active']
        ])->orderBy('created_at','desc')->get()->toArray();

        $total_duration = 0;
        foreach($videos as $video){
            $total_duration += $video['duration'];
        }

        $playlistDetails = Playlist::where('id', $args['playlistId'])->first();

        $playlistVideosObj = array();
        $playlistVideosObj[$args['playlistId']] = new \stdClass();

        $playlistVideosObj[$args['playlistId']]->videos = $videos;
        $playlistVideosObj[$args['playlistId']]->total_duration = $total_duration;
        $playlistVideosObj[$args['playlistId']]->title = $playlistDetails->title;

        return $response->withJson($playlistVideosObj);
    }
    function videosShort($request, $response, $args){

        $videos = Video::select(['video_id','title', 'playlist_id'])->where([
            ['playlist_id', $args['playlistId']],
            ['status', 'active']
        ])->orderBy('created_at','desc')->get()->toArray();

        return $response->withJson($videos);
    }
    function mobile($request, $response, $args){
        return $this->view->render($response, 'index-mobile.twig', []);
    }

    function homepage($request, $response, $args){

        if($this->isMobileDevice()){
            return $this->view->render($response, 'index-mobile.twig', []);
        }

        $country = $this->view->getEnvironment()->getGlobals()['country'];

        $trending = Playlist::where([
            ['is_trending', true],
            ['status', 'active']
        ])
            ->where(function($query) use ($country){
                $query->where('country', null);
                if(!is_null($country)) {
                    $query->orWhere('country', $country);
                }
            })
            ->get()->toArray();
        $latest_music = Playlist::where([
            ['is_latest', true],
            ['channel', 'music'],
            ['status', 'active']
        ])
            ->where(function($query) use ($country){
                $query->where('country', null);
                if(!is_null($country)) {
                    $query->orWhere('country', $country);
                }
            })
            ->take(6)->get()->toArray();
        $latest_tv = Playlist::where([
            ['is_latest', true],
            ['channel', 'tv'],
            ['status', 'active']
        ])
            ->where(function($query) use ($country){
                $query->where('country', null);
                if(!is_null($country)) {
                    $query->orWhere('country', $country);
                }
            })
            ->take(6)->get()->toArray();

        $latest_sports = Playlist::where([
            ['is_latest', true],
            ['channel', 'sports'],
            ['status', 'active']
        ])
            ->where(function($query) use ($country){
                $query->where('country', null);
                if(!is_null($country)) {
                    $query->orWhere('country', $country);
                }
            })
            ->take(6)->get()->toArray();

        $latest_kids = Playlist::where([
            ['is_latest', true],
            ['channel', 'kids'],
            ['status', 'active']
        ])
            ->where(function($query) use ($country){
                $query->where('country', null);
                if(!is_null($country)) {
                    $query->orWhere('country', $country);
                }
            })
            ->take(6)->get()->toArray();

        $latest_news = Playlist::where([
            ['is_latest', true],
            ['channel', 'news'],
            ['status', 'active']
        ])
            ->where(function($query) use ($country){
                $query->where('country', null);
                if(!is_null($country)) {
                    $query->orWhere('country', $country);
                }
            })
            ->take(6)->get()->toArray();

        $latest_vlogs = Playlist::where([
            ['is_latest', true],
            ['channel', 'vlogs'],
            ['status', 'active']
        ])
            ->where(function($query) use ($country){
                $query->where('country', null);
                if(!is_null($country)) {
                    $query->orWhere('country', $country);
                }
            })
            ->take(6)->get()->toArray();


        return $this->view->render($response, 'index.twig', [
            'rand' => rand(0,2),
            'trending' => $trending,
            'latest_music' => $latest_music,
            'latest_tv' => $latest_tv,
            'latest_sports' => $latest_sports,
            'latest_kids' => $latest_kids,
            'latest_news' => $latest_news,
            'latest_vlogs' => $latest_vlogs
        ]);
    }
}
