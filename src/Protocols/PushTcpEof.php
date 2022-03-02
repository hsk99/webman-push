<?php

namespace Hsk99\WebmanPush\Protocols;

use Workerman\Connection\TcpConnection;
use Hsk99\WebmanPush\Util;

/**
 * JsonTcp 协议 （数据包 + 结尾符）
 *
 * @author HSK
 * @date 2022-03-02 14:27:26
 */
class PushTcpEof
{
    /**
     * 分包
     *
     * @author HSK
     * @date 2022-03-02 14:27:26
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return integer
     */
    public static function input(string $buffer, TcpConnection $connection): int
    {
        $pos = strpos($buffer, chr(0));
        if (false === $pos) {
            return 0;
        }

        return $pos + 1;
    }

    /**
     * 打包
     *
     * @author HSK
     * @date 2022-03-02 14:27:26
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return string
     */
    public static function encode(string $buffer, TcpConnection $connection): string
    {
        Util::debug($connection, $buffer, 'response');

        return $buffer . chr(0);
    }

    /**
     * 解包
     *
     * @author HSK
     * @date 2022-03-02 14:27:26
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return string
     */
    public static function decode(string $buffer, TcpConnection $connection): string
    {
        $buffer = rtrim($buffer, chr(0));

        Util::debug($connection, $buffer, 'request');

        return $buffer;
    }
}
