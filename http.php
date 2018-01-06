<?php

// 创建一个http服务，监听本地的所有ip地址，端口号为9501
$http = new swoole_http_server('0.0.0.0', 9501);

// 关注request事件， 第二个参数是一个回调函数，有两个参数
$http->on('request', function(swoole_http_request $request, swoole_http_response $response) {
    print_r($request);
});

# 启动服务器
$http->start();