`
    public function receive_push()
    {

        /*
         * 获取推送的数据
         * */
        $json = file_get_contents("php://input");
        $data = json_decode($json, true);

        /*
         * 提交给处理的数据
         * */
        $need = [
            "clone" => $data['repository']["clone_url"],
            "path" => $data['repository']["path"],
            "branch" => str_replace("refs/heads/", '', $data['ref'])
        ];

        getRedis()->publish("RECV_GIT", json_encode($need));
    }

`