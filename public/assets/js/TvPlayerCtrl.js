angular.module('playerApp.player', [])
.controller('TvPlayerCtrl', [
    '$scope', '$window', '$q', '$location', '$http',
    function($scope, $window, $q, $location, $http) {

        $scope.muted = false;
        $scope.fullscreen = false;
        if(typeof localStorage.volume !== 'undefined'){
            $scope.volume = parseInt(localStorage.volume);
        } else {
            $scope.volume = 100;
        }

        $scope.currentProgress = 0;
        if ($location.path() == undefined) {
            console.log($window.location.pathname.split('/'));
            // $scope.currentPlaylistId = $window.location.pathname.split('/')[2];
            $scope.currentPlaylistId = 'e7ca73ea048c292bc7f3dec43cb99ca0';
        }
        else 
            $scope.currentPlaylistId = $location.path().substr(1);

        $scope.progressInterval;

        $scope.playingVideo;

        $scope.videoListAvailable = false;
        $scope.videoListData = null;

        var videosObj = initialVideoJSObj[$scope.currentPlaylistId];

        var automaticScroll = false;

        var sidebarTimeout;
        var sidebarInterval;
        var showVideoListTimeout;
        var elementUnderCursorTimeout;
        var channelsElement = $('.channels');

        var cursorX, cursorY;
        document.body.addEventListener('mousemove', function(e){
            cursorX = e.clientX;
            cursorY = e.clientY;
        });

        channelsElement.on('scroll', function(e){
            if(automaticScroll){
                automaticScroll = false;
                return;
            }
            if($scope.videoListAvailable){
                $scope.videoListAvailable = false;
                $scope.videoListData = null;
                $scope.$apply();
            }
            // delay
            clearTimeout(elementUnderCursorTimeout)
            elementUnderCursorTimeout = setTimeout(function(){
                var el = document.elementFromPoint(cursorX, cursorY);
                openVideoList($(el));
            }, 300);

        });

        function openVideoList($element){
            clearTimeout(showVideoListTimeout);
            showVideoListTimeout = setTimeout(function(){

                if($element[0].tagName.toLowerCase() == 'span'){
                    $element = $element.parent();
                }
                var playlistId = $element.data('id');
                console.log('load', playlistId);

                var diff = $element.offset().top+ channelsElement.scrollTop();
                // console.log('Load - '+id, );
                channelsElement.find('.tick').css('top', (diff-72)+'px');

                $scope.videoListAvailable = true;

                $http.get('/api/videos-short/'+playlistId).then(function (response) {
                    $scope.videoListData = response.data;
                });
            },500);
        };

        $scope.showVideoList = function(event, id){
            $scope.videoListAvailable = false;
            $scope.videoListData = null;
            openVideoList($(event.target));
        };


        function initVideoProgressMonitoring() {
            $scope.progressInterval = setInterval(function () {
                var duration = $scope.player.getDuration();

                if(duration == 0){
                    $scope.currentProgress == 0;
                } else {
                    $scope.currentProgress = Math.round($scope.player.getCurrentTime() / $scope.player.getDuration() * 100);
                    $scope.$apply();
                }
            }, 1000);
        }

        $scope.$onDestroy = function () {
            //console.log('me destroyed!');
            clearInterval($scope.progressInterval);
            clearInterval(sidebarInterval);
            clearTimeout(sidebarTimeout);
            $scope.player.destroy();
        };

        var setPlayingVideoAndGetStartTime = function(){
            var startAt = 0; //init start video time

            //self.playingChannel = self.channels[self.playingChannelIndex];
            //var channelVideoKeys = Object.keys(videosObj.videos);

            var currentSecond = Math.round((new Date().getTime()-window.clientTimeOffset)/1000);
            var startPlaylistAtSecond = currentSecond % videosObj.total_duration;

            var playingVideo = videosObj.videos[0];

            var timeTracker = 0;
            for(var i =0; i < videosObj.videos.length; i++){
                var duration = parseInt(videosObj.videos[i].duration);
                if(startPlaylistAtSecond < timeTracker + duration){
                    playingVideo = videosObj.videos[i];
                    startAt = startPlaylistAtSecond - timeTracker;
                    break;
                }
                timeTracker += duration;
            }
            $scope.playingVideo = playingVideo;

            //self.playingVideo = self.channels[self.playingChannelIndex].videos[channelVideoKeys[0]];

            return startAt;
        };

        var initYouTube = function() {
            //define video and get start second
            var startAt = setPlayingVideoAndGetStartTime();

            window.onYouTubeIframeAPIReady = function () {
                $scope.player = new YT.Player('player-container', {
                    height: '720',
                    width: '1290',
                    //todo
                    videoId: $scope.playingVideo.video_id,
                    //start: startAt,
                    playerVars: {
                        start: startAt,
                        autoplay: 1,
                        autohide: 1,
                        iv_load_policy: 3,
                        controls: 0,
                        showinfo: 0,
                        enablejsapi: 1,
                        origin: 'dev10.koval.rocks'
                    }
                });

                $scope.player.addEventListener('onReady', function onPlayerStateChange(event) {
                    window.YouTubeJSAPIIncluded = true;
                    window.player = $scope.player;
                    initVideoProgressMonitoring();

                    if($scope.volume !== 100){
                        $scope.volumeChanged();
                    }
                });

                $scope.player.addEventListener('onStateChange', function onPlayerStateChange(event) {
                    //console.log('event state change');
                    if (event.data == YT.PlayerState.PAUSED) {
                        $scope.player.playVideo();
                    }
                    if (event.data == YT.PlayerState.ENDED) {
                        $scope.nextVideo();
                        //angular.element(document.getElementById('wrapper')).scope().$apply();
                    }
                });
            };


            if(typeof window.YouTubeJSAPIIncluded == 'undefined') {
                var tag = document.createElement('script');

                tag.src = "https://www.youtube.com/iframe_api";
                var firstScriptTag = document.getElementsByTagName('script')[0];
                firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
            } else {
                window.onYouTubeIframeAPIReady();
            }
        };

        $scope.nextVideo = function(){
            var currentIndex = videosObj.videos.indexOf($scope.playingVideo);

            if(currentIndex+1 < videosObj.videos.length){
                $scope.playingVideo = videosObj.videos[currentIndex+1];
            } else {
                $scope.playingVideo = videosObj.videos[0];
            }
            self.player.loadVideoById({
                'videoId': $scope.playingVideo.video_id,
                'suggestedQuality': 'large'
            });
        };

        $scope.nextPlaylist = function(){
            var playingElement = $('.channels').find('.active');

            var nextElement = playingElement[0].nextElementSibling;

            if(nextElement === null){
                nextElement = $('.channels').children()[1];
            }

            if(nextElement.classList.contains('group')){
                nextElement = nextElement.nextElementSibling;
            }

            $scope.playPlaylistWithId(nextElement.dataset.id);
            scrollSidebarList();
        };

        $scope.prevPlaylist = function(){
            var playingElement = $('.channels').find('.active');

            var nextElement = playingElement[0].previousElementSibling;

            if(nextElement.classList.contains('group')){
                nextElement = nextElement.previousElementSibling;
            }
            
            if(nextElement === null){
                nextElement = $('.channels').children().last()[0];
            }

            $scope.playPlaylistWithId(nextElement.dataset.id);
            scrollSidebarList();
        };

        $scope.playPlaylistWithId = function(id, sidebarClick){
            if(id == $scope.currentPlaylistId){
                return;
            } else {
                $scope.currentPlaylistId = id;

                $http.get('/api/videos/'+id).then(function (response) {
                    videosObj = response.data[id];

                    var startAt = setPlayingVideoAndGetStartTime();

                    $scope.player.loadVideoById({
                        'videoId': $scope.playingVideo.video_id,
                        'suggestedQuality': 'large',
                        'startSeconds': startAt
                    });
                    $location.path(id);

                    var titleParts = document.title.split(' - ');
                    document.title = videosObj.title + ' - ' + titleParts[titleParts.length-1];
                });

                if(typeof sidebarClick != 'undefined') {
                    var playingChannelElement = $('.channels').find('.active');
                    var title = playingChannelElement.find('.channel-name').text();
                    ga('send', 'event', {
                        eventCategory: 'Channels',
                        eventAction: 'sidebarPlay',
                        eventLabel: title
                    });
                }
            }
        };

        $scope.toggleMore = function(group){
            if(typeof $scope.expandedGroups == 'undefined'){
                $scope.expandedGroups = {};
            }

            if(typeof $scope.expandedGroups[group] == 'undefined'){
                $scope.expandedGroups[group] = true;
            } else {
                delete $scope.expandedGroups[group];
            }
        };

        $scope.switchMute = function(){
            if($scope.muted){
                $scope.player.unMute();
                $scope.muted = false;
            } else {
                $scope.player.mute();
                $scope.muted = true;
            }
        };
        $scope.volumeChanged = function(){
            localStorage.volume = $scope.volume;
            $scope.player.setVolume($scope.volume);
        };

        $scope.toggleFullScreen = function() {
            if (!document.fullscreenElement &&    // alternative standard method
                !document.mozFullScreenElement && !document.webkitFullscreenElement) {  // current working methods
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen();
                } else if (document.documentElement.mozRequestFullScreen) {
                    document.documentElement.mozRequestFullScreen();
                } else if (document.documentElement.webkitRequestFullscreen) {
                    document.documentElement.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
                }
                $scope.fullscreen = true;
            } else {
                if (document.cancelFullScreen) {
                    document.cancelFullScreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.webkitCancelFullScreen) {
                    document.webkitCancelFullScreen();
                }
                $scope.fullscreen = false;
            }
        };

        var scrollSidebarList = function(){
            //scroll to playing item;
            setTimeout(function(){
                var channelsElement = $('.channels');
                var playingChannelElement = $('.channels').find('.active');

                var diff = playingChannelElement.offset().top - channelsElement.offset().top;

                automaticScroll = true;
                channelsElement.scrollTop(channelsElement.scrollTop()+diff-2); //2 is outline
            },100);
        };
        $http.get('/api/time').then(function (response) {
            window.clientTimeOffset = new Date().getTime() - response.data;

            initYouTube();

            scrollSidebarList();

            //sidebar show/hide
            var overlay = document.getElementById('overlay');
            var sidebar = document.getElementById('remote');
            var sidebarTop = document.getElementsByClassName('header')[0];
            var sidebarBottom = document.getElementById('remote-bottom');

            var showSidebar = function(){
                clearTimeout(sidebarTimeout);
                clearInterval(sidebarInterval);
                overlay.style.display = 'none';
                sidebar.style.right = '0px';
                sidebarTop.style.top = '0px';
                sidebarBottom.style.bottom = '0px';
                sidebarBottom.classList.remove('hidden');

                sidebarTimeout = setTimeout(function(){
                    $scope.videoListAvailable = false;
                    $scope.videoListData = null;
                    $scope.$apply();
                    overlay.style.display = 'block';
                    var right = 0;
                    var top = 0;
                    var bottom = 0;
                    sidebarBottom.classList.add('hidden');

                    sidebarInterval = setInterval(function(){
                        right += 15;
                        top += 3.4;
                        bottom += 2.5;
                        sidebar.style.right = '-'+right+'px';
                        sidebarTop.style.top = '-'+top+'px';
                        sidebarBottom.style.bottom = '-'+bottom+'px';
                        if(right == 300) clearInterval(sidebarInterval);
                    }, 35);


                }, 3000);
            };

            document.addEventListener('mousemove', showSidebar);
            document.addEventListener('wheel', showSidebar);

            var touchTimeout = null;
            document.addEventListener('touchmove', function(){
                if(touchTimeout){
                    return;
                }
                touchTimeout = setTimeout(function(){
                    showSidebar();
                    touchTimeout = null;
                }, 1000);
            });

        });

        $scope.playVideo = function(playlistId, videoId){
            window.location = '/'+_ACTIVE_CHANNEL+'/'+playlistId+'/'+videoId;
        }
    }]);
