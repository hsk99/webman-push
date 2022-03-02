<?php

namespace Hsk99\WebmanPush;

class Util
{
    /**
     * INFO
     *
     * @author HSK
     * @date 2022-03-02 14:06:46
     *
     * @param \Workerman\Connection\TcpConnection $connection
     * @param mixed $buffer
     * @param string $message
     *
     * @return void
     */
    public static function info(\Workerman\Connection\TcpConnection $connection, $buffer, $message = '')
    {
        $time = microtime(true);
        $data = [
            'worker'         => $connection->worker->name,                         // 运行进程
            'time'           => date('Y-m-d H:i:s.', $time) . substr($time, 11),   // 时间（包含毫秒时间）
            'message'        => $message,                                          // 描述
            'client_address' => $connection->getRemoteAddress(),                   // 客户端地址
            'server_address' => $connection->getLocalAddress(),                    // 服务端地址
            'context'        => $buffer ?? "",                                     // 数据
        ];

        \support\Log::info($message, $data);

        if (config('plugin.hsk99.push.app.debug', false)) {
            echo "\033[31;1m" . date('Y-m-d H:i:s', $time) . "\t"
                . $connection->worker->name . "\t"
                . var_export($buffer, true) . PHP_EOL . "\033[0m";
        }
    }

    /**
     * DEBUG
     *
     * @author HSK
     * @date 2022-03-02 14:04:55
     *
     * @param \Workerman\Connection\TcpConnection $connection
     * @param mixed $buffer
     * @param string $message
     *
     * @return void
     */
    public static function debug(\Workerman\Connection\TcpConnection $connection, $buffer, $message = '')
    {
        $time = microtime(true);
        $data = [
            'worker'         => $connection->worker->name,                         // 运行进程
            'time'           => date('Y-m-d H:i:s.', $time) . substr($time, 11),   // 时间（包含毫秒时间）
            'message'        => $message,                                          // 描述
            'client_address' => $connection->getRemoteAddress(),                   // 客户端地址
            'server_address' => $connection->getLocalAddress(),                    // 服务端地址
            'context'        => $buffer ?? "",                                     // 数据
        ];

        \support\Log::debug($message, $data);

        if (config('plugin.hsk99.push.app.debug', false)) {
            switch ($message) {
                case 'response':
                    $color = 34;
                    break;
                case 'request':
                default:
                    $color = 31;
                    break;
            }
            echo "\033[$color;1m" . date('Y-m-d H:i:s', $time) . "\t"
                . $connection->worker->name . "\t"
                . var_export($buffer, true) . PHP_EOL . "\033[0m";
        }
    }
}
