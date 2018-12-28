<?php

namespace App\Controllers;

use App\Models\Playlist;
use App\Models\Video;

use App\Models\YTSource;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;

use Google_Service_YouTube;
use Google_Client;
use SpotifyWebAPI;

class ImporterController extends Controller{

    private $service = null;

    private function isBlocked($blockedList){
        $whiteList = ['BG', 'CZ', 'DK', 'DE', 'EE', 'IE', 'EL', 'ES', 'FR', 'HR', 'IT', 'CY', 'LV', 'LT', 'LU', 'HU', 'MT', 'NL', 'AT', 'PL', 'PT', 'RO', 'SI', 'SK', 'FI', 'SE', 'UK', 'IS', 'LI', 'NO', 'CH', 'US', 'CA'];

        return (count(array_intersect($blockedList, $whiteList)) > 0);
    }

    private function normalizeTitle($title){
        return preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $title);
    }

    private function dateIntervalToSeconds($dateinterval) {
        return ($dateinterval->y * 365 * 24 * 60 * 60) +
        ($dateinterval->m * 30 * 24 * 60 * 60) +
        ($dateinterval->d * 24 * 60 * 60) +
        ($dateinterval->h * 60 * 60) +
        ($dateinterval->i * 60) +
        $dateinterval->s;
    }
    private function getSpotifyApi(){
        $settings = $this->container->get('settings');
        $appSettings = $settings['spotifyApp'];


        $session = new SpotifyWebAPI\Session(
            $appSettings['clientId'],
            $appSettings['clientSecret'],
            $settings['baseUrl'].'/pull-from-spotify'
        );

        $api = new SpotifyWebAPI\SpotifyWebAPI();

        if (file_exists($appSettings['credentialsPath'])) {
            $token = json_decode(file_get_contents($appSettings['credentialsPath']), true);

            $session->refreshAccessToken($token['refreshToken']);

            $accessToken = $session->getAccessToken();
            $tokenExpiration = $session->getTokenExpiration();

            file_put_contents($appSettings['credentialsPath'], json_encode([
                'accessToken' => $accessToken,
                'refreshToken' => $token['refreshToken'],
                'tokenExpiration' => $tokenExpiration
            ]));

            $api->setAccessToken($accessToken);
        } else {
            if (isset($_GET['code'])) {
                $session->requestAccessToken($_GET['code']);
                $accessToken = $session->getAccessToken();
                $refreshToken = $session->getRefreshToken();
                $tokenExpiration = $session->getTokenExpiration();
                $api->setAccessToken($accessToken);

                file_put_contents($appSettings['credentialsPath'], json_encode([
                    'accessToken' => $accessToken,
                    'refreshToken' => $refreshToken,
                    'tokenExpiration' => $tokenExpiration
                ]));

                echo "Run me again now ;)\n";

                die();
            } else {
                $options = [
                    'scope' => [
                        'user-read-email',
                        'playlist-read-private',
                        'playlist-read-collaborative'
                    ],
                ];

                echo 'Location: ' . $session->getAuthorizeUrl($options);

                die();
            }
        }

        return $api;
    }
    private function getGoogleClient() {
        $appSettings = $this->container->get('settings')['googleApp'];

        $client = new Google_Client();
        $client->setApplicationName($appSettings['appName']);
        $client->setScopes(implode(' ', array(Google_Service_YouTube::YOUTUBE_READONLY)));
        $client->setAuthConfig($appSettings['clientSecretPath']);
        $client->setAccessType('offline');

        // Load previously authorized credentials from a file.
        if (file_exists($appSettings['credentialsPath'])) {
            $accessToken = json_decode(file_get_contents($appSettings['credentialsPath']), true);
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

            // Store the credentials to disk.
            file_put_contents($appSettings['credentialsPath'], json_encode($accessToken));
            printf("Credentials saved to %s\n", $appSettings['credentialsPath']);
        }
        $client->setAccessToken($accessToken);

        // Refresh the token if it's expired.
        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($accessToken['refresh_token']);
            $newAccessToken = $client->getAccessToken();
            $newAccessToken['refresh_token'] = $accessToken['refresh_token'];
            file_put_contents($appSettings['credentialsPath'], json_encode($newAccessToken));
        }
        return $client;
    }

    function getVideoDetailsAndSave($videoIds, $videoRows, $hdOnly = false, $ignoreLessThan = 0, $ignoreViewsLessThan = 0, $saveViewCount = false){
        $videoSaved = 0;

        if(count($videoIds) == 0) return 0;
        $videosSearchQuery = array(
            'id' => join(',', $videoIds),
            'maxResults' => 50
        );

        do {
            //get videos details
            $videosDetailsResults = $this->service->videos->listVideos(
                'contentDetails,status'.($ignoreLessThan > 0 || $saveViewCount ? ',statistics' : ''),
                $videosSearchQuery
            );

            if ($videosDetailsResults->nextPageToken) {
                $videosSearchQuery['pageToken'] = $videosDetailsResults->nextPageToken;
            }

            if ($videosDetailsResults->count() != 0) {

                foreach ($videosDetailsResults->getItems() as $videoDetails) {

                    if (!$videoDetails->getStatus()->embeddable) {
                        echo 'Video ' . $videoDetails->getId() . " is not embeddable\n";
                        continue;
                    }
                    if ($videoDetails->getStatus()->getPrivacyStatus() == 'private') {
                        echo "Skipping private video: " . $videoDetails->getId() . "\n";
                        continue;
                    }
                    if($hdOnly && $videoDetails->getContentDetails()['definition'] != 'hd'){
                        echo "Skipping sd video: " . $videoDetails->getId() . "\n";
                        continue;
                    }

                    $contentDetails = $videoDetails->getContentDetails();

                    if (!is_null($contentDetails->getRegionRestriction()->blocked)) {
                        echo 'Video ' . $videoDetails->getId() . " has region restrictions";

                        if ($this->isBlocked($contentDetails->getRegionRestriction()->blocked) === false) {
                            echo " (not from whitelist)\n";
                        } else {
                            echo "\n";
                            continue;
                        }
                    }

                    $interval = new \DateInterval($videoDetails->getContentDetails()['duration']);
                    $seconds = $this->dateIntervalToSeconds($interval);
                    if (!$seconds) {
                        echo 'Please check if all is good with video with id: ' . $videoDetails->getId() . "\n";
                        continue;
                    }
                    if($seconds < $ignoreLessThan){
                        echo 'Video ' . $videoDetails->getId() . " was declined because of duration less than ".$ignoreLessThan."\n";
                        continue;
                    }
                    if((int)$videoDetails->getStatistics()['viewCount'] < $ignoreViewsLessThan){
                        echo 'Video ' . $videoDetails->getId() . " was declined because of views less than ".$ignoreViewsLessThan."\n";
                        continue;
                    }
                    if($videoDetails->getStatistics() && isset($videoDetails->getStatistics()['viewCount'])) {
                        $videoRows[$videoDetails->getId()]->view_count = (int)$videoDetails->getStatistics()['viewCount'];
                    }
                    $videoRows[$videoDetails->getId()]->duration = $seconds;
                    $videoRows[$videoDetails->getId()]->save();
                    $videoSaved++;
                }

            } else {
                echo "A problem found, nothing returned for videos details request: " . implode(",", $videoIds) . "\n";
            }
        } while ($videosDetailsResults->nextPageToken);


        return $videoSaved;
    }

    private function pullFromChannel($source)
    {
        $sourceIds = explode('|', $source->ytSource);

        if(count($sourceIds) > 1){
            $multiChannel = true;
        } else {
            $multiChannel = false;
        }

        $thumbnail = null;
        $ytCrawlIds = array();

        for($i = 0; $i < count($sourceIds); $i++) {
            if ($source->type == 'channel-to-playlist') {
                $channelInfo = $this->service->channels->listChannels("snippet", array(
                    'id' => $sourceIds[$i]
                ));
            } else if ($source->type == 'user-to-playlist') {
                $channelInfo = $this->service->channels->listChannels("snippet", array(
                    'forUsername' => $sourceIds[$i]
                ));
            } else if ($source->type == 'playlist-to-playlist') {
                $channelInfo = $this->service->playlists->listPlaylists("snippet", array(
                    'id' => $sourceIds[$i]
                ));
            }

            if($channelInfo->count() == 0){
                echo "Could not find associated youtube channel with source id: ".$sourceIds[$i]."\n";
                return;
            }
            $details = $channelInfo->getItems()[0];

            $ytCrawlIds[] = $details->getId();
            if($i == 0){
                $thumbnail = $details->getSnippet()->getThumbnails()->getHigh()->getUrl();
            }
        }


        $playlistRow = new Playlist();
        $playlistRow->id = md5($source->ytSource);
        $playlistRow->ytSourceId = $source->id;
        $playlistRow->thumbnail = $thumbnail;
        $playlistRow->channel = $source->channel;

        if($source->country !== null){
            $playlistRow->country = $source->country;
        }

        if(strval($source->featured) !== "0"){
            $playlistRow->is_featured = 1;
        } else {
            $playlistRow->is_featured = 0;
        }

        $playlistRow->title = $this->normalizeTitle($source->title);
        $playlistRow->group = $source->group;
        $playlistRow->save();

        //get videos
        foreach ($ytCrawlIds as $crawlId) {
            if ($source->type == 'playlist-to-playlist') {
                $videosSearchQuery = array(
                    'playlistId' => $crawlId,
                    'maxResults' => 50
                );
            } else {
                $videosSearchQuery = array(
                    'channelId' => $crawlId,
                    'maxResults' => 50,
                    'order' => 'date'
                );

                if($multiChannel){
                    $notOlderThan = 48; //default hours
                    if(!is_null($source->not_older_than)){
                        $notOlderThan = $source->not_older_than;
                    }
                    $objDateTime = new \DateTime('NOW');
                    $objDateTime->modify( '-'.strval($notOlderThan).' hours' );
                    $videosSearchQuery['publishedAfter'] = $objDateTime->format(\DateTime::RFC3339);
                }
            }

            $maxCount = 50;
            $count = 0;
            $videoSaved = 0;
            do {
                $videoIds = array();

                if ($source->type == 'playlist-to-playlist') {
                    $videoResults = $this->service->playlistItems->listPlaylistItems("snippet,status", $videosSearchQuery);
                } else {
                    $videoResults = $this->service->search->listSearch("snippet", $videosSearchQuery);
                }

                $count += $videoResults->count();

                if ($videoResults->nextPageToken) {
                    $videosSearchQuery['pageToken'] = $videoResults->nextPageToken;
                }

                //search videos
                $videoRows = [];
                foreach ($videoResults->getItems() as $video) {
                    //                $status = $video->getStatus();
                    //                if($status->getPrivacyStatus() == 'private'){
                    //                    echo "Skipping private video: ".$video->getSnippet()->getResourceId()['videoId']."\n";
                    //                    continue;
                    //                }
                    $videoRow = new Video();
                    $videoRow->playlist_id = $playlistRow->id;
                    if ($source->type == 'playlist-to-playlist') {
                        $videoRow->video_id = $video->getSnippet()->getResourceId()['videoId'];
                    } else {
                        $videoRow->video_id = $video->getId()['videoId'];
                    }
                    $videoRow->title = $this->normalizeTitle($video['snippet']['title']);

                    $publishedTime = new \DateTime($video['snippet']['publishedAt']);

                    $videoRow->created_at = $publishedTime->getTimestamp();

                    $thumbnails = $video->getSnippet()->getThumbnails();
                    $videoRow->thumbnail = $thumbnails ? $thumbnails->getHigh()->getUrl() : '';

                    $videoIds[] = $videoRow->video_id;
                    $videoRows[$videoRow->video_id] = $videoRow;
                }

                $videoSaved += $this->getVideoDetailsAndSave($videoIds, $videoRows, false, 0, 0, $multiChannel && !is_null($source->no_of_most_viewed));

                if (!$videoResults->nextPageToken) {
                    echo 'Imported data from: ' . $playlistRow->title . "(" . $videoSaved . ")\n";
                }

                if ($videoSaved >= $maxCount && !isset($videosSearchQuery['publishedAfter'])) {
                    echo 'Imported data from: ' . $playlistRow->title . ", took maximum allowed count: " . $maxCount . "(" . $videoSaved . ")\n";
                    break;
                }

            } while ($videoResults->nextPageToken);
        }
    }

    private function channelToDB($channelName, $channelId)
    {
        $apiPoints = 0;

        $playlistSearchQuery = array(
            'channelId' => $channelId,
            'maxResults' => 50
        );

        do {
            $results = $this->service->playlists->listPlaylists("snippet", $playlistSearchQuery);
            $apiPoints += 2;

            if ($results->nextPageToken) {
                $playlistSearchQuery['pageToken'] = $results->nextPageToken;
            }

            foreach ($results->getItems() as $playlist) {
//                if ($playlist->getSnippet()->title == 'Frontpage Videos') {
//                    $videosSearchQuery = array(
//                        'playlistId' => $playlist->getId(),
//                        'maxResults' => 50
//                    );
//                    $videoIds = array();
//                    $playlistItemsResponse = $youtube->playlistItems->listPlaylistItems('snippet', $videosSearchQuery);
//                    foreach ($playlistItemsResponse->getItems() as $video) {
//                        $result->frontpageVideos[] = $video->getSnippet()->getResourceId()['videoId'];
//                    }
//                    echo "Frontpage Videos operated!\n";
//                    continue;
//                }

                $playlistRow = new Playlist();

                $playlistRow->id = $playlist->getId();
                $playlistRow->description = $playlist->getSnippet()->description;
                $playlistRow->thumbnail = $playlist->getSnippet()->getThumbnails()->getHigh()->getUrl();
                $playlistRow->channel = $channelName;

                $title_parts = explode(':', $playlist->getSnippet()->title);

                $possibleTags = ['featured', 'first', 'trending', 'latest'];

                for($k = 0; $k < count($possibleTags); $k++) {
                    foreach ($possibleTags as $tag) {
                        if(strtolower(trim($title_parts[0])) == $tag){
                            $playlistRow->{'is_'.$tag} = 1;
                            array_shift($title_parts);
                            break;
                        }
                    }
                }

                //check if we have countries
                if (count($title_parts) == 3) {
                    $playlistRow->country = trim($title_parts[0]);
                    $group = trim($title_parts[1]);
                    $playlistTitle = trim($title_parts[2]);
                } else if (count($title_parts) == 2) {
                    $playlistTitle = trim($title_parts[1]);
                    $group = trim($title_parts[0]);
                } else {
                    echo "Wrong playlist name: " . $playlist->getSnippet()->title . "\n";
                    continue;
                }

                $playlistRow->title = $this->normalizeTitle($playlistTitle);

                $playlistRow->group = $group;

                $playlistRow->save();

                //get videos
                $videosSearchQuery = array(
                    'playlistId' => $playlist->getId(),
                    'maxResults' => 50
                );

                do {
                    $videoIds = array();

                    $videoResults = $this->service->playlistItems->listPlaylistItems("snippet,status", $videosSearchQuery);
                    $apiPoints += 2;

                    if ($videoResults->nextPageToken) {
                        $videosSearchQuery['pageToken'] = $videoResults->nextPageToken;
                    }

                    //search videos
                    $videoRows = [];
                    foreach ($videoResults->getItems() as $video) {
                        $status = $video->getStatus();
                        if($status->getPrivacyStatus() == 'private'){
                            echo "Skipping private video: ".$video->getSnippet()->getResourceId()['videoId']."\n";
                            continue;
                        }

                        //check if we have a video for this song already
                        $check = Video::where('video_id', $video->getSnippet()->getResourceId()['videoId'])->get();

                        if($check->count() > 0){
                            $found = null;
                            foreach ($check as $v){
                                if($v->playlist_id == $playlistRow->id){
                                    $found = $v;
                                    break;
                                }
                            }

                            if(!is_null($found)){
                                $found->status = 'pending';
                                $found->save();
                            } else {
                                $new_video = $check[0]->replicate();
                                $new_video->status = 'pending';
                                $new_video->playlist_id = $playlistRow->id;
                                $new_video->save();
                            }

                            echo "We have info for video".(is_null($found) ? ' (replicated)' : '').": ".$video->getSnippet()->getResourceId()['videoId']."\n";
                            continue;
                        }

                        $videoRow = new Video();
                        $videoRow->playlist_id = $playlist->getId();
                        $videoRow->video_id = $video->getSnippet()->getResourceId()['videoId'];
                        $videoRow->title = $this->normalizeTitle($video['snippet']['title']);

                        $thumbnails = $video->getSnippet()->getThumbnails();
                        $videoRow->thumbnail = $thumbnails ? $thumbnails->getHigh()->getUrl() : '';

                        $videoIds[] = $videoRow->video_id;
                        $videoRows[$videoRow->video_id] = $videoRow;
                    }

                    //get videos details
                    $apiPoints += 2;

                    $this->getVideoDetailsAndSave($videoIds, $videoRows);

                    if (!$videoResults->nextPageToken) {
                        echo 'Operated playlist: ' . $playlistTitle . "\n";
                    }

                } while ($videoResults->nextPageToken);
            }
        } while ($results->nextPageToken);

        echo "Used ".$apiPoints." API points\n";
    }

    function cliImport($request, $response, $args){
        ob_end_flush();
        $time_start = microtime(true);

        $client = $this->getGoogleClient();
        $this->service = new Google_Service_YouTube($client);

        $channels = $this->container->get('settings')['googleApp']['channels'];


        if(in_array($args['type'], array_keys($channels))){

//            $this->deletePendingPlaylistCopierItems()
            $playlistIds = Playlist::where([
                ['status','pending'],
                ['channel', $args['type']]
            ])->whereNull('ytSourceId')->get()->pluck('id');

            Playlist::where([
                ['status','pending'],
                ['channel', $args['type']]
            ])->whereNull('ytSourceId')->delete();
            Video::where('status', 'pending')->whereIn('playlist_id', $playlistIds)->delete();


            //fill by pending
            $this->channelToDB($args['type'], $channels[$args['type']]);


            //delete active
            $playlistIds = Playlist::where([
                ['status','active'],
                ['channel', $args['type']]
            ])->whereNull('ytSourceId')->get()->pluck('id');

            Playlist::where([
                ['status','active'],
                ['channel', $args['type']]
            ])->whereNull('ytSourceId')->delete();

            Video::where('status', 'active')->whereIn('playlist_id', $playlistIds)->delete();


            //update, pending -> active
            $playlistIds = Playlist::where([
                ['status','pending'],
                ['channel', $args['type']]
            ])->get()->pluck('id');

            Playlist::where([
                ['status','pending'],
                ['channel', $args['type']]
            ])->whereNull('ytSourceId')->update([
                'status' => 'active'
            ]);

            Video::where('status', 'pending')
                ->whereIn('playlist_id', $playlistIds)
                ->update([
                    'status' => 'active'
                ]);
        }

        $time_end = microtime(true);
        $time = $time_end - $time_start;

        echo "Execution time $time seconds!\n";

        return "\n";
    }

    function removePlaylistsDeletedFromCopier(){
        //remove playlists & videos for not existing sources
        $sourcesIds = YTSource::get()->pluck('id');

        $playlistIds = Playlist::whereNotNull('ytSourceId')->whereNotIn('ytSourceId', $sourcesIds)->get()->pluck('id');

        Video::whereIn('playlist_id', $playlistIds)->delete();
        Playlist::whereIn('id', $playlistIds)->delete();

    }
    function deletePendingPlaylistCopierItems($sourceId, $type = null){

        //delete pending
        $idsFilter = [
            ['status','pending'],
            ['ytSourceId', $sourceId]
        ];

        $playlistFilter = [
            ['status','pending'],
            ['ytSourceId', $sourceId]
        ];

        if(!is_null($type)){
            $idsFilter[] = ['channel', $type];
            $playlistFilter[] = ['channel', $type];
        }
        $playlistIds = Playlist::where($idsFilter)->get()->pluck('id');

        Playlist::where($playlistFilter)->delete();

        Video::where('status', 'pending')->whereIn('playlist_id', $playlistIds)->delete();
    }
    function makePendingActive($sourceId, $leaveTopN = null){
        $playlistIds = Playlist::where([
            ['status','active'],
            ['ytSourceId', $sourceId]
        ])->get()->pluck('id');

        //leave top N videos if needed, sort by view_count to get top
        if(!is_null($leaveTopN)){
            $videoIds = Video::where('status','pending')
                ->whereIn('playlist_id', $playlistIds)
                ->orderBy('view_count','desc')
                ->take($leaveTopN)
                ->get()->pluck('id');

            Video::where('status','pending')
                ->whereNotIn('id', $videoIds)
                ->whereIn('playlist_id', $playlistIds)->delete();
        }

        //delete active playlists and videos
        Playlist::where([
            ['status','active'],
            ['ytSourceId', $sourceId]
        ])->delete();
        Video::where('status', 'active')->whereIn('playlist_id', $playlistIds)->delete();

        //update, pending -> active
        $playlistIds = Playlist::where([
            ['status','pending'],
            ['ytSourceId', $sourceId]
        ])->get()->pluck('id');

        Playlist::where([
            ['status','pending'],
            ['ytSourceId', $sourceId]
        ])->update([
            'status' => 'active'
        ]);

        Video::where('status', 'pending')
            ->whereIn('playlist_id', $playlistIds)
            ->update([
                'status' => 'active'
            ]);
    }
    function cliPullImport($request, $response, $args){
        ob_end_flush();
        $time_start = microtime(true);

        $this->removePlaylistsDeletedFromCopier();

        $client = $this->getGoogleClient();
        $this->service = new Google_Service_YouTube($client);


        Playlist::where([
            ['status','active'],
            ['channel', $args['type']]
        ])->whereNotNull('ytSourceId')->delete();

        $sources = YTSource::where([
            ['type', '<>', 'spotify-to-playlist']
        ])->get();

        foreach($sources as $source){
            $sourceIds = explode('|', $source->ytSource);

            if(count($sourceIds) > 1){
                $multiChannel = true;
            } else {
                $multiChannel = false;
            }
            $this->deletePendingPlaylistCopierItems($source->id);

            $this->pullFromChannel($source);

            $this->makePendingActive($source->id, $multiChannel && !is_null($source->no_of_most_viewed) ? $source->no_of_most_viewed : null);
        }

        $time_end = microtime(true);
        $time = $time_end - $time_start;

        echo "Execution time $time seconds!\n";

        return "\n";
    }

    function getSpotifyPlaylistId($name){
        return md5('spotify-'.$name);
    }

    function compareVideoThumbs($videoId){
        $image1 = new \imagick();
        $image2 = new \imagick();

        // set the fuzz factor (must be done BEFORE reading in the images)
        $image1->SetOption('fuzz', '5%');

        $img1 = @fopen('https://img.youtube.com/vi/'.$videoId.'/1.jpg', 'rb');
        $img2 = @fopen('https://img.youtube.com/vi/'.$videoId.'/2.jpg', 'rb');
        if (!$img1 || !$img2) {
            return null;
        } else {
            // read in the images
            $image1->readImageFile($img1);
            $image2->readImageFile($img2);

            // compare the images using METRIC=1 (Absolute Error)
            $result = $image1->compareImages($image2, 1);

            return $result[1];
        }

        return null;
    }
    function cliPullFromSpotify($request, $response, $args){
        ob_end_flush();
        $time_start = microtime(true);

        $this->removePlaylistsDeletedFromCopier();

        $api = $this->getSpotifyApi();

        $client = $this->getGoogleClient();
        $this->service = new Google_Service_YouTube($client);

        $sources = YTSource::where('type', 'spotify-to-playlist')->get();


        $offset = 0;
        $limit = 50;

        do {
            $spotifyPlaylists = $api->getMyPlaylists(['limit' => $limit, 'offset' => $offset]);

            foreach ($spotifyPlaylists->items as $sPlaylist){
                $sourceFound = null;

                foreach ($sources as $source){
                    if(strtolower($sPlaylist->name) == strtolower($source->ytSource)){
                        $sourceFound = $source;
                        break;
                    }
                }

                if(is_null($sourceFound)){
                    echo "Skipping ".$sPlaylist->name."\n";
                    continue;
                }

                echo "Coping ".$sPlaylist->name."\n";

                //delete pending
                $this->deletePendingPlaylistCopierItems($sourceFound->id);

                //creating playlist
                $playlistRow = new Playlist();
                $playlistRow->id = $this->getSpotifyPlaylistId($source->ytSource);
                $playlistRow->ytSourceId = $source->id;
                $playlistRow->thumbnail = $sPlaylist->images[0]->url;
                $playlistRow->channel = $source->channel;

                if($source->country !== null){
                    $playlistRow->country = $source->country;
                }

                if(strval($source->featured) !== "0"){
                    $playlistRow->is_featured = 1;
                } else {
                    $playlistRow->is_featured = 0;
                }

                $playlistRow->title = $this->normalizeTitle($source->title);
                $playlistRow->group = $source->group;
                $playlistRow->save();

                //copying songs
                $tracksLimit = 50; $tracksOffset = 0;
                do {
                    $videoIds = array();
                    $videoRows = [];


                    $songs = $api->getUserPlaylistTracks(
                        $sPlaylist->owner->id,
                        $sPlaylist->id,
                        ['limit' => $tracksLimit, 'offset' => $tracksOffset]
                    );

                    foreach ($songs->items as $song){
                        $artists = [];
                        foreach ($song->track->artists as $artist){
                            $artists[] = $artist->name;
                        }

                        $name = $song->track->name;

                        $query = implode(" ", $artists).' - '.$name;

                        //check if we have a video for this song already
                        $check = Video::where('spotify_id', $song->track->id)->get();

                        if($check->count() > 0){
                            $found = null;
                            foreach ($check as $v){
                                if($v->playlist_id == $playlistRow->id){
                                    $found = $v;
                                    break;
                                }
                            }

                            if(!is_null($found)){
                                $found->status = 'pending';
                                $found->save();
                            } else {
                                $new_video = $check[0]->replicate();
                                $new_video->status = 'pending';
                                $new_video->playlist_id = $playlistRow->id;
                                $new_video->save();
                            }

                            echo "We have video for".(is_null($found) ? ' (replicated)' : '').": ".$query."\n";
                            continue;
                        }

                        $videosSearchQuery = array(
                            'q' => $query,
                            'chart' => 'mostPopular',
                            'type' => 'video',
                            'maxResults' => 5
                        );

                        $videoResults = $this->service->search->listSearch("snippet", $videosSearchQuery);

                        if($videoResults->count() == 0){
                            echo "Could not find video for: ".$query."\n";
                        } else {
                            $videoResults = $videoResults->toSimpleObject()->items;
                            $removeWithWords = ['audio', 'cover', 'full album', 'reaction'];
                            $preferWithWords = ['official'];

                            //remove with 'anti' words

                            foreach ($videoResults as $key => $value){
                                foreach ($removeWithWords as $word){
                                    if (strpos(strtolower($value['snippet']['title']), strtolower($word)) !== false) {
                                        unset($videoResults[$key]);
                                        break;
                                    }
                                }
                            }

                            $video = null;
                            foreach ($videoResults as $key => $value){
                                $resultFound = false;
                                foreach ($preferWithWords as $word){
                                    if (strpos(strtolower($value['snippet']['title']), strtolower($word)) !== false) {
                                        $video = $value;
                                        $resultFound = true;
                                    }
                                }
                                if($resultFound) break;
                            }

                            if(is_null($video) && count($videoResults) > 0) {
                                $video = array_shift($videoResults);
                            }

                            //check if video has static picture
                            $comparisonResult = null;
                            do {
                                $comparisonResult = $this->compareVideoThumbs($video['id']['videoId']);
                                if($comparisonResult < 2500){
                                    if(count($videoResults) > 0) {
                                        $video = array_shift($videoResults);
                                        echo ".";
                                    } else {
                                        $video = null;
                                        echo "-";
                                    }
                                }
                            } while (!is_null($video) && (is_null($comparisonResult) || $comparisonResult < 2500));

                            //check if in blacklist
                            if(!is_null($video) && !is_null($sourceFound['blacklist_ids'])){
                                if (strpos(strtolower($sourceFound['blacklist_ids']), strtolower($video['id']['videoId'])) !== false) {
                                    $title = $this->normalizeTitle($video['snippet']['title']);
                                    echo "Ignoring video ".$title." (".$video['id']['videoId'].") as it was found in blacklist\n";
                                    $video = null;
                                }
                            }

                            if(!is_null($video)) {
                                //check if we have a video for this song already
                                $check = Video::where('video_id', $video['id']['videoId'])->get();

                                if($check->count() > 0){
                                    $found = null;
                                    foreach ($check as $v){
                                        if($v->playlist_id == $playlistRow->id){
                                            $found = $v;
                                            break;
                                        }
                                    }

                                    if(!is_null($found)){
                                        $found->status = 'pending';
                                        $found->save();
                                    } else {
                                        $new_video = $check[0]->replicate();
                                        $new_video->status = 'pending';
                                        $new_video->playlist_id = $playlistRow->id;
                                        $new_video->spotify_id = $song->track->id;
                                        $new_video->save();
                                    }

                                    echo "We have info for video".(is_null($found) ? ' (replicated)' : '').": ".$query."\n";
                                    continue;
                                }

                                $videoRow = new Video();
                                $videoRow->playlist_id = $playlistRow->id;
                                $videoRow->video_id = $video['id']['videoId'];
                                $videoRow->spotify_id = $song->track->id;
                                $videoRow->title = $this->normalizeTitle($video['snippet']['title']);
                                $publishedTime = new \DateTime($video['snippet']['publishedAt']);
                                $videoRow->created_at = $publishedTime->getTimestamp();
                                $videoRow->thumbnail = $video['snippet']['thumbnails']['high']['url'];
                                $videoIds[] = $videoRow->video_id;
                                $videoRows[$videoRow->video_id] = $videoRow;

                                echo "Found video for: ".$query."\n";
                            } else {
                                echo "No suitable video among results for: ".$query."\n";
                            }

                        }
                    }

                    $this->getVideoDetailsAndSave($videoIds, $videoRows, false, 60, 1000);

                    $tracksOffset += $tracksLimit;
                } while ($songs->total > $songs->limit + $songs->offset);

                $this->makePendingActive($sourceFound->id);
            }
            $offset += $limit;
        } while ($spotifyPlaylists->total > $spotifyPlaylists->offset + $spotifyPlaylists->limit);
        $time_end = microtime(true);
        $time = $time_end - $time_start;

        echo "Execution time $time seconds!\n";

        return "\n";
    }
}
