<?php
include "./AsyncTCPServer.php";

$server = new \Async\TCP\Server("tcp://0.0.0.0:4000");

$server->onConnect(function($conn){
    echo "CONNECTED\n";

    $conn->write("Hello there\n");
    $conn->onDisconnect(function(){
        echo "CLIENT DISCONNECTED\n";
    });
    $conn->onData(function($data) use ($conn){
        $conn->write(strrev($data)."\n");
    });
});

echo "Listening on tcp://0.0.0.0:4000\n";

$server->run();
