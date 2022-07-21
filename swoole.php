<?php

include_once 'include/functions.php';
include_once 'vendor/autoload.php';

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\CloseFrame;
use Swoole\Coroutine\Http\Server;

use Swoole\Coroutine;
use function Swoole\Coroutine\go;
use function Swoole\Coroutine\run;
use function Swoole\Coroutine\defer;
use phpseclib3\Net\SSH2;



/*
 * 机器人自动登录
 * */

/*
 * 设置协程运行相关的参数
 * */
Co::set([
    'socket_timeout'=>-1, //tcp超时
    'hook_flags' => SWOOLE_HOOK_ALL  //HOOK函数范围
]);


/*
 * 创建协程容器
 * */
run(function () {

    /*
     * 第三个参数 是否开启ssl
     * */
    $server = new Server('0.0.0.0', 5080, false);

    $server->handle('/ws', function (Request $request, Response $ws) {


        /*websocket协议*/
        $ws->upgrade();
        $ssh = new SSH2('localhost',22);

        /*如果登录失败*/
        if (!$ssh->login('root', '123456')) {
            $ws->close();
            return;
        }


        $ssh->setTimeout(0.1);




        /*
         * 创建协程，输出命令行内容
         * 这个协程不能被强制cancel，那就只能通过标志位判断了。
         * */
        $subscribe=function () use($ws,$ssh){

            $ws->isRun=true; //标志位读取ssh

            go(function () use ($ws,$ssh){
                /*
                 * 协程退出时清理
                 * */
                defer(function () use ($ssh,$ws) {
                    /*
                     * 退出
                     * */
                    logs('SSH断开链接！');
                    $ssh->disconnect();
                });

                /*
                 * close时，将抛出异常
                 * */
                try {

                    while ($ws->isRun){
                        $msg=$ssh->read('username@username:~$');
                        if(!empty($msg)){
                            $ws->push($msg);
                        }
                    }

                } catch (\Throwable $e) {
                    logs('读取异常');
                }

            });
        };


        /*
         * 清理链接
         * */
        $quit=function ($log) use ($ws){

            logs($log);//记录退出原因

            /*
             * 如果协程已经运行
             * */
            if($ws->isRun){
                $ws->isRun=false; //停止读取
            }

            $ws->close(); //断开ws

        };


        /*
         * 正常处理逻辑
         * */

        $subscribe(); //开始订阅

        $cmd=[
            'ps -ef',
            'ping 127.0.0.1',
            'ifconfig',
            "\x03"
        ];


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

                /*
                  * 如果不在测试命令，则终止
                  * */
                if(!in_array($frame->data,$cmd)){
                    continue;
                }

                $ssh->write($frame->data."\n"); // note the "\n"

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
