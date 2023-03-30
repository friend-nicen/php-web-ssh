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
            if($size>0){
                logs("内存释放" . $size);
            }

        });
    });



    /*
     * 第三个参数 是否开启ssl
     * */
    $server = new Server('0.0.0.0', 5017, false);
    $server->table = []; //共享内存
    $server->redis = getRedis(); //创建redis
    $server->redis->del("ONLINE_STAFF"); //清空在线状态



    /*
     * redi
     * */
    go(function () use ($server) {


        $redis = getRedis(); //创建redis


        logs('Redis订阅已启动！');


        /*
         * 开启消息订阅
         * */
        $redis->subscribe(["RECV_DIAL"], function ($redis, $chan, $msg) use ($server) {

            logs('新消息：' . $msg);

            $json = @json_decode($msg, true); //redis消息
            if (empty($json['name'])) return; //如果手机号为空

            $ws = $server->table[$json['name']]; //websocket服务器

            /* 如果ws不存在 */
            if (!$ws) {
                logs('未获取到在线的websocket：' . $msg);
                return;
            }


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


    });


    $server->handle('/ws', function (Request $request, Response $ws) use ($server) {

        /*websocket协议*/
        $ws->upgrade();

        /*
         * 断开连接，清理退出
         * */
        $quit = function ($log) use ($ws, $server) {

            logs(($ws->mobile ?? "未登录") . "：" . "断开连接：" . $log);//记录退出原因
            logs("当前Ws连接数量：" . count($server->table));

            /* 是否要清除数据 */
            if (!isset($ws->is_closed) && $ws->mobile) {

                unset($server->table[$ws->mobile]);
                $ws->redis->hDel("ONLINE_STAFF", $ws->mobile); //清除在线状态
                $ws->redis->close(); //关闭redis

            }

            $ws->close(); //断开ws

        };


        /*
         * 时间循环
         * */
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
                if (!$ws->redis) {
                    $ws->redis = getRedis(); //创建redis
                }


                /*
               * 获取在线人数
               * */
                if ($frame->data == "online") {

                    $online = $ws->redis->hLen("ONLINE_STAFF");
                    $users = $ws->redis->hGetAll("ONLINE_STAFF");
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


                            /*
                             * 如果已经在线
                             * */
                            if (!empty($server->table[$data['msg']['name']])) {

                                logs("禁止重复登录，通知" . $data['msg']['name'] . "自动退出！");//记录退出原因

                                /* 通知退出 */
                                $ws->redis->publish("RECV_DIAL", json_encode([
                                    'type' => 'quit',
                                    'name' => $data['msg']['name']
                                ]));

                            }

                            logs('新消息：' . $data['msg']['name'] . "上线");


                            /* 记录连接 */
                            $server->table[$data['msg']['name']] = $ws;
                            /* 标记手机号 */
                            $ws->mobile = $data['msg']['name'];
                            /* 记录登录状态 */
                            $ws->redis->hSet('ONLINE_STAFF', $data['msg']['name'], time());
                        }

                    } else {
                        $ws->redis->publish("RECV_DIAL", $frame->data);
                    }


                }


            }
        }
    });


    /*
     * 输出默认测试模板
     * */
    $server->handle('/', function (Request $request, Response $response) {
        $response->end(getTestB());
    });


    $server->start();
});
