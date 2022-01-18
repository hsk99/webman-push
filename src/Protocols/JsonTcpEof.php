<?php

namespace Protocols;

use Workerman\Connection\TcpConnection;

/**
 * JsonTcp 协议 （数据包 + 结尾符）
 *
 * @author HSK
 * @date 2022-01-07 10:38:11
 */
class JsonTcpEof
{
    /**
     * 分包
     *
     * @author HSK
     * @date 2022-01-07 10:38:38
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
     * @date 2022-01-07 10:38:49
     *
     * @param array $buffer
     * @param TcpConnection $connection
     *
     * @return string
     */
    public static function encode(array $buffer, TcpConnection $connection): string
    {
        $json = json_encode($buffer, 320);

        return $json . chr(0);
    }

    /**
     * 解包
     *
     * @author HSK
     * @date 2022-01-07 10:38:55
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return array
     */
    public static function decode(string $buffer, TcpConnection $connection): array
    {
        $buffer = rtrim($buffer, chr(0));

        return json_decode($buffer, true) ?? [];
    }
}
