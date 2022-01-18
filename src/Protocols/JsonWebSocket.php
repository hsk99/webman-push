<?php

namespace Protocols;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Websocket;

/**
 * JsonWebSocket 协议
 *
 * @author HSK
 * @date 2022-01-07 10:39:22
 */
class JsonWebSocket
{
    /**
     * 分包
     *
     * @author HSK
     * @date 2022-01-07 10:39:27
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
     * @date 2022-01-07 10:39:32
     *
     * @param array $buffer
     * @param TcpConnection $connection
     *
     * @return string
     */
    public static function encode(array $buffer, TcpConnection $connection): string
    {
        $buffer = json_encode($buffer, 320);

        return Websocket::encode($buffer, $connection);
    }

    /**
     * 拆包
     *
     * @author HSK
     * @date 2022-01-07 10:39:36
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return array
     */
    public static function decode(string $buffer, TcpConnection $connection): array
    {
        $buffer = Websocket::decode($buffer, $connection);

        return json_decode($buffer, true) ?? [];
    }
}
