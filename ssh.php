<?php

include_once 'include/functions.php';
include_once 'vendor/autoload.php';
include_once 'include/Ssh.php';


/* 结束的标志 */
$redis = getRedis(); //创建redis
$root = "/home/repos/"; //存放仓库的目录


/*
 * SSH连接配置
 * */
$ssh = new Ssh('localhost', 22);

/*如果登录失败*/
if (!$ssh->login('root', 'Qq1747111677@')) {
    exit();
}

sleep(1); //等待登录

/*设置读取间隔时间*/
$ssh->setTimeout(0.1);


/*
 * redis订阅消息
 * */
$redis->subscribe(["RECV_GIT"], function ($redis, $chan, $msg) use ($ssh, $root) {

    echo "收到消息：" . $msg . "\n"; //收到消息

    if (strpos($msg, "branch") === false) return;

    try {

        $data = json_decode($msg, true); //处理结果

        /*
          * 判断仓库是否存在
          * */
        $path = $root . $data['path']; //本地仓库路径

        /*
         * 仓库目录是否存在
         * 不存在则创建目录
         * */
        if (file_exists($path)) {
            $ssh->exec_command("rm -rf " . $path); //创建目录
        }

        $ssh->exec_command("mkdir " . $path); //创建目录
        $ssh->exec_command("cd " . $path); //打开所在目录

        $ssh->exec_command("git init"); //初始化仓库
        $ssh->exec_command("git remote add origin " . $data['clone']); //添加仓库


        /*拉取分支*/
        $ssh->exec_command("git pull origin " . $data['branch'] . ":" . $data['branch']);
        echo "同步完毕";

    } catch (\Throwable $e) {
        echo $e->getMessage() . "\n";
    }
});

