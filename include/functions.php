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
            <script src="https://lf26-cdn-tos.bytecdntp.com/cdn/expire-1-M/xterm/4.11.0/xterm.js" type="application/javascript"></script>
			<link href="https://lf6-cdn-tos.bytecdntp.com/cdn/expire-1-M/xterm/4.11.0/xterm.css" type="text/css" rel="stylesheet" />
			<script src="https://lf3-cdn-tos.bytecdntp.com/cdn/expire-1-M/xterm/4.11.0/addons/fit/xterm-addon-fit.js" type="application/javascript"></script>
			<script src="https://lf26-cdn-tos.bytecdntp.com/cdn/expire-1-M/xterm/4.11.0/addons/attach/xterm-addon-attach.js" type="application/javascript"></script>
			<script src="https://lf3-cdn-tos.bytecdntp.com/cdn/expire-1-M/jquery/3.6.0/jquery.min.js" type="application/javascript"></script>
            <style>
               body{
				margin:0;
				padding: 0;
				background-color: #000000;
				box-sizing: border-box;
			  }

			  #term{

				width: calc(100vw - 40px);
				height: calc(100vh - 40px);
				padding: 20px;
				box-sizing: border-box;
			  }
            </style>
        </head>
        <body>
			<main id="term"></main>
        </body>
        <script type="module">

        
         window.onload=function (){
  

			  const term = new Terminal({
				rendererType: "canvas", //渲染类型
				rows: 40, //行数
				cols: 100, // 不指定行数，自动回车后光标从下一行开始
				convertEol: true, //启用时，光标将设置为下一行的开头
				scrollback: 50, //终端中的回滚量
				disableStdin: false, //是否应禁用输入
				windowsMode: true, // 根据窗口换行
				cursorStyle: "underline", //光标样式
				cursorBlink: true, //光标闪烁
				theme: {
				  foreground: "#ECECEC", //字体
				  background: "#000000", //背景色
				  cursor: "#ffffff", //设置光标
				  lineHeight: 20,
				},
			  });



			  let socket = new WebSocket("ws://1.15.118.69:5722/ws");

			  const attachAddon = new AttachAddon.AttachAddon(socket,{
				bidirectional:true
			  });

			  const fitAddon = new FitAddon.FitAddon() // 全屏插件
			  term.loadAddon(attachAddon);
			  term.loadAddon(fitAddon);
			  term.open($("#term").get(0));
			  fitAddon.fit();
			  term.focus();

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
function getRedis():Redis
{
    $redis = new Redis();
    $redis->connect("/tmp/redis.sock");
    $redis->setOption(3, -1);

    return $redis;
}
