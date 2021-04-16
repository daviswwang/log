<?php
/**
 * Created by PhpStorm.
 * User: fanxinyu
 * Date: 2021-01-26
 * Time: 14:03
 */

declare(strict_types=1);

namespace Daviswwang\Log;

use Hyperf\Utils\Context;

class Log
{

    public function add_debug(array $debug)
    {
        Context::set('add_debug', $debug);
        Context::set('starTime', microtime(true));
        Context::set('starMemory', memory_get_usage());
    }

    public function add_mysqlDebug(array $params)
    {
        $mysqlDebug = Context::get('add_mysqlDebug') ?: [];
        array_push($mysqlDebug, $params);
        Context::set('add_mysqlDebug', $mysqlDebug);

    }

    public function set_fileName($shopCode, $userId)
    {
        $filePath = BASE_PATH . '/runtime/logs/' . date('Y-m-d') . '/' . $shopCode;

        if (!file_exists($filePath)) {
            mkdir($filePath, 0777, true);   //给到777权限 不然filebeat 监听不到
        }

        $fileName = $filePath . '/' . $userId . '_' . date('H-i-s') . '_' . mt_rand(100, 999) . '.log';

        Context::set('fileName', $fileName);
    }

    public function start_debug($debug, $userId)
    {
        Context::set('add_debug', $debug);
        Context::set('starTime', microtime(true));
        Context::set('starMemory', memory_get_usage());

        $filePath = BASE_PATH . '/runtime/logs/' . config('app_name');

        if (!file_exists($filePath)) {
            mkdir($filePath, 0777, true);
        }
        $fileName = $filePath . '/' . $userId . '_' . date('H-i-s') . '_' . mt_rand(100, 999) . '.log';

        Context::set('fileName', $fileName);
    }


    /**
     * 保存
     * @param  $request
     * @param array $response
     */
    public function save_log($request, $response = [])
    {
        $debug = Context::get('add_debug');
        $mysqlDebug = Context::get('add_mysqlDebug');
        $starTime = Context::get('starTime');
        $starMemory = Context::get('starMemory');
        $fileName = Context::get('fileName');

        if (!$fileName || !$debug) return;

        $data = [];
        $data[] = "## 请求数据\n";
        $data[] = " - METHOD:\t\t{$request->getMethod()}\n";
        $data[] = " - GET_URL:\t\t{$request->getUri()}\n";
//        $data[] = " - SERV_IP:\t\t" . ($_SERVER['SERVER_ADDR'] ?? '') . "\n";
//        $data[] = " - USER_IP:\t\t" . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
//        $data[] = " - REAL_IP:\t\t" . ($_SERVER['X-REAL-IP'] ?? '') . "\n";
        $data[] = " - DATETIME:\t" . date('Y-m-d H:i:s') . "\n";
//        $data[] = " - AGENT:\t\t" . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n";
        $data[] = " - Router:\t\t{$request->fullUrl()}\n";

        $data[] = " \n\n\n\n";
        //一些路由结果，路由结果参数

//        $data[] = "## 路由参数\n";
//        $Params = $request->getHeaders();
//        $data[] = " - Header请求参数:\t\t\n";
//        if (!empty($Params)) {
//            foreach ($Params as $k => $v) {
//                if (is_array($v)) {
//                    $data[] = " \t\t- {$k}中参数:\t\t\n";
//                    foreach ($v as $kk => $item) {
//                        if (is_array($item)) $item = json_encode($item, JSON_UNESCAPED_UNICODE);
//                        $data[] = "\t\t\t\t- {$kk}\t{$item}\n";
//                    }
//                } else {
//                    $data[] = "- {$k}\t{$v}\n";
//                }
//            }
//        }

        $data[] = "## 路由参数\n";
        $Params = $request->getParsedBody();
        $data[] = " - Request请求参数:\t\t\n";
        if (!empty($Params)) {
            if (is_array($Params)) {
                foreach ($Params as $k => $v) {
                    if (is_array($v)) {
                        $data[] = " \t\t- {$k}中参数:\t\t\n";
                        foreach ($v as $kk => $item) {
                            if (is_array($item)) $item = json_encode($item, JSON_UNESCAPED_UNICODE);
                            $data[] = "\t\t\t\t- {$kk}\t{$item}\n";
                        }
                    } else {
                        $data[] = "- {$k}\t{$v}\n";
                    }
                }
            }
        }

        //执行时间 内存消耗
        $data[] = "## 执行时间(ms)    内存消耗(kb)    内存总消耗(kb)\n```\n";
        $time = sprintf('% 9.3f', (microtime(true) - $starTime) * 1000);
        $memo = sprintf('% 9.3f', (memory_get_usage() - $starMemory) / 1024);
        $total = sprintf('% 9.3f', (memory_get_usage()) / 1024);
        $data[] = "\tuTime\t\tuMem\t\ttMem\t\n";
        $data[] = "  {$time}\t{$memo}\t{$total}\t\n```\n";

        if (is_array($mysqlDebug) && count($mysqlDebug)) {
            $slow = [];
            $data[] = "\n## Mysql 顺序：\n";
            $data[] = " - 当前共执行MYSQL：\t" . count($mysqlDebug) . " 次\n";
            foreach ($mysqlDebug as $i => $value) {
                $data[] = "\t\t执行时间\t:" . (date('Y-m-d H:i:s')) . "\t\t执行耗时(ms)\t:" . ($value['time']) . "\n";
                $data[] = "\t\tsql\t:" . ($value['sql'] ?? '') . "\n";
                if (isset($value['parmars'])) $data[] = "\t\t执行结果\t :" . json_encode($value['parmars']) . "\n";
                $data[] = "\n";
            }
        }

        //程序执行顺序
        $data[] = "## 程序执行顺序\n```\n";

        foreach ($debug as $value) {
            $data[] = "\t\t文件位置:" . ($value[0] ?? '') . "\n";
            $data[] = "\t\t行数:" . ($value[1] ?? '') . "\n";
            $data[] = "\t\t描述:" . ($value[2] ?? '') . "\n";
            $data[] = "\t\t详情:\n";
            $value3 = $value[3] ?? '';

            if (is_array($value3) || is_object($value3)) {
                $value3 = json_decode(json_encode($value3), true);

                foreach ($value3 as $k => $v) {
                    if (is_array($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                    $data[] = " \t\t\t\t-{$k}\t{$v}\n";
                }
            }
            $data[] = "\n";
        }

        if (!empty($response)) {
            $data[] = "## 返回数据\n```\n";

            foreach ($response as $key => $value) {

                if (is_array($value) || is_object($value)) {
                    $value = json_decode(json_encode($value), true);

                    foreach ($value as $k => $v) {
                        if (is_array($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                        $data[] = " \t\t\t\t-{$k}\t{$v}\n";
                    }
                } else {
                    $data[] = "- {$key}\t{$value}\n";
                }
                $data[] = "\n";
            }


            $data[] = "\n```\n";
        }


        $data[] = "\n";

        file_put_contents($fileName, $data, LOCK_EX);
    }
}