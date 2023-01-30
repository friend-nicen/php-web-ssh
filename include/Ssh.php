<?php


use phpseclib3\Net\SSH2;


class Ssh extends SSH2
{

    private $finish = "END_EXEC"; //结束的标志

    public function __construct($host, $port)
    {
        parent::__construct($host, $port);
    }

    /*
     * 读取消息
     * */
    public function read_raw()
    {

        $raw = "";  //缓存消息

        /*
         * 循环读取
         * */
        while (true) {

            $msg = $this->read('username@username:~$');

            if (!empty($msg)) {
                $raw .= $msg;
            }

            /* 结束的标记 */
            if (strpos($raw, $this->finish) !== false) {
                break;
            }
        }
        
        /* 登录 */
        $raw = str_replace($this->finish, "", $raw);
        return str_replace("&& echo " . $this->finish, "", $raw);
    }


    /*
     * 执行命令并输出消息
     */
    public function exec_command($command)
    {
        $this->write($command . ' && echo ' . $this->finish . "\n");
        echo $this->read_raw(); //读取初始化的消息
    }


}