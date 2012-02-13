<?php namespace Async\TCP;

function eventReadCallback($bufferEvent, $connection) {
    $cb = $connection->dataCallback;
    if(!$cb)
        return;

    $dataArray = array();
    while($data = event_buffer_read($bufferEvent, 256)) {
        $dataArray[] = $data;
    }

    $cb(implode(NULL, $dataArray));
};

function eventWriteCallback($bufferEvent, $connection) {
    $connection->writePending = FALSE;

    if($connection->closePending) {
        $connection->close();
    }
};

function eventErrorCallback($bufferEvent, $events, $connection) {
    $connection->close();

    $cb = $connection->disconnectCallback;
    if($cb) {
        $cb();
    }
};


class Connection {
    public $socket;
    public $eventBuffer;
    public $tcpServer;
    public $dataCallback;
    public $disconnectCallback;
    public $writePending = FALSE;
    public $closePending = FALSE;

    function __construct($socket, $server) {
        $this->socket = $socket;
        $this->tcpServer = $server;

        stream_set_blocking($socket, 0);

        $this->eventBuffer = event_buffer_new(
            $socket,          // File descriptor to watch
            '\Async\TCP\eventReadCallback',             // Read event callback
            '\Async\TCP\eventWriteCallback',             // Write event callback
            '\Async\TCP\eventErrorCallback', // Error callback
            $this // Custom data to provide to callback
        );

        event_buffer_base_set($this->eventBuffer, $server->eventBase);
        // event_buffer_timeout_set($this->eventBuffer, 30, 30);
        event_buffer_watermark_set($this->eventBuffer, EV_READ | EV_WRITE, 0, 0xffffff);
        event_buffer_priority_set($this->eventBuffer, 10);
        event_buffer_enable($this->eventBuffer, EV_READ | EV_WRITE | EV_PERSIST);
    }

    function write($bytes) {
        $this->writePending = TRUE;
        event_buffer_write($this->eventBuffer, $bytes);
    }

    function close() {
        if(!$this->writePending)
            $this->_close();
        else {
            echo "WAITING FOR WRITE\n";
            $this->closePending = TRUE;
        }
    }

    function _close() {
        event_buffer_disable($this->eventBuffer, EV_READ | EV_WRITE);
        event_buffer_free($this->eventBuffer);
        fclose($this->socket);
        unset($this->eventBuffer, $this->socket);
        echo "TCP CONNECTION CLOSED\n";
    }

    function onData($function) {
        $this->dataCallback = $function;
    }

    function onDisconnect($function) {
        $this->disconnectCallback = $function;
    }
}

class Server {
    public $connectCallback;
    public $socket;
    public $address;
    public $eventBase;
    public $evAccept;

    function __construct($address) {
        $this->address = $address;
        $this->eventBase = \event_base_new();

        $this->evAccept = function($fd, $events, $server) {
            $socket = stream_socket_accept($fd);
            $connection = new Connection($socket, $server);

            $cb = $server->connectCallback;
            if($cb) {
                $cb($connection);
            }
        };
    }

    function onConnect($function) {
        $this->connectCallback = $function;
    }

    function run() {
        $this->socket = stream_socket_server($this->address);

        $event = event_new();

        event_set(
            $event, // The libevent Event object
            $this->socket, // The file descriptor to watch
            EV_READ|EV_PERSIST, // Watch for READ events and watch the event forever, not just once
            $this->evAccept, // Call this function when the event happens
            $this); // Pass this as the 3rd argument to the callback

        event_base_set($event, $this->eventBase);
        event_add($event);
        event_base_loop($this->eventBase);
    }
}
