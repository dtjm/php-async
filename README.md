php-async
=========
Asynchronous components for PHP

TCP Server
----------
**server.php**

```php
$server = new \Async\TCPServer("tcp://0.0.0.0:4000");
$server->onConnect(function(){
    echo "A client connected\n";
});
$server->onData(function($data){
    echo "Client said: $data\n";
});

$server->run();
```

Then you can do this:

```bash
# telnet localhost 4000
```