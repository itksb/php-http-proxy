<?php

/**
 * Proxy util for the checktaxi service
 * @package itksb\ato\proxy\taxi
 * @author Sergey ksb@itksb.com
 */

namespace itksb\ato\proxy\taxi;


final class CheckTaxi
{
    public $host = 'checktaxi70.ru';
    public $port = 80;
    public $path = '/check_number.php';


    final public function checkGosNumber(string $gosNumber): Response
    {
        if (!($sock = fsockopen($this->host, $this->port, $err_no, $err_str))) {
            $response = $this->createErrorResponse($err_no, $err_str);
        } else {
            $path = $this->createPath($gosNumber);
            $packet = $this->createPacket($path);
            fwrite($sock, $packet);
            $streamResult = stream_get_contents($sock);
            $response = $this->createResponse($streamResult);
        }

        return $response;
    }

    private function createErrorResponse(int $err_no, string $err_str): Response
    {
        $response = new ErrorResponse();
        $response->proxyError = 'Error code: ' . $err_no . '. Error: ' . $err_str;
        return $response;
    }

    private function createPath(string $gosNumber): string
    {
        return '/check_number.php?gosno=' . urlencode($gosNumber);
    }

    private function createPacket(string $path): string
    {
        $packet = "GET {$path} HTTP/1.1\r\n";
        $packet .= "Host: {$this->host}\r\n";
        $packet .= "Connection: close\r\n\r\n";
        return $packet;
    }

    private function createResponse(string $streamResult): Response
    {
        $body = $this->extractHttpBody($streamResult);
        if (empty($body)) {
            $response = new ErrorResponse();
            $response->proxyError = 'Empty answer from the ' . $this->host;
            return $response;
        }

        $decoded = $this->parseHttpResponseBody($body);
        if (!empty($decoded->error)) {
            $response = new ErrorResponse();
            $response->proxyError = $decoded->error;
            return $response;
        }

        $response = new SuccessResponse();

        foreach ($response as $key => $value) {
            if (isset($decoded->$key)) {
                $response->$key = $decoded->$key;
            }
        }

        return $response;

    }


    private function extractHttpBody(string $httpResponse): string
    {
        $explodeLimit = 2;
        list($header, $body) = explode("\r\n\r\n", $httpResponse, $explodeLimit);
        $pos = mb_strpos($body, '{');
        if ($pos > 0) {
            $body = mb_substr($body, $pos);
        }
        $pos = mb_strrpos($body, '}');
        if ($pos > 0) {
            $body = mb_substr($body, 0, $pos + 1);
        }
        return $body;
    }


    private function decodeJsonError(int $errorNum)
    {
        $result = '';
        switch ($errorNum) {
            case JSON_ERROR_NONE:
                $result = '';
                break;
            case JSON_ERROR_DEPTH:
                $result = ' - Достигнута максимальная глубина стека';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $result = ' - Некорректные разряды или несоответствие режимов';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $result = ' - Некорректный управляющий символ';
                break;
            case JSON_ERROR_SYNTAX:
                $result = ' - Синтаксическая ошибка, некорректный JSON';
                break;
            case JSON_ERROR_UTF8:
                $result = ' - Некорректные символы UTF-8, возможно неверно закодирован';
                break;
            default:
                $result = ' - Неизвестная ошибка';
                break;
        }
        return $result;
    }


    private function parseHttpResponseBody(string $httpResponseBody): \stdClass
    {
        $result = new \stdClass();
        $parseAsAssocArray = false;
        $recursionDepth = 2;
        $httpResponseBody = utf8_encode($httpResponseBody);
        $decoded = json_decode($httpResponseBody, $parseAsAssocArray, $recursionDepth);
        $jsonError = json_last_error();
        if ($jsonError !== JSON_ERROR_NONE) {
            $result->error = 'Error while parsing json: ' . $this->decodeJsonError($jsonError);
        } else {
            $result = $decoded;
        }
        return $result;
    }

}

abstract class Response
{
    public $status = '';
    public $message = '';
    public $proxyError = '';

    final public function isFound()
    {
        return 'ok' === $this->status;
    }

    final public function isConnError()
    {
        return !empty($this->proxyError);
    }
}


final class SuccessResponse extends Response
{
    public $avto_model = '';
    public $avto_number = '';
    public $avto_year = '';
    public $city = '';
    public $country = '';
    public $date_from = '';
    public $date_to = '';
    public $form = '';
    public $fullname = '';
    public $lic_number = '';
    public $post_index = '';
    public $region = '';
    public $state = '';
}


final class ErrorResponse extends Response
{
}
