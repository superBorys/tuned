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
        var path = $location.path().substr(1).split('/');
        $scope.currentPlaylistId = path[0];
        $scope.currentVideoId = path[1];

        $scope.progressInterval;

        $scope.playingVideo;

        var videosObj = initialVideoJSObj[$scope.currentPlaylistId];
        $scope.videos = videosObj;

        var sidebarTimeout;
        var sidebarInterval;

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

        var initYouTube = function() {
            //define video and get start second
            //var startAt = setPlayingVideoAndGetStartTime();
            for(var i = 0; i < videosObj.videos.length; i++){
                if(videosObj.videos[i].video_id == $scope.currentVideoId){
                    $scope.playingVideo = videosObj.videos[i];
                    break;
                }
            }


            window.onYouTubeIframeAPIReady = function () {
                $scope.player = new YT.Player('player-container', {
                    height: '720',
                    width: '1290',
                    //todo
                    videoId: $scope.playingVideo.video_id,
                    //start: startAt,
                    playerVars: {
                        start: 0,
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
                        $scope.paused = true;
                        document.getElementById('pause-overlay').style.display = 'block';
                    } else {
                        $scope.paused = false;
                        document.getElementById('pause-overlay').style.display = 'none';
                    }
                    if (event.data == YT.PlayerState.ENDED) {
                        $scope.nextVideo();
                        //angular.element(document.getElementById('wrapper')).scope().$apply();
                    }
                    $scope.$apply();
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

        $scope.pause = function(){
            if($scope.player.getPlayerState() == YT.PlayerState.PAUSED){
                $scope.player.playVideo();
            } else {
                $scope.player.pauseVideo();
            }
        };

        $scope.nextVideo = function(){
            var currentIndex = videosObj.videos.indexOf($scope.playingVideo);

            if(currentIndex+1 < videosObj.videos.length){
                $scope.playingVideo = videosObj.videos[currentIndex+1];
            } else {
                $scope.playingVideo = videosObj.videos[0];
            }
            playNewVideo();
            scrollSidebarList();
        };

        $scope.prevVideo = function(){
            var currentIndex = videosObj.videos.indexOf($scope.playingVideo);

            if(currentIndex-1 < 0){
                $scope.playingVideo = videosObj.videos[videosObj.videos.length-1];
            } else {
                $scope.playingVideo = videosObj.videos[currentIndex-1];
            }
            playNewVideo();
            scrollSidebarList();
        };

        $scope.playVideoWithId = function(id, sidebarClick){
            for(var i = 0; i < videosObj.videos.length; i++){
                if(videosObj.videos[i].video_id == id){
                    $scope.playingVideo = videosObj.videos[i];
                    break;
                }
            }
            playNewVideo();

            if(typeof sidebarClick != 'undefined') {
                ga('send', 'event', {
                    eventCategory: 'Videos',
                    eventAction: 'sidebarPlay',
                    eventLabel: $scope.playingVideo.title
                });
            }
        };

        var playNewVideo = function(){
            $scope.player.loadVideoById({
                'videoId': $scope.playingVideo.video_id,
                'suggestedQuality': 'large'
            });

            $location.path($scope.currentPlaylistId+'/'+$scope.playingVideo.video_id);

            var titleParts = document.title.split(' - ');
            document.title = $scope.playingVideo.title + ' - ' + titleParts[titleParts.length-1];
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
                channelsElement.scrollTop(channelsElement.scrollTop()+diff-2); //2 is outline
            },100);
        };

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

        //console.log($location.path().substr(1));
        //$location.path('abc');
        console.log($location.search());


    }]);
