<?php

use Hsk99\WebmanPush\Server;

return [
    'server' => [
        'handler'     => Server::class,
        'listen'      => config('plugin.hsk99.push.app.listen'),
        'count'       => 1, // 必须是1
        'reloadable'  => false, // 执行reload不重启
        'constructor' => [
            'api_listen' => config('plugin.hsk99.push.app.api'),
            'app_info'   => [
                config('plugin.hsk99.push.app.app_key') => [
                    'channel_hook' => config('plugin.hsk99.push.app.channel_hook'),
                    'app_secret'   => config('plugin.hsk99.push.app.app_secret'),
                ],
            ]
        ]
    ]
];
