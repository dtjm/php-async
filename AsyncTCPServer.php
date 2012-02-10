<?php namespace Async;

class TCPServer {
    public $connectCallback;
    public $dataCallback;
    public $closeCallback;
    public $listenSocket;
    public $address;
    public $eventBase;
    public $connections;
    public $buffers;
    public $count;
    public $evAccept;
    public $evRead;
    public $evError;

    function __construct($address) {
        $this->address = $address;
        $this->connections = array();
        $this->buffers = array();
        $this->count = 0;

        $this->evAccept = function($fd, $events, $server) {
            $conn = stream_socket_accept($fd);
            stream_set_blocking($conn, 0);

            $buffer = event_buffer_new(
                $conn, $server->evRead, NULL, $server->evError, array($server, $server->count));
            event_buffer_base_set($buffer, $server->eventBase);
            event_buffer_timeout_set($buffer, 30, 30);
            event_buffer_watermark_set($buffer, EV_READ, 0, 0xffffff);
            event_buffer_priority_set($buffer, 10);
            event_buffer_enable($buffer, EV_READ | EV_PERSIST);

            $server->connections[$server->count] = $conn;
            $server->buffers[$server->count] = $buffer;

            $server->count += 1;
            $cb = $server->connectCallback;
            if($cb) {
                $cb();
            }
        };

        $this->evRead = function($buffer, $data) {
            $server = $data[0];
            $cb = $server->dataCallback;
            while($read = event_buffer_read($buffer, 256)) {
                if($cb) {
                    $cb($read);
                }
            }
        };

        $this->evError = function($buffer, $error, $data) {
            $server = $data[0];
            $id = $data[1];
            event_buffer_disable($server->buffers[$id], EV_READ | EV_WRITE);
            event_buffer_free($server->buffers[$id]);
            fclose($server->connections[$id]);
            unset($server->buffers[$id], $server->connections[$id]);
            $cb = $server->closeCallback;
            if($cb) {
                $cb();
            }
        };
    }

    function onConnect($function) {
        $this->connectCallback = $function;
    }

    function onData($function) {
        $this->dataCallback = $function;
    }

    function onClose($function) {
        $this->closeCallback = $function;
    }

    function run() {
        $this->listenSocket = stream_socket_server($this->address);

        $this->eventBase = event_base_new();
        $event = event_new();

        event_set(
            $event, // The libevent Event object
            $this->listenSocket, // The file descriptor to watch
            EV_READ|EV_PERSIST, // Watch for READ events and watch the event forever, not just once
            $this->evAccept, // Call this function when the event happens
            $this); // Pass this as the 3rd argument to the callback

        event_base_set($event, $this->eventBase);
        event_add($event);
        event_base_loop($this->eventBase);
    }
}
