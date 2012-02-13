<?php
require "./AsyncHTTPServer.php";

$server = new \Async\HTTP\Server("tcp://localhost:8000");

$server->onRequest(function($req, $rsp){
    print_r($req);

    $rsp->status(200);
    $rsp->header("Content-Type", "text/plain");

    $body = "Hello world";
    $rsp->header("Content-Length", strlen($body));
    $rsp->body($body);
    $rsp->close();
});

echo "Listening on tcp://localhost:8000\n";
$server->run();
