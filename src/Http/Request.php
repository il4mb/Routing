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

    function __construct($options = [
        "clearState" => true
    ])
    {

        $this->method      = Method::tryFrom($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri         = new Url();
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
            // Check if multiple files are uploaded for the same field
            if (is_array($value['name'])) {
                // Iterate over the array of files and handle each one
                foreach ($value['name'] as $index => $filename) {
                    $fileData = [
                        'type' => $value['type'][$index],
                        'name' => $filename,
                        'tmp_name' => $value['tmp_name'][$index],
                        'size' => $value['size'][$index],
                        'error' => $value['error'][$index] ?? null
                    ];

                    // Assign the file data to the __files array
                    if (!isset($this->props["__files"][$key])) {
                        $this->props["__files"][$key] = [];
                    }
                    $this->props["__files"][$key][] = $fileData;
                }
            } else {
                // Single file upload case
                $fileData = [
                    'type' => $value['type'],
                    'name' => $value['name'],
                    'tmp_name' => $value['tmp_name'],
                    'size' => $value['size'],
                    'error' => $value['error'] ?? null
                ];

                // Assign the file data to the __files array
                $this->props["__files"][$key] = $fileData;
            }
        }

        if (isset($options['clearState']) && $options['clearState'] == true) {
            // clear state
            $_GET    = [];
            $_POST   = [];
            $_COOKIE = [];
            $_FILES  = [];
        }
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

    /**
     * @return array<string>|array<string, array<string>>|null
     */
    function getFile(string $name): mixed
    {
        return $this->props["__files"][$name] ?? null;
    }

    function getCookie(string $name)
    {
        return $this->props["__cookies"][$name] ?? null;
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
                            // Handle file uploads properly
                            $tempFilePath = tempnam(sys_get_temp_dir(), uniqid('upload_', true));
                            file_put_contents($tempFilePath, $body);

                            $fileData = [
                                'type' => $headers['Content-Type'] ?? 'application/octet-stream',
                                'name' => $filename,
                                'tmp_name' => $tempFilePath,
                                'size' => strlen($body),
                                'error' => null,
                            ];

                            if (strpos($name, '[]') !== false) {
                                // Handle list of files
                                $name = str_replace('[]', '', $name); // Remove [] from name
                                if (!isset($this->props["__files"][$name])) {
                                    $this->props["__files"][$name] = [];
                                }
                                $this->props["__files"][$name][] = $fileData;
                            } else {
                                // Single file input
                                $this->props["__files"][$name] = $fileData;
                            }
                        } else {
                            // Handle form fields
                            $this->parseNestedFormData($name, trim($body));
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


    private function parseNestedFormData($name, $value)
    {
        // Parse nested array syntax (e.g., "bahan-bahan[0][harga]")
        if (preg_match_all('/\[(.*?)\]/', $name, $matches)) {
            $keys = $matches[1];
            $baseKey = strtok($name, '['); // Get the base key (e.g., "bahan-bahan")

            // Initialize the base key if it doesn't exist
            if (!isset($this->props["__body"][$baseKey])) {
                $this->props["__body"][$baseKey] = [];
            }

            // Build the nested array structure
            $current = &$this->props["__body"][$baseKey];
            foreach ($keys as $key) {
                if (!isset($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
            $current = $value;
        } else {
            // Handle non-nested fields
            $this->props["__body"][$name] = $value;
        }
    }
}
