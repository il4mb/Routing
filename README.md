# Il4mb Routing Library

Il4mb Routing is a simple and flexible PHP routing library designed to make request handling and middleware execution intuitive and efficient.

---

## Features

- **PSR-4 Autoloading**
- Attribute-based route definitions
- Middleware support for request preprocessing
- Built with PHP 8.0+

---

## Installation

Install the library using Composer:

```bash
composer require il4mb/routing
```

---

## Getting Started

### Define a Route

You can define routes in your controllers using PHP attributes:

```php
use Il4mb\Routing\Http\Method;
use Il4mb\Routing\Map\Route;

class AdminController
{
    #[Route(Method::GET, "/home")]
    public function home()
    {
        return "Welcome to the Admin Home!";
    }
}
```

---

### Middleware

Create a middleware by implementing the `Il4mb\Routing\Middlewares\Middleware` interface:

```php
namespace App\Middlewares;

use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;
use Closure;

class AuthMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // do something 

        return $next($request);
    }
}
```

Use the middleware in your routes:

```php
#[Route(Method::GET, "/dashboard", middlewares: [AuthMiddleware::class])]
public function dashboard()
{
    return "Welcome to the dashboard!";
}
```

---

## Example Usage

### Initialize Middleware Executor

```php
use Il4mb\Routing\Middlewares\MiddlewareExecutor;
use Il4mb\Routing\Http\Request;

$executor = new MiddlewareExecutor([
    App\Middlewares\AuthMiddleware::class
]);

$request = new Request();
$response = $executor($request, function ($req) {
    return new Response("Request handled successfully.");
});

echo $response->getBody();
```

---

## License

This project is licensed under the MIT License. See the `LICENSE` file for details.

---

## Author

**Ilham B**  
Email: durianbohong@gmail.com

