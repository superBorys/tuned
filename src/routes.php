<?php
// Routes
$app->get('/', 'APIController:homepage')->setName('homepage');
$app->get('/new', 'APIController:newHome')->setName('newHome');
$app->get('/mobile', 'APIController:mobile')->setName('mobile');
$app->get('/{channel:video}', 'APIController:video')->setName('channelPage');
$app->get('/{channel:music|tv|sports|kids|vlogs|news}', 'APIController:channel')->setName('channelPage');
$app->get('/{channel:music|tv|sports|kids|vlogs|news}/{playlistId}', 'APIController:player')->setName('playerPage');
$app->get('/{channel:music|tv|sports|kids|vlogs|news}/{playlistId}/{videoId}', 'APIController:playlist')->setName('playlistPage');
$app->get('/about', 'APIController:about')->setName('aboutPage');
$app->get('/contact', 'APIController:contact')->setName('contactPage');

$app->get('/import-{type}', 'ImporterController:cliImport')->setName('cliImport');
$app->get('/pull-videos', 'ImporterController:cliPullImport')->setName('cliPullImport');
$app->get('/pull-from-spotify', 'ImporterController:cliPullFromSpotify')->setName('cliPullFromSpotifyImport');

$app->get('/api/search-params', 'APIController:searchParams')->setName('searchParams');
$app->post('/api/search', 'APIController:search')->setName('search');
$app->get('/api/time', 'APIController:time')->setName('time');
$app->get('/api/videos/{playlistId}', 'APIController:videos')->setName('videosAPIMethod');
$app->get('/api/videos-short/{playlistId}', 'APIController:videosShort')->setName('videosShortAPIMethod');
$app->get('/api/channel/{channel:music|tv|sports|kids|vlogs|news}', 'APIController:apiGetChannel')->setName('apiGetChannel');