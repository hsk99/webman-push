<?php

namespace Hsk99\WebmanPush\Protocols;

use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Websocket;
use Hsk99\WebmanPush\Protocols\PushJsonTcp;

/**
 * 组合协议
 *
 * @author HSK
 * @date 2021-11-23 11:24:07
 */
class Push
{
    /**
     * 分包
     *
     * @author HSK
     * @date 2021-11-23 11:47:26
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return integer
     */
    public static function input(string $buffer, TcpConnection $connection): int
    {
        // 首次连接，解析数据包协议
        if (!isset($connection->PACKAGE_TYPE)) {
            $request = new Request($buffer);
            if (strtolower($request->header('connection')) === 'upgrade') {
                $protocol = 'Websocket';
            } else {
                $protocol = 'PushJsonTcp';
            }

            // 记录当前连接使用协议
            $connection->PACKAGE_TYPE = $protocol;
        }

        // 协议分发处理
        switch ($connection->PACKAGE_TYPE) {
            case 'Websocket':
                return Websocket::input($buffer, $connection);
                break;
            case 'PushJsonTcp':
                return PushJsonTcp::input($buffer, $connection);
                break;
            default:
                return PushJsonTcp::input($buffer, $connection);
                break;
        }
    }

    /**
     * 打包
     *
     * @author HSK
     * @date 2021-11-23 11:47:36
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return string
     */
    public static function encode(string $buffer, TcpConnection $connection): string
    {
        if (!isset($connection->PACKAGE_TYPE)) $connection->PACKAGE_TYPE = 'PushJsonTcp';

        // 协议分发处理
        switch ($connection->PACKAGE_TYPE) {
            case 'Websocket':
                return Websocket::encode($buffer, $connection);
                break;
            case 'PushJsonTcp':
                return PushJsonTcp::encode($buffer, $connection);
                break;
            default:
                return PushJsonTcp::encode($buffer, $connection);
                break;
        }
    }

    /**
     * 解包
     *
     * @author HSK
     * @date 2021-11-23 11:47:41
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return string
     */
    public static function decode(string $buffer, TcpConnection $connection): string
    {
        if (!isset($connection->PACKAGE_TYPE)) $connection->PACKAGE_TYPE = 'PushJsonTcp';

        // 协议分发处理
        switch ($connection->PACKAGE_TYPE) {
            case 'Websocket':
                return Websocket::decode($buffer, $connection);
                break;
            case 'PushJsonTcp':
                return PushJsonTcp::decode($buffer, $connection);
                break;
            default:
                return PushJsonTcp::decode($buffer, $connection);
                break;
        }
    }
}
