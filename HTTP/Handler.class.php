<?php

namespace MFS\AppServer\HTTP;

class Handler extends \MFS\AppServer\DaemonicHandler implements \MFS\AppServer\iProtocol
{
    public function __construct($socket_url = 'tcp://127.0.0.1:8080', $transport_name = 'Socket')
    {
        parent::__construct();

        $transport_class = 'MFS\\AppServer\\Transport\\'.$transport_name;
        $this->setTransport(new $transport_class($socket_url, array($this, 'onRequest')));
        $this->setProtocol($this);
    }

    protected function writeResponse($response_data)
    {
        $response = 'HTTP/1.0 '.$response_data[0]."\r\n";

        for ($i = 0, $cnt = count($response_data[1]); $i < $cnt; $i++) {
            $response .= $response_data[1][$i].': '.$response_data[1][++$i]."\r\n";
        }
        $response .= "\r\n";
        $response .= $response_data[2];

        $this->protocol->write($response); // body
    }

    // iProtocol
    private $stream = null;
    private $headers = null;

    public function readRequest($stream)
    {
        $this->stream = $stream;

        $_headers_str = stream_get_line($this->stream, 0, "\r\n\r\n");
        $_headers = explode("\r\n", $_headers_str); // getting headers

        list($http_method, $url, $http_version) = sscanf(array_shift($_headers), "%s %s %s");

        $this->headers = array();
        foreach ($_headers as $element) {
            $divider = strpos($element, ': ');

            $name = 'HTTP_'.str_replace('-', '_', strtoupper(substr($element, 0, $divider)));
            $value = substr($element, $divider + 2);

            $this->headers[$name] = $value;

        }
        unset($_headers, $first);

        $this->headers['SERVER_SOFTWARE'] = 'appserver-in-php';
        $this->headers['GATEWAY_INTERFACE'] = 'CGI/1.1';
        $this->headers['REQUEST_METHOD'] = $http_method;
        $this->headers['REQUEST_URI'] = $url;
        $this->headers['SCRIPT_NAME'] = '';

        if (false === $pos = strpos($url, '?')) {
            $this->headers['PATH_INFO'] = $url;
            $this->headers['QUERY_STRING'] = '';
        } else {
            $this->headers['PATH_INFO'] = substr($url, 0, $pos);
            $this->headers['QUERY_STRING'] = strval(substr($url, $pos + 1));
        }

        if (isset($this->headers['HTTP_HOST'])) {
            if (false === $pos = strpos($this->headers['HTTP_HOST'], ':')) {
                $host = $this->headers['HTTP_HOST'];
                $port = 80;
            } else {
                $host = substr($this->headers['HTTP_HOST'], 0, $pos);
                $port = substr($this->headers['HTTP_HOST'], $pos + 1);
            }

            $this->headers['SERVER_NAME'] = $host;
            $this->headers['SERVER_PORT'] = strval($port);
        } else {
            $this->headers['SERVER_NAME'] = 'localhost';
            $this->headers['SERVER_PORT'] = '80';
        }

        if (isset($this->headers['HTTP_CONTENT_TYPE'])) {
            $this->headers['CONTENT_TYPE'] = $this->headers['HTTP_CONTENT_TYPE'];
            unset($this->headers['HTTP_CONTENT_TYPE']);
        }

        if (isset($this->headers['HTTP_CONTENT_LENGTH'])) {
            $this->headers['CONTENT_LENGTH'] = $this->headers['HTTP_CONTENT_LENGTH'];
            unset($this->headers['HTTP_CONTENT_LENGTH']);
        } else {
            $this->headers['CONTENT_LENGTH'] = 0;
        }
    }

    public function doneWithRequest()
    {
        if (null !== $this->stream) {
            $this->headers = null;

            fclose($this->stream);
            $this->stream = null;
        }
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getStdin()
    {
        return $this->stream;
    }

    public function write($data)
    {
        fwrite($this->stream, $data);
    }
}
