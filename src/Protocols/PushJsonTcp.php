<?php

namespace Protocols;

use Workerman\Connection\TcpConnection;

/**
 * JsonTcp 协议处理
 *
 * @author HSK
 * @date 2021-11-23 11:32:50
 */
class PushJsonTcp
{
    /**
     * 包头长度
     */
    const PACKAGE_FIXED_LENGTH = 4;

    /**
     * 分包
     *
     * @author HSK
     * @date 2021-11-23 11:33:24
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return integer
     */
    public static function input(string $buffer, TcpConnection $connection): int
    {
        if (strlen($buffer) < self::PACKAGE_FIXED_LENGTH) {
            return 0;
        }

        $unpackData = unpack("Ndata_len", $buffer);

        $len = $unpackData['data_len'] + self::PACKAGE_FIXED_LENGTH;

        if (strlen($buffer) < $len) {
            return 0;
        }

        return $len;
    }

    /**
     * 打包
     *
     * @author HSK
     * @date 2021-11-23 11:33:33
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return string
     */
    public static function encode(string $buffer, TcpConnection $connection): string
    {
        $len = strlen($buffer);

        return pack('N', $len) . $buffer;
    }

    /**
     * 解包
     *
     * @author HSK
     * @date 2021-11-23 11:33:41
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return string
     */
    public static function decode(string $buffer, TcpConnection $connection): string
    {
        $unpackData = unpack("Ndata_len", $buffer);
        $data       = substr($buffer, self::PACKAGE_FIXED_LENGTH, $unpackData['data_len']);

        return $data;
    }
}
