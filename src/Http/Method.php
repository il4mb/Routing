<?php

namespace Il4mb\Routing\Http;

enum Method: string
{
    case GET = "GET";
    case POST = "POST";
    case PUT = "PUT";
    case DELETE = "DELETE";
    case PATCH = "PATH";
}
