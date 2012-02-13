<?php namespace Async\HTTP;
require_once "./AsyncTCPServer.php";

class Request {
    private $parseIndex = 0;
    private $contentLength = 0;

    function entityIsReady() {
        return
            (isset($this->method) && $this->method === "GET");
    }

    function isValid() {
        $headerBoundaryPos = strpos($this->buffer, "\r\n\r\n");
        if($headerBoundaryPos !== FALSE) {
            echo "HEADER RECEIVED\n";
            $this->parseHeader(substr($this->buffer, 0, $headerBoundaryPos));
            $this->buffer = substr($this->buffer, $headerBoundaryPos + 2);
        }

        if($this->entityIsReady()) {
            return TRUE;
        }

        return FALSE;
    }

    function parseHeader($headerString) {
        $lines = explode("\r\n", $headerString);

        $statusLine = $lines[0];
        $statusLineParts = explode(" ", $statusLine);
        $this->method = $statusLineParts[0];
        $this->uri = $statusLineParts[1];
        $this->version = $statusLineParts[2];
        $this->headers = array();

        $lines = array_slice($lines, 1);
        $i = 1;
        $numLines = count($lines);
        $loop = TRUE;
        foreach($lines as $line) {
            $headerParts = explode(":", $line, 2);
            $key = $headerParts[0];
            $val = $headerParts[1];
            $this->headers[$key] = trim($val);
        }

        return TRUE;
    }

}

class Response {
    private $statusSent = FALSE;
    private $headersSent = FALSE;
    private $connection;

    public function __construct($connection) {
        $this->connection = $connection;
    }

    public function status($code) {
        $this->connection->write("HTTP/1.1 $code\r\n");
    }

    public function header($key, $value) {
        $this->connection->write("$key: $value\r\n");
    }

    public function body($data) {
        $this->connection->write("\r\n$data");
    }

    public function close() {
        $this->connection->close();
    }
}

class Server {
    public $tcpServer;
    public $requestCallback;
    public $eventBase;
    private $count;

    function __construct($address) {
        $this->tcpServer = new \Async\TCP\Server($address);
        $self = $this;

        $this->tcpServer->onConnect(function($connection) use ($self){
            $rsp = new Response($connection);
            $req = new Request();
            $req->buffer = "";

            $connection->onData(function($data) use ($self, $req, $rsp) {
                $cb = $self->requestCallback;
                if(!$cb) return;

                $req->buffer .= $data;
                if($req->isValid()) {
                    $cb($req, $rsp);
                }
            });
        });
    }

    function onRequest($function) {
        $this->requestCallback = $function;
    }

    function run() {
        $this->tcpServer->run();
    }
}
