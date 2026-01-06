<?php

namespace Il4mb\Routing\Http;

use Il4mb\Routing\Http\Code;
use Il4mb\Routing\Map\Route;
use InvalidArgumentException;

class Response
{

    public ListPair $headers;
    public ListPair $cookies;

    protected int $code = Code::OK;
    protected mixed $content = "";


    public function __construct(
        $content = "",
        $code = Code::OK,
        array $headers = [
            "content-type" => "text/html",
            "content-encoding" => "utf-8"
        ]
    ) {
        $this->content = $content;
        $this->code    = is_int($code) ? ($code) : Code::OK;
        $this->headers = new ListPair($headers);
        $this->cookies = new ListPair();
    }

    final public function setContent($content)
    {
        $this->content = $content;
    }

    final public function getContent()
    {
        return $this->content;
    }

    final public function getHeaders()
    {
        return $this->headers;
    }

    final public function setCode(int $code)
    {
        $this->code = Code::fromCode($code) ?? Code::INTERNAL_SERVER_ERROR;
        return $this;
    }

    final public function getCode()
    {
        return $this->code;
    }

    final public function setContentType($contentType)
    {

        $this->headers["content-type"] = $contentType;
        return $this;
    }

    final public function setEncoding($encoding)
    {
        $this->headers["content-encoding"] = $encoding;
        return $this;
    }


    /**
     * Sets a cookie with the specified name, value, and options.
     *
     * @param mixed $name The name of the cookie.
     * @param mixed $value The value of the cookie.
     * @param array $options An associative array of options to customize the cookie:
     *                       - expire (int): The expiration time of the cookie (Unix timestamp).
     *                       - path (string): The path where the cookie is available (default is '/').
     *                       - domain (string): The domain where the cookie is available.
     *                       - secure (bool): Whether the cookie should only be transmitted over HTTPS (default is false).
     *                       - httponly (bool): Whether the cookie is accessible only via HTTP (default is false).
     *
     * @return void
     */
    function setCookie($name, $value, array $options = [])
    {

        if (empty($name)) throw new InvalidArgumentException("Cookie name cannot be empty.");
        if (empty($value)) throw new InvalidArgumentException("Cookie value cannot be empty.");

        // Set default expiration to 0 (session cookie) if not provided
        $expire = isset($options['expire']) ? $options['expire'] : 0;

        // Ensure other options are correctly passed
        $path = isset($options['path']) ? $options['path'] : '/';
        $domain = isset($options['domain']) ? $options['domain'] : '';
        $secure = isset($options['secure']) ? $options['secure'] : false;
        $httponly = isset($options['httponly']) ? $options['httponly'] : false;
        $this->cookies[$name] = [
            "value"  => $value,
            "expire" => $expire,
            "path"   => $path,
            "domain" => $domain,
            "secure" => $secure,
            "httponly" => $httponly
        ];
    }


    final function http_response_code(int $code): void
    {

        if (!function_exists('http_response_code')) {
            $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/2.0';
            header($protocol . ' ' . $code . ' ' . Code::reasonPhrase($code));
        } else
            @http_response_code($code);
    }


    final public function send(): string
    {
        $content = $this->content;
        $this->http_response_code($this->code);

        $content = !empty($content) ? (is_string($content) ? $content : json_encode($content)) : "";
        $this->headers["content-length"] = \mb_strlen($content, '8bit');
        if (is_array($this->content)) {
            $this->headers["content-type"] = "application/json";
        }

        // Ensure no extra output corrupts headers
        if (ob_get_length() > 0) ob_clean();

        // Send headers
        foreach ($this->headers as $key => $value) header("{$key}: {$value}");


        // Set cookies
        foreach ($this->cookies as $name => $cookie)
            setcookie(
                $name,
                $cookie['value'],
                $cookie['expire'],
                $cookie['path'],
                $cookie['domain'],
                $cookie['secure'],
                $cookie['httponly']
            );


        // Return the content as a string or JSON-encoded
        return $content;
    }

    function __debugInfo()
    {

        return [
            "content" => $this->content,
            "code"    => $this->code,
            "headers" => [
                "Content-Type" => $this->headers["content-type"],
                "Content-Encoding" => $this->headers["content-encoding"]
            ],
            "cookies" => $this->cookies,
        ];
    }

    public static function redirect(string $path)
    {
        return new self([], Code::TEMPORARY_REDIRECT, [
            "Location" => "/" . ltrim($path, "/")
        ]);
    }
}
