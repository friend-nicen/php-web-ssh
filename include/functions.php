<?php

/*
 * 打印测试的html模板
 * */
function getTest(): string
{
    $test = <<<HTML
        <!DOCTYPE html>
        <html lang="zh-cn" xmlns="http://www.w3.org/1999/html">
        <head>
            <meta charset="UTF-8"/>
            <meta charset="UTF-8"/>
            <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
            <title>Web SSH客户端</title>
            <link href="https://nicen.cn/wp-content/themes/document/favicon.ico" rel="shortcut icon" type="image/x-icon"/>
            <script src="https://lf3-cdn-tos.bytecdntp.com/cdn/expire-1-M/jquery/3.6.0/jquery.min.js" type="application/javascript"></script>
            <script src="https://lf3-cdn-tos.bytecdntp.com/cdn/expire-1-M/keyboardjs/2.6.2/keyboard.min.js" type="application/javascript"></script>
            <style>
                body{
                    background-color: #000000;
                    color: #e2e2e2;
                    padding: 15px;
                }
                 input{
                    background-color: black;
                    border: none;
                    color: white;
                    outline: none;
                    font-size: 17px;
                }
            </style>
        </head>
        <body>
        <h1>Web SSH测试</h1>
        <div>须知：测试环境只支持：ps -ef、ping 127.0.0.1、ifconfig，三个命令。</div>
        <div>提示：回车提交、ctrl+c中断（终端现在连接的是网站的主机）</div>
        <br />
        <main>
             <span id="content"></span>
             <input type="text">
        </main>
       
        </body>
        <script>
        
         window.onload=function (){
             
            let content=$("#content");
            let input= $('input');
            let wsServer = 'ws://cname.teiao.com:5707/ws';
            let websocket = new WebSocket(wsServer);
            
            websocket.onopen = function (evt) {
                content.append("Connected to WebSocket server.<br />");
            };
        
            websocket.onclose = function (evt) {
                content.append("Disconnected.<br />");
            };
        
            websocket.onmessage = function (evt) {
                content.append(evt.data.replaceAll("\\n",'<br />'));
                input.val("");
                $(window).scrollTop(document.documentElement.scrollHeight)  
            };
        
            websocket.onerror = function (evt, e) {
                content.append("Error occured: " + evt.data+"<br />");
            };
            

            input.focus();
            
            /*
            * 自动聚焦
            * */
            $(window).on("click",function (){
                input.focus();
            })
            
            /*
            * 回车提交
            * */
            keyboardJS.bind('enter', (e) => {
              websocket.send(input.val());
            });
            
            /*
            * ctrl+c
            * */
             keyboardJS.bind('ctrl > c', (e) => {
              websocket.send("\x03");
            });
        }

           
        </script>
HTML;

    return $test;
}


/*
 * 记录日志
 * */
function logs(string $log, bool $flag = true): void
{
    $time = date("Y-m-d H:i:s", time());

    if ($flag) {
        echo $time . '，' . $log . "\n";
    } else {
        file_put_contents('runtime/log.txt', $time . '，' . $log . "\n", FILE_APPEND);
    }
}

/*
 * 创建redis
 * */
function getRedis(): Redis
{
    $redis = new Redis();
    $redis->connect("/tmp/redis.sock");
    $redis->setOption(3, -1);

    return $redis;
}


/*
 * post提交数据
 * */
function post($address, $data)
{
    $header = array("Content-type:application/json;charset=utf8");
    $cul = curl_init($address);
    curl_setopt($cul, CURLOPT_HTTPHEADER, $header);
    curl_setopt($cul, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($cul, CURLOPT_POST, true);
    curl_setopt($cul, CURLOPT_SSL_VERIFYPEER, false);
    //禁止 cURL 验证对等证书
    curl_setopt($cul, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($cul, CURLOPT_POSTFIELDS, $data);
    $code = curl_exec($cul);
    curl_close($cul);
    return $code; /*只要QQ机器人开着，稳妥的OK*/
}

?>
