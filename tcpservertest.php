<?php
include "./AsyncTCPServer.php";

$server = new \Async\TCPServer("tcp://0.0.0.0:4000");

$server->onConnect(function(){
    echo "CONNECTED\n";
});

$server->onData(function($data){
    echo $data;
});

$server->run();
