<?php

namespace Protocols;

use Workerman\Connection\TcpConnection;

/**
 * JsonTcp 协议 （包头 + 报数据）
 *
 * @author HSK
 * @date 2022-01-07 10:36:54
 */
class JsonTcpHead
{
    /**
     * 包头长度
     */
    const PACKAGE_FIXED_LENGTH = 4;

    /**
     * 分包
     *
     * @author HSK
     * @date 2022-01-07 10:37:28
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
     * @date 2022-01-07 10:37:50
     *
     * @param array $buffer
     * @param TcpConnection $connection
     *
     * @return string
     */
    public static function encode(array $buffer, TcpConnection $connection): string
    {
        $json = json_encode($buffer, 320);
        $len  = strlen($json);

        return pack('N', $len) . $json;
    }

    /**
     * 解包
     *
     * @author HSK
     * @date 2022-01-07 10:37:56
     *
     * @param string $buffer
     * @param TcpConnection $connection
     *
     * @return array
     */
    public static function decode(string $buffer, TcpConnection $connection): array
    {
        $unpackData = unpack("NdataLen", $buffer);
        $data       = substr($buffer, self::PACKAGE_FIXED_LENGTH, $unpackData['dataLen']);

        return json_decode($data, true) ?? [];
    }
}
