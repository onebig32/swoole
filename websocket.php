<?php

class Websocket
{
    public $server;
    public $userFile = __DIR__ . '/user.txt';
    public function __construct()
    {
        //设置成0.0.0.0代表监听所有地址来源的连接，所以可以进行连接。
        $this->server = new swoole_websocket_server("0.0.0.0", 9502);

        //监听WebSocket连接打开事件
        $this->server->on('open', function (swoole_websocket_server $server, $request) {
            echo "server: handshake success with fd{$request->fd}\n";
            $array = [];
            if (file_exists($this->userFile)) {
                $array = array_filter(explode(',', file_get_contents($this->userFile)));
            }
            array_push($array, $request->fd);
            file_put_contents($this->userFile, join(',', $array), LOCK_EX);

        });

        //监听WebSocket消息事件
        $this->server->on('message', function (swoole_websocket_server $server, $frame) {

            echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";

            //获取聊天用户数组
            $array = explode(',', file_get_contents($this->userFile));
            foreach ($array as $key => $val) {
                $array[$key] = intval($val);
            }

            //组装消息数据
            $msg = json_encode([
                'fd' => $frame->fd,//客户id
                'msg' => $frame->data,//发送数据
                'total_num' => count($array)//聊天总人数
            ], JSON_UNESCAPED_UNICODE);


            //发送消息
            foreach ($array as $fdId) {
                $server->push($fdId, $msg);
            }

        });

        //监听WebSocket连接关闭事件
        $this->server->on('close', function ($server, $fd) {
            //获取聊天用户数组
            $array = explode(',', file_get_contents($this->userFile));
            foreach ($array as $key => $val) {
                $array[$key] = intval($val);
            }

            ///组装消息数据
            $msg = json_encode(
                [
                    'fd' => $fd,
                    'msg' => '离开聊天室!',
                    'total_num' => count($array) - 1
                ],
                JSON_UNESCAPED_UNICODE);

            //发送消息
            foreach ($array as $key => $fdId) {
                if ($fdId == $fd) {
                    unset($array[$key]);
                } else {
                    $server->push($fdId, $msg);
                }
            }
            //更新聊天用户数组
            file_put_contents($this->userFile, join(',', $array), LOCK_EX);
            echo "client {$fd} closed\n";
        });

        $this->server->on('request', function ($request, $response) {
            // 接收http请求从get获取message参数的值，给用户推送
            // $this->server->connections 遍历所有websocket连接用户的fd，给所有用户推送
            foreach ($this->server->connections as $fd) {
                $this->server->push($fd, $request->get['message']);
            }
        });

        $this->server->start();
    }
}

new Websocket();