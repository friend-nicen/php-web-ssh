<?php

require_once 'include/functions.php';

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\CloseFrame;
use Swoole\Coroutine\Http\Server;

use Swoole\Coroutine;
use Swoole\Timer;
use function Swoole\Coroutine\go;
use function Swoole\Coroutine\run;
use function Swoole\Coroutine\defer;


/*
 * 通过hash存放在机器人的在线状态；
 * 订阅机器人的频道 recv_QQ
 * */


/*
 * 设置协程运行相关的参数
 * */
Co::set([
    'socket_timeout' => -1, //tcp超时
    'hook_flags' => SWOOLE_HOOK_ALL  //HOOK函数范围
]);


/*
 * 创建协程容器
 * */
run(function () {


    /*
     * 内存释放
     * */
    go(function () {
        Timer::tick(1000, function () {
            $size = gc_mem_caches();
            if ($size > 0) {
                logs("内存释放" . $size);
            }

        });
    });


    /*
     * 第三个参数 是否开启ssl
     * */
    $server = new Server('0.0.0.0', 5017, false);

    $server->handle('/ws', function (Request $request, Response $ws) {

        /*websocket协议*/
        $ws->upgrade();
        $redis = null; //redis客户端


        /*
         * 创建协程，并获取Guid
         * */
        $subscribe = function () use ($ws) {

            /* 保存协程ID */
            $ws->Gid = go(function () use ($ws) {

                $redis = getRedis(); //创建redis

                /*
                 * 协程退出时清理
                 * */
                defer(function () use ($redis, $ws) {

                    /*
                     * 退出
                     * */
                    logs($ws->mobile . '，已断开链接！');

                    if (!$ws->is_closed) {
                        $redis->hDel("ONLINE_STAFF", $ws->mobile); //取消在线状态
                    }

                    $redis->rawCommand("UNSUBSCRIBE", "RECV_DIAL_" . $ws->mobile);//取消订阅
                    $redis->close();//关闭链接，导致订阅抛出异常
                });

                /*
                 * close时，将抛出异常
                 * */
                try {
                    /*
                     * 订阅频道，当http api处理完毕时将给
                     * */
                    logs($ws->mobile . '，已上线！');

                    /*
                     * 记录已上线
                     * */
                    $redis->hSet('ONLINE_STAFF', $ws->mobile, time());

                    /*
                     * 开启消息订阅
                     * */
                    $redis->subscribe(["RECV_DIAL_" . $ws->mobile], function ($redis, $chan, $msg) use ($ws) {


                        logs('新消息：' . $msg);

                        $json = @json_decode($msg, true); //redis消息
                        if (empty($json['name'])) return; //如果手机号为空


                        /*
                         * 消息推送
                         * */
                        switch ($json['type']) {
                            case 'speak':
                                $ws->push($json['speak']);
                                logs("拨打电话：" . $json['speak'] . "！");
                                break;
                            case 'quit':
                                $ws->is_closed = true; //标记关闭
                                $ws->push('quit');
                                break;
                            case 'message':
                                $ws->push("#" . $json['speak']);
                                logs("发送短信：" . $json['speak'] . "！");
                                break;
                        }


                    });

                } catch (\Throwable $e) {
                    logs($ws->mobile . '，订阅已经关闭');
                }

            });
        };


        /*
         * 清理链接
         * */
        $quit = function ($log) use ($ws, $redis) {

            logs($log);//记录退出原因

            /*
             * 如果协程已经运行
             * */
            if (isset($ws->Gid)) {
                Coroutine::cancel($ws->Gid); //关闭协程
            }

            /*
             * 关闭redis链接
             * */
            if ($redis) {
                $redis->close();//关闭链接
            }

            $ws->close(); //断开ws

        };


        while (true) {

            $frame = $ws->recv(); //阻塞接收消息

            if ($frame === '') {

                $quit("断开连接，收到空数据！");
                break;

            } else if ($frame === false) {

                $quit(swoole_last_error());
                break;

            } else {

                if ($frame->data == 'close' || get_class($frame) === CloseFrame::class) {
                    $quit("用户主动关闭\n");
                    break;
                }

                /* 如果redis客户端不存在 */
                if (!$redis) {
                    $redis = getRedis(); //创建redis
                }


                /*
               * 获取在线人数
               * */
                if ($frame->data == "online") {

                    $online = $redis->hLen("ONLINE_STAFF");
                    $users = $redis->hGetAll("ONLINE_STAFF");
                    $ws->push("当前在线用户数量为：" . (!$online ? 0 : $online . "<br>" . join("<br>", array_keys($users))));

                } else {


                    /* 消息解密 */
                    $data = @json_decode($frame->data, true);

                    /*
                     * 如果没有手机号，并且要登录
                     * */
                    if (!isset($ws->mobile) && $data['type'] == "mobile_login") {

                        /* 如果手机号不为空 */
                        if (!empty($data['msg']['name'])) {


                            logs($data['msg']['name'] . "登录状态：" . $redis->hGet("ONLINE_STAFF", $data['msg']['name']));

                            /*
                             * 如果已经在线
                             * */
                            if ($redis->hGet("ONLINE_STAFF", $data['msg']['name'])) {

                                logs("禁止重复登录，通知" . $data['msg']['name'] . "自动退出！");//记录退出原因

                                /* 通知退出 */
                                $redis->publish("RECV_DIAL_" . $data['msg']['name'], json_encode([
                                    'type' => 'quit',
                                    'name' => $data['msg']['name']
                                ]));

                            }

                            logs('新消息：' . $data['msg']['name'] . "上线");


                            $ws->mobile = $data['msg']['name']; //保存手机号
                            $subscribe(); //开始订阅
                        }

                    } else {
                        $redis->publish("RECV_DIAL_" . $ws->mobile, $frame->data);
                    }


                }

            }
        }
    });


    /*
     * 输出默认测试模板
     * */
    $server->handle('/', function (Request $request, Response $response) {
        $response->end(getTest());
    });


    $server->start();
});
