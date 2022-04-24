<?php

namespace Hsk99\WebmanPush;

use Workerman\Worker;
use Workerman\Timer;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Hsk99\WebmanPush\Util;
use Hsk99\WebmanException\RunException;

class Server extends \Webman\Push\Server
{
    /**
     * Channel
     *
     * @var bool
     */
    public $channel = false;

    /**
     * Channel IP
     *
     * @var string
     */
    public $channelIp = null;

    /**
     * Channel Port
     *
     * @var int
     */
    public $channelPort = null;

    /**
     * Worker：0 以外的其他Worker所存储订阅数据
     *
     * @var array
     */
    protected $_globalDataChannel = [];

    /**
     * 构造函数
     *
     * @author HSK
     * @date 2022-04-23 01:17:04
     *
     * @param bool $channel
     * @param string $channel_ip
     * @param int $channel_port
     * @param string $api_listen
     * @param array $app_info
     */
    public function __construct($channel, $channel_ip, $channel_port, $api_listen, $app_info)
    {
        $this->channel     = $channel;
        $this->channelIp   = $channel_ip;
        $this->channelPort = $channel_port;
        $this->apiListen   = $api_listen;
        $this->appInfo     = $app_info;
    }

    /**
     * 服务启动
     *
     * @author HSK
     * @date 2022-04-23 01:07:59
     *
     * @param \Workerman\Worker $worker
     *
     * @return void
     */
    public function onWorkerStart($worker)
    {
        $this->_globalID = $worker->id + 1;

        Timer::add($this->keepAliveTimeout / 2, array($this, 'checkHeartbeat'));
        Timer::add($this->webHookDelay, array($this, 'webHookCheck'));

        if ($this->channel) {
            \Webman\Channel\Client::connect($this->channelIp, $this->channelPort);
        }

        if (0 === $worker->id) {
            $api_worker = new Worker($this->apiListen);
            $api_worker->onMessage = array($this, 'onApiClientMessage');
            $api_worker->listen();

            if ($this->channel) {
                \Webman\Channel\Client::on('hsk99WebmanPushGlobalData', function ($eventData) {
                    $this->_globalDataChannel[$eventData['worker']] = $eventData['data'];
                });
            }
        } else {
            if ($this->channel) {
                \Webman\Channel\Client::on('hsk99WebmanPushApiPush', function ($eventData) {
                    $this->publishToClients($eventData['app_key'], $eventData['channel'], $eventData['event'], $eventData['data'], $eventData['socket_id']);
                });
            }
        }
    }

    /**
     * WebSocket 握手触发回调
     *
     * @author HSK
     * @date 2022-01-20 15:45:07
     *
     * @param \Workerman\Connection\TcpConnection $connection
     * @param string $header
     *
     * @return void
     */
    public function onWebSocketConnect(\Workerman\Connection\TcpConnection $connection, $header)
    {
        try {
            if (!preg_match('/ \/app\/([^\/^\?^ ]+)/', $header, $match)) {
                Util::info($connection, ["header" => $header], "app_key not found");
                $connection->pauseRecv();
                return;
            }

            $app_key = $match[1];
            if (!isset($this->appInfo[$app_key])) {
                Util::info($connection, ["app_key" => $app_key], "Invalid app_key");
                $connection->pauseRecv();
                return;
            }
            $socket_id                                     = $this->createsocketID($connection);
            $connection->appKey                            = $app_key;
            $connection->socketID                          = $socket_id;
            $connection->channels                          = array('' => '');
            $connection->channelUidMap                     = [];
            $connection->clientNotSendPingCount            = 0;
            $this->_eventClients[$app_key][''][$socket_id] = $connection;
            $this->_allClients[$socket_id]                 = $connection;

            /*
             * 向客户端发送链接成功的消息
             * {"event":"pusher:connection_established","data":"{\"socket_id\":\"208836.27464492\",\"activity_timeout\":120}"}
             */
            $data = array(
                'event' => 'pusher:connection_established',
                'data'  => json_encode(array(
                    'socket_id' => $socket_id,
                    'activity_timeout' => 55
                ))
            );

            $connection->send(json_encode($data));
        } catch (\Throwable $th) {
            RunException::report($th);
        }
    }

    /**
     * 收到数据回调
     *
     * @author HSK
     * @date 2022-01-20 15:44:30
     *
     * @param \Workerman\Connection\TcpConnection $connection
     * @param string $data
     *
     * @return void
     */
    public function onMessage($connection, $data)
    {
        try {
            $connection->clientNotSendPingCount = 0;
            $data = json_decode($data, true);

            if (
                !isset($connection->appKey)
                && (!isset($data) || !is_array($data) || !isset($data['event']) || 'pusher:auth' !== $data['event'])
            ) {
                Util::info($connection, $data, "connection not authenticated");
                $connection->pauseRecv();
                return;
            }

            if (!$data) {
                return;
            }
            $event = $data['event'] ?? '';
            switch ($event) {
                case 'pusher:auth':
                    if (!isset($data['app_key'])) {
                        Util::info($connection, $data, "app_key not found");
                        $connection->pauseRecv();
                        return;
                    }

                    $app_key = $data['app_key'];
                    if (!isset($this->appInfo[$app_key])) {
                        Util::info($connection, ["app_key" => $app_key], "Invalid app_key");
                        $connection->pauseRecv();
                        return;
                    }
                    $socket_id                                     = $this->createsocketID($connection);
                    $connection->appKey                            = $app_key;
                    $connection->socketID                          = $socket_id;
                    $connection->channels                          = array('' => '');
                    $connection->channelUidMap                     = [];
                    $connection->clientNotSendPingCount            = 0;
                    $this->_eventClients[$app_key][''][$socket_id] = $connection;
                    $this->_allClients[$socket_id]                 = $connection;

                    $connection->send(json_encode([
                        'event' => 'pusher:connection_established',
                        'data'  => json_encode([
                            'socket_id'        => $socket_id,
                            'activity_timeout' => 55
                        ])
                    ]));
                    return;
                case 'pusher:ping':
                    $connection->send('{"event":"pusher:pong","data":"{}"}');
                    return;
                    // {"event":"pusher:subscribe","data":{"channel":"my-channel"}}
                case 'pusher:subscribe':
                    $channel = $data['data']['channel'];
                    // private- 和 presence- 开头的channel需要验证
                    $channel_type = $this->getChannelType($channel);
                    if ($channel_type === 'presence') {
                        // {"event":"pusher:subscribe","data":{"auth":"b054014693241bcd9c26:10e3b628cb78e8bc4d1f44d47c9294551b446ae6ec10ef113d3d7e84e99763e6","channel_data":"{\"user_id\":100,\"user_info\":{\"name\":\"123\"}}","channel":"presence-channel"}}
                        $client_auth = $data['data']['auth'];

                        if (!isset($data['data']['channel_data'])) {
                            $connection->send($this->error(null, 'Empty channel_data'));
                            return;
                        }
                        $auth = $connection->appKey . ':' . hash_hmac('sha256', $connection->socketID . ':' . $channel . ':' . $data['data']['channel_data'], $this->appInfo[$connection->appKey]['app_secret'], false);

                        // {"event":"pusher:error","data":{"code":null,"message":"Received invalid JSON"}}
                        if ($client_auth !== $auth) {
                            return $connection->send($this->error(null, 'Received invalid JSON ' . $auth));
                        }
                        $user_data = json_decode($data['data']['channel_data'], true);
                        if (!$user_data || !isset($user_data['user_id']) || !isset($user_data['user_info'])) {
                            $connection->send($this->error(null, 'Bad channel_data'));
                            return;
                        }

                        $this->subscribePresence($connection, $channel, $user_data['user_id'], $user_data['user_info']);

                        if ($this->channel && 0 !== $connection->worker->id) {
                            \Webman\Channel\Client::publish('hsk99WebmanPushGlobalData', [
                                'worker' => $connection->worker->id,
                                'data'   => $this->_globalData
                            ]);
                        }
                        return;
                    } elseif ($channel_type === 'private') {
                        // {"event":"pusher:subscribe","data":{"auth":"b054014693241bcd9c26:10e3b628cb78e8bc4d1f44d47c9294551b446ae6ec10ef113d3d7e84e99763e6","channel_data":"{\"user_id\":100,\"user_info\":{\"name\":\"123\"}}","channel":"presence-channel"}}
                        $client_auth = $data['data']['auth'];
                        $auth = $connection->appKey . ':' . hash_hmac('sha256', $connection->socketID . ':' . $channel, $this->appInfo[$connection->appKey]['app_secret'], false);
                        // {"event":"pusher:error","data":{"code":null,"message":"Received invalid JSON"}}
                        if ($client_auth !== $auth) {
                            return $connection->send($this->error(null, 'Received invalid JSON ' . $auth));
                        }
                        $this->subscribePrivateChannel($connection, $channel);
                    } else {
                        $this->subscribePublicChannel($connection, $channel);
                    }

                    if ($this->channel && 0 !== $connection->worker->id) {
                        \Webman\Channel\Client::publish('hsk99WebmanPushGlobalData', [
                            'worker' => $connection->worker->id,
                            'data'   => $this->_globalData
                        ]);
                    }

                    // {"event":"pusher_internal:subscription_succeeded","data":"{}","channel":"my-channel"}
                    $connection->send(json_encode(
                        array(
                            'event'   => 'pusher_internal:subscription_succeeded',
                            'data'    => '{}',
                            'channel' => $channel
                        ),
                        JSON_UNESCAPED_UNICODE
                    ));
                    return;
                    // {"event":"pusher:unsubscribe","data":{"channel":"my-channel"}}
                case 'pusher:unsubscribe':
                    $app_key = $connection->appKey;
                    $channel = $data['data']['channel'];
                    $channel_type = $this->getChannelType($channel);
                    switch ($channel_type) {
                        case 'public':
                            $this->unsubscribePublicChannel($connection, $channel);
                            break;
                        case 'private':
                            $this->unsubscribePrivateChannel($connection, $channel);
                            break;
                        case 'presence':
                            $uid = $connection->channels[$channel];
                            $this->unsubscribePresenceChannel($connection, $channel, $uid);
                            break;
                    }

                    if ($this->channel && 0 !== $connection->worker->id) {
                        \Webman\Channel\Client::publish('hsk99WebmanPushGlobalData', [
                            'worker' => $connection->worker->id,
                            'data'   => $this->_globalData
                        ]);
                    }
                    return;

                    // {"event":"client-event","data":{"your":"hi"},"channel":"presence-channel"}
                default:
                    if (
                        false === strpos($event, 'pusher:')
                        || !isset($event)
                    ) {
                        return $connection->send($this->error(null, 'illegal data'));
                    }

                    if (strpos($event, 'pusher:') === 0) {
                        return $connection->send($this->error(null, 'Unknown event'));
                    }
                    $channel = $data['channel'];
                    // 客户端触发事件必须是private 或者 presence的channel
                    $channel_type = $this->getChannelType($channel);
                    if ($channel_type !== 'private' && $channel_type !== 'presence') {
                        // {"event":"pusher:error","data":{"code":null,"message":"Client event rejected - only supported on private and presence channels"}}
                        return $connection->send($this->error(null, 'Client event rejected - only supported on private and presence channels'));
                    }
                    // 当前链接没有订阅这个channel
                    if (!isset($connection->channels[$channel])) {
                        return $connection->send($this->error(null, 'Client event rejected - you didn\'t subscribe this channel'));
                    }
                    // 事件必须以client-为前缀
                    if (strpos($event, 'client-') !== 0) {
                        return $connection->send($this->error(null, 'Client event rejected - client events must be prefixed by \'client-\''));
                    }

                    // @todo 检查是否设置了可前端发布事件
                    // {"event":"pusher:error","data":{"code":null,"message":"To send client events, you must enable this feature in the Settings page of your dashboard."}}
                    // 全局发布事件
                    $this->publishToClients($connection->appKey, $channel, $event, json_encode($data['data'], JSON_UNESCAPED_UNICODE), $connection->socketID);
                    if ($this->channel) {
                        \Webman\Channel\Client::publish('hsk99WebmanPushApiPush', [
                            'app_key'   => $connection->appKey,
                            'channel'   => $channel,
                            'event'     => $event,
                            'data'      => json_encode($data['data'], JSON_UNESCAPED_UNICODE),
                            'socket_id' => $connection->socketID,
                        ]);
                    }
            }
        } catch (\Throwable $th) {
            RunException::report($th);
        }
    }

    /**
     * API 收到数据回调
     *
     * @author HSK
     * @date 2022-04-23 17:53:40
     *
     * @param \Workerman\Connection\TcpConnection $connection
     * @param Request $request
     *
     * @return void
     */
    public function onApiClientMessage($connection, Request $request)
    {
        if (!($app_key = $request->get('auth_key'))) {
            return $connection->send(new Response(400, [], 'Bad Request'));
        }

        if (!isset($this->appInfo[$app_key])) {
            return $connection->send(new Response(401, [], 'Invalid app_key'));
        }

        $path = $request->path();
        $explode = explode('/', trim($path, '/'));
        if (count($explode) < 3) {
            return $connection->send(new Response(400, [], 'Bad Request'));
        }

        $auth_signature = $request->get('auth_signature');
        $params = $request->get();
        unset($params['auth_signature']);
        ksort($params);
        $string_to_sign = $request->method() . "\n" . $path . "\n" . self::array_implode('=', '&', $params);

        $real_auth_signature = hash_hmac('sha256', $string_to_sign, $this->appInfo[$app_key]['app_secret'], false);
        if ($auth_signature !== $real_auth_signature) {
            return $connection->send(new Response(401, [], 'Invalid signature'));
        }

        $type = $explode[2];
        switch ($type) {
            case 'batch_events':
                $packages = json_decode($request->rawBody(), true);
                if (!$packages || !isset($packages['batch'])) {
                    return $connection->send(new Response(400, [], 'Bad request'));
                }

                $packages = $packages['batch'];
                foreach ($packages as $package) {
                    $channel = $package['channel'];
                    $event = $package['name'];
                    $data = $package['data'];
                    $socket_id = isset($package['socket_id']) ? isset($package['socket_id']) : null;
                    $this->publishToClients($app_key, $channel, $event, $data, $socket_id);
                    if ($this->channel) {
                        \Webman\Channel\Client::publish('hsk99WebmanPushApiPush', [
                            'app_key'   => $app_key,
                            'channel'   => $channel,
                            'event'     => $event,
                            'data'      => $data,
                            'socket_id' => $socket_id,
                        ]);
                    }
                }
                return $connection->send('{}');
                break;
            case 'events':
                $package = json_decode($request->rawBody(), true);
                if (!$package) {
                    return $connection->send(new Response(401, [], 'Invalid signature'));
                }
                $channels = $package['channels'];
                $event = $package['name'];
                $data = $package['data'];
                foreach ($channels as $channel) {
                    $socket_id = isset($package['socket_id']) ? isset($package['socket_id']) : null;
                    $this->publishToClients($app_key, $channel, $event, $data, $socket_id);
                    if ($this->channel) {
                        \Webman\Channel\Client::publish('hsk99WebmanPushApiPush', [
                            'app_key'   => $app_key,
                            'channel'   => $channel,
                            'event'     => $event,
                            'data'      => $data,
                            'socket_id' => $socket_id,
                        ]);
                    }
                }
                return $connection->send('{}');
            case 'channels':
                if (!isset($explode[3])) {
                    return $connection->send(new Response(400, [], 'Bad Request'));
                }
                $channel = $explode[3];
                // users
                if (isset($explode[4])) {
                    if ($explode[4] !== 'users') {
                        return $connection->send(new Response(400, [], 'Bad Request'));
                    }
                    $id_array = isset($this->_globalData[$app_key][$channel]['users']) ?
                        array_keys($this->_globalData[$app_key][$channel]['users']) : array();
                    $user_id_array = array();
                    foreach ($id_array as $id) {
                        $user_id_array[] = array('id' => $id);
                    }

                    foreach ($this->_globalDataChannel as $globalData) {
                        $id_array_temp = isset($globalData[$app_key][$channel]['users']) ? array_keys($globalData[$app_key][$channel]['users']) : [];
                        foreach ($id_array_temp as $id) {
                            $user_id_array[] = ['id' => $id];
                        }
                    }

                    $connection->send(json_encode($user_id_array, JSON_UNESCAPED_UNICODE));
                }
                // info
                $info = explode(',', $request->get('info', ''));
                $occupied = isset($this->_globalData[$app_key][$channel]);
                $user_count = isset($this->_globalData[$app_key][$channel]['users']) ? count($this->_globalData[$app_key][$channel]['users']) : 0;
                $subscription_count = $occupied ? $this->_globalData[$app_key][$channel]['subscription_count'] : 0;

                foreach ($this->_globalDataChannel as $globalData) {
                    $occupied           = $occupied ?: isset($globalData[$app_key][$channel]);
                    $user_count         += isset($globalData[$app_key][$channel]['users']) ? count($globalData[$app_key][$channel]['users']) : 0;
                    $subscription_count += isset($globalData[$app_key][$channel]) ? $globalData[$app_key][$channel]['subscription_count'] : 0;
                }

                $channel_info = array(
                    'occupied' => $occupied
                );
                foreach ($info as $item) {
                    switch ($item) {
                        case 'user_count':
                            $channel_info['user_count'] = $user_count;
                            break;
                        case 'subscription_count':
                            $channel_info['subscription_count'] = $subscription_count;
                            break;
                    }
                }
                $connection->send(json_encode($channel_info, JSON_UNESCAPED_UNICODE));
                break;
            default:
                return $connection->send(new Response(400, [], 'Bad Request'));
        }
    }
}
