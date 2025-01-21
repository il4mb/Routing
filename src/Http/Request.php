<?php

namespace Il4mb\Routing\Http;

use Exception;
use Il4mb\Routing\Http\Method;

class Request
{
    private array $fixKeys = ["files", "body", "queries", "cookies"];
    /**
     * @var array<string, string> $props
     */
    protected array $props = [
        "files"   => [],
        "body"    => [],
        "queries" => [],
        "cookies" => []
    ];
    public readonly ?Method $method;
    public readonly URL $uri;
    public readonly array $headers;
    private static $instance;

    private function __construct()
    {

        $this->method      = Method::tryFrom($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri         = new URL();
        if (function_exists("getallheaders"))
            $this->headers = getallheaders();
        else
            $this->headers = [];

        foreach ($_GET as $key => $value) {
            $this->props["queries"][$key] = $value;
        }
        foreach ($_POST as $key => $value) {
            $this->props["body"][$key] = $value;
        }

        $this->parseMutipartBoundary();

        foreach ($_COOKIE as $key => $value) {
            $this->props["cookies"][$key] = $value;
        }
        foreach ($_FILES as $key => $value) {
            $this->props["files"][$key] = $value;
        }
    }


    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    function get(string $name, $default = null)
    {
        if ($name == "*") return $this->props;
        return $this->props[$name] ?? $default;
    }


    function set(string $name, mixed $value): void
    {
        if (in_array($name, $this->fixKeys)) throw new Exception("Can't set fixed key \"{$name}\"");
        $this->props[$name] = $value;
    }

    function has(string $key): bool
    {
        return isset($this->props[$key]);
    }

    function getBody($name)
    {
        return $this->props["body"][$name] ?? null;
    }

    function getQuery($name)
    {
        return $this->props["queries"][$name] ?? null;
    }

    function getFile(string $name)
    {
        return $this->props["files"][$name] ?? null;
    }

    public function isMethod(Method $method)
    {
        return strtoupper($this->method?->value) === strtoupper($method->value);
    }

    public function isAjax()
    {
        $keys = [
            "X-Requested-With" => "XMLHttpRequest",
            "Sec-Fetch-Mode"   => "cors"
        ];
        foreach ($keys as $key => $value) {
            if (isset($this->headers[$key]) && $this->headers[$key] === $value) {
                return true;
            }
        }
        if ($this->isContent(ContentType::JSON)) {
            return true;
        }
    }

    function isContent(ContentType $accept)
    {
        return in_array($accept->value, explode(",", $this->headers['Accept'] ?? ""));
    }

    private function parseMutipartBoundary()
    {
        $rawBody = file_get_contents('php://input');
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'multipart/form-data') !== false) {
            preg_match('/boundary=(.*)$/', $contentType, $matches);
            $boundary = $matches[1] ?? null;
            if ($boundary) {
                $parts = explode('--' . $boundary, $rawBody);
                foreach ($parts as $part) {

                    if (empty(trim($part)) || $part === '--') continue;
                    $block = explode("\r\n\r\n", $part, 2);
                    if (empty($block) || count($block) < 2) continue;
                    [$rawHeaders, $body] = $block;
                    $rawHeaders = explode("\r\n", $rawHeaders);
                    $headers = [];
                    foreach ($rawHeaders as $header) {
                        if (strpos($header, ':') !== false) {
                            [$key, $value] = explode(':', $header, 2);
                            $headers[trim($key)] = trim($value);
                        }
                    }
                    if (isset($headers['Content-Disposition'])) {
                        preg_match('/name="([^"]+)"/', $headers['Content-Disposition'], $nameMatch);
                        preg_match('/filename="([^"]+)"/', $headers['Content-Disposition'], $fileMatch);
                        $name = $nameMatch[1] ?? null;
                        $filename = $fileMatch[1] ?? null;
                        if ($filename) {
                            $tempFilePath = tempnam(sys_get_temp_dir(), uniqid('upload_', true));
                            file_put_contents($tempFilePath, $body);
                            $this->props["files"][$name] = [
                                'type' => $headers['Content-Type'] ?? 'application/octet-stream',
                                'name' => $filename,
                                'tmp_name' => $tempFilePath,
                                'size' => strlen($body),
                            ];
                        } else {
                            $this->props["body"][$name] = trim($body);
                        }
                    }
                }
            }
        } else {
            $jsonArray = json_decode($rawBody, true) ?? [];
            foreach ($jsonArray as $key => $val) {
                $this->props["body"][$key] = $val;
            }
        }
    }
}
