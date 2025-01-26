<?php

namespace Il4mb\Routing\Http;

use Exception;
use Il4mb\Routing\Http\Method;

class Request
{
    private array $fixKeys = ["__files", "__body", "__queries", "__cookies"];
    /**
     * @var array<string, string> $props
     */
    protected array $props = [
        "__files"   => [],
        "__body"    => [],
        "__queries" => [],
        "__cookies" => []
    ];
    public readonly ?Method $method;
    public readonly Url $uri;
    public readonly ListPair $headers;

    function __construct()
    {

        $this->method      = Method::tryFrom($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri         = new URL();
        $this->headers     = new ListPair(
            function_exists("getallheaders")
                ? getallheaders()
                : []
        );

        foreach ($_GET as $key => $value) {
            $this->props["__queries"][$key] = $value;
        }
        foreach ($_POST as $key => $value) {
            $this->props["__body"][$key] = $value;
        }

        $this->parseMutipartBoundary();

        foreach ($_COOKIE as $key => $value) {
            $this->props["__cookies"][$key] = $value;
        }
        foreach ($_FILES as $key => $value) {
            $this->props["__files"][$key] = $value;
        }

        // clear state
        $_GET    = [];
        $_POST   = [];
        $_COOKIE = [];
        $_FILES  = [];
    }

    /**
     * @template T
     * @param string $name The name of the property to retrieve.
     * @param class-string<T>|null $type The class string for type checking or casting (optional).
     * @return T|array|null The value cast to the specified type or null if not found.
     */
    function get(string $name, string $type = null)
    {
        // Retrieve the value from props or fallback sources.
        if ($name === "*") {
            return $this->props; // Ensure $this->props is typed correctly (e.g., array<string, mixed>).
        }

        $val = $this->props[$name] ?? null;
        if ($val === null) {
            $val = $this->getBody($name) ?? $this->getQuery($name) ?? null;
        }

        // If a type is provided, validate or cast the value.
        if ($type !== null) {
            if (!is_a($val, $type, allow_string: true)) {
                return null;
            }
        }

        return $val;
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
        return $this->props["__body"][$name] ?? null;
    }

    function getQuery($name)
    {
        return $this->props["__queries"][$name] ?? null;
    }

    function getFile(string $name)
    {
        return $this->props["__files"][$name] ?? null;
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
        return in_array($accept->value, explode(",", $this->headers['accept'] ?? ""));
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
                            $this->props["__files"][$name] = [
                                'type' => $headers['Content-Type'] ?? 'application/octet-stream',
                                'name' => $filename,
                                'tmp_name' => $tempFilePath,
                                'size' => strlen($body),
                            ];
                        } else {
                            $this->props["__body"][$name] = trim($body);
                        }
                    }
                }
            }
        } else {
            $jsonArray = json_decode($rawBody, true) ?? [];
            foreach ($jsonArray as $key => $val) {
                $this->props["__body"][$key] = $val;
            }
        }
    }
}
