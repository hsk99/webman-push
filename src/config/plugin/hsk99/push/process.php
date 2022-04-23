<?php

return [
    'channel' => [
        'listen'     => 'frame://' . config('plugin.hsk99.push.app.channel_ip') . ':' . config('plugin.hsk99.push.app.channel_port'),
        'handler'    => \Webman\Channel\Server::class,
        'reloadable' => false,
        'count'      => 1,
        'bootstrap'   => []
    ],
    'server' => [
        'handler'     => \Hsk99\WebmanPush\Server::class,
        'listen'      => config('plugin.hsk99.push.app.listen'),
        'protocol'    => config('plugin.hsk99.push.app.protocol'),
        'count'       => config('plugin.hsk99.push.app.channel') ? config('plugin.hsk99.push.app.count') : 1,
        'reloadable'  => false,
        'reusePort'   => true,
        'constructor' => [
            'channel'      => config('plugin.hsk99.push.app.channel'),
            'channel_ip'   => config('plugin.hsk99.push.app.channel_ip'),
            'channel_port' => config('plugin.hsk99.push.app.channel_port'),
            'api_listen'   => config('plugin.hsk99.push.app.api'),
            'app_info'     => [
                config('plugin.hsk99.push.app.app_key') => [
                    'channel_hook' => config('plugin.hsk99.push.app.channel_hook'),
                    'app_secret'   => config('plugin.hsk99.push.app.app_secret'),
                ],
            ]
        ],
        'bootstrap'   => [
            Hsk99\WebmanException\RunException::class,
        ]
    ]
];
