<?php

namespace Hsk99\WebmanPush;

use Hsk99\WebmanPush\Util;
use Hsk99\WebmanException\RunException;

class Server extends \Webman\Push\Server
{
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
            }
        } catch (\Throwable $th) {
            RunException::report($th);
        }
    }
}
