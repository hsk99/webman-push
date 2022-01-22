<?php

return [
    'enable'       => true,
    'debug'        => true,
    'listen'       => 'tcp://0.0.0.0:8803',
    'protocol'     => \Hsk99\WebmanPush\Protocols\Push::class,
    'api'          => 'http://0.0.0.0:3232',
    'app_key'      => 'APP_KEY_TO_REPLACE',
    'app_secret'   => 'APP_SECRET_TO_REPLACE',
    'channel_hook' => 'http://127.0.0.1:8787/plugin/hsk99/push/hook',
    'auth'         => '/plugin/hsk99/push/auth'
];
