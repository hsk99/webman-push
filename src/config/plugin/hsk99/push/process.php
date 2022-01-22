<?php

use Hsk99\WebmanPush\Server;

return [
    'server' => [
        'handler'     => Server::class,
        'listen'      => config('plugin.hsk99.push.app.listen'),
        'protocol '   => config('plugin.hsk99.push.app.protocol'),
        'count'       => 1,
        'reloadable'  => false,
        'constructor' => [
            'api_listen' => config('plugin.hsk99.push.app.api'),
            'app_info'   => [
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
