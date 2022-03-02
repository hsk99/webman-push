<?php

namespace Hsk99\WebmanPush\Protocols;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Websocket;
use Hsk99\WebmanPush\Util;

/**
 * PushWebSocket 协议
 *
 * @author HSK
 * @date 2022-03-02 14:24:53
 */
class PushWebSocket
{
    /**
     * 分包
     *
     * @author HSK
     * @date 2022-03-02 14:24:53
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return integer
     */
    public static function input(string $buffer, TcpConnection $connection): int
    {
        return Websocket::input($buffer, $connection);
    }

    /**
     * 打包
     *
     * @author HSK
     * @date 2022-03-02 14:24:53
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return string
     */
    public static function encode(string $buffer, TcpConnection $connection): string
    {
        Util::debug($connection, $buffer, 'response');

        return Websocket::encode($buffer, $connection);
    }

    /**
     * 拆包
     *
     * @author HSK
     * @date 2022-03-02 14:24:53
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return string
     */
    public static function decode(string $buffer, TcpConnection $connection): string
    {
        $buffer = Websocket::decode($buffer, $connection);

        Util::debug($connection, $buffer, 'request');

        return $buffer;
    }
}
