<?php

namespace Il4mb\Routing\Adapters\Http;

use Il4mb\Routing\Engine\RoutingContext;
use Il4mb\Routing\Http\Request;

final class HttpContextFactory
{
    public static function fromRequest(Request $request): RoutingContext
    {
        $protocol = $request->uri->getProtocol() ?? 'http';
        $host = $request->uri->getHost() ?? '';
        $path = $request->uri->getPath() ?? '/';
        $method = $request->method;

        return new RoutingContext(
            protocol: (string)$protocol,
            host: (string)$host,
            path: rawurldecode((string)$path),
            method: $method,
            headers: $request->headers,
            attributes: [
                // Keep a reference for adapters/handlers.
                'http.request' => $request,
            ],
        );
    }
}
