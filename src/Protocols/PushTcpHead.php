<?php

namespace Hsk99\WebmanPush\Protocols;

use Workerman\Connection\TcpConnection;
use Hsk99\WebmanPush\Util;

/**
 * JsonTcp 协议 （包头 + 报数据）
 *
 * @author HSK
 * @date 2022-03-02 14:26:16
 */
class PushTcpHead
{
    /**
     * 包头长度
     */
    const PACKAGE_FIXED_LENGTH = 4;

    /**
     * 分包
     *
     * @author HSK
     * @date 2022-03-02 14:26:16
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

        $unpackData = unpack("NdataLen", $buffer);

        $len = $unpackData['dataLen'] + self::PACKAGE_FIXED_LENGTH;

        if (strlen($buffer) < $len) {
            return 0;
        }

        return $len;
    }

    /**
     * 打包
     *
     * @author HSK
     * @date 2022-03-02 14:26:16
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return string
     */
    public static function encode(string $buffer, TcpConnection $connection): string
    {
        Util::debug($connection, $buffer, 'response');

        $len  = strlen($buffer);

        return pack('N', $len) . $buffer;
    }

    /**
     * 解包
     *
     * @author HSK
     * @date 2022-03-02 14:26:16
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return string
     */
    public static function decode(string $buffer, TcpConnection $connection): string
    {
        $unpackData = unpack("NdataLen", $buffer);
        $data       = substr($buffer, self::PACKAGE_FIXED_LENGTH, $unpackData['dataLen']);

        Util::debug($connection, $data, 'request');

        return $data;
    }
}
