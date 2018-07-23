#!/usr/bin/env php
<?php

    /**
     * socket demo
     * 简单连接方式telnet 0.0.0.0 1000
     * 获取编写客户端连接代码
    */

    error_reporting('E_ALL');
    //设置永久运行
    set_time_limit(0);

    //打开隐式输出
    ob_implicit_flush();

    //连接地址
    $address = '127.0.0.1';

    //端口监听
    $port = 10000;

    //服务端套接字
    if (($serverSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false){
        echo "socket_create() 出错！ 原因:". socket_strerror(socket_last_error())."\n";
    }

    //绑定地址和端口
    if (socket_bind($serverSocket, $address, $port) === false) {
        echo "socket_bind() 出错！ 原因:" . socket_strerror(socket_last_error($serverSocket)) . "\n";
    }

    //监听连接
    if (socket_listen($serverSocket) === false) {
        echo "socket_listen() 出错！ 原因:" . socket_strerror(socket_last_error($serverSocket)) . "\n";
    }

    //客户端连接池
    $clientsockts = array();

    while(true){
        $read = array();
        $read[] = $serverSocket;
        $read = array_merge($read,$clientsockts);
        //等待socket改变状态
        if(socket_select($read ,$write = NULL, $except = NULL, $tv_sec = 5) < 1){
            continue;
        }
        //socket为活跃时
        if (in_array($serverSocket,$read)){
            //添加客户端
            if(($client = socket_accept($serverSocket)) === false){
                echo "socket_accept() 出错！ 原因:" . socket_strerror(socket_last_error($serverSocket)) . "\n";
            }
            if ($client){
                $clientsockts[] = $client;
                $id = array_keys($clientsockts,$client);
                $welcome = "已连接到php服务端\n连接id为{$id[0]}\n发送q断开客户端连接，e关闭服务端\n"; 
                socket_write($client,$welcome,strlen($welcome));
            }
        }
        //处理客户端传送的信息
        foreach($clientsockts as $id => $client){
            //socket活跃状态
            if(in_array($client,$read)){
                $buffer = socket_read($client,2048,PHP_NORMAL_READ);
                if (!$buffer = trim($buffer)) {
                    continue;
                }
                if ($buffer == 'q') {
                    unset($clients[$key]);
                    socket_close($client);
                    break;
                }
                if ($buffer == 'e') {
                    socket_close($client);
                    break 2;
                }
                $msg = "客户端id:{$id},发送了：{$buffer}\n";
                socket_write($client,$msg,strlen($msg));
                echo "$buffer\n";
            }

        }
    }
    socket_close($serverSocket);
?>