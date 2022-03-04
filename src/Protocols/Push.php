<?php

namespace Hsk99\WebmanPush\Protocols;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Hsk99\WebmanPush\Protocols\PushTcpHead;
use Hsk99\WebmanPush\Protocols\PushTcpEof;
use Hsk99\WebmanPush\Protocols\PushWebSocket;

/**
 * Push 组合协议
 *
 * @author HSK
 * @date 2022-03-02 14:23:00
 */
class Push
{
    /**
     * 分包
     *
     * @author HSK
     * @date 2022-03-02 14:23:00
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return integer
     */
    public static function input(string $buffer, TcpConnection $connection): int
    {
        if (!isset($connection->packetProtocol)) {
            $request = new Request($buffer);

            switch (true) {
                case chr(65) === substr($buffer, 0, 1):
                    $protocol = 'PushTcpHead';
                    break;
                case chr(66) === substr($buffer, 0, 1):
                    $protocol = 'PushTcpEof';
                    break;
                case 'upgrade' === strtolower($request->header('connection')):
                default:
                    $protocol = 'PushWebSocket';
                    break;
            }

            $connection->packetProtocol = $protocol;
        }

        switch ($connection->packetProtocol) {
            case 'PushWebSocket':
                return PushWebSocket::input($buffer, $connection);
                break;
            case 'PushTcpHead':
                return 1 + PushTcpHead::input(substr($buffer, 1), $connection);
                break;
            case 'PushTcpEof':
                return 1 + PushTcpEof::input(substr($buffer, 1), $connection);
                break;
        }
    }

    /**
     * 打包
     *
     * @author HSK
     * @date 2022-03-02 14:23:00
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return string
     */
    public static function encode(string $buffer, TcpConnection $connection): string
    {
        if (!isset($connection->packetProtocol)) $connection->packetProtocol = 'PushTcpHead';

        switch ($connection->packetProtocol) {
            case 'PushWebSocket':
                return PushWebSocket::encode($buffer, $connection);
                break;
            case 'PushTcpHead':
                return chr(65) . PushTcpHead::encode($buffer, $connection);
                break;
            case 'PushTcpEof':
                return chr(66) . PushTcpEof::encode($buffer, $connection);
                break;
        }
    }

    /**
     * 解包
     *
     * @author HSK
     * @date 2022-03-02 14:23:00
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return string
     */
    public static function decode(string $buffer, TcpConnection $connection): string
    {
        if (!isset($connection->packetProtocol)) $connection->packetProtocol = 'PushTcpHead';

        switch ($connection->packetProtocol) {
            case 'PushWebSocket':
                return PushWebSocket::decode($buffer, $connection);
                break;
            case 'PushTcpHead':
                return PushTcpHead::decode(substr($buffer, 1), $connection);
                break;
            case 'PushTcpEof':
                return PushTcpEof::decode(substr($buffer, 1), $connection);
                break;
        }
    }
}
