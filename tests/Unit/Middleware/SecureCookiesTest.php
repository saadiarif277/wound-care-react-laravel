<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\SecureCookies;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class SecureCookiesTest extends TestCase
{
    protected SecureCookies $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SecureCookies();
    }

    public function test_adds_secure_flag_for_https_requests()
    {
        $request = Request::create('https://example.com/test', 'GET');
        $response = new Response();
        $response->headers->setCookie(cookie('test_cookie', 'value'));

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $cookieHeader = $result->headers->get('Set-Cookie');
        $this->assertStringContainsString('secure', $cookieHeader);
    }

    public function test_removes_secure_flag_for_http_requests()
    {
        $request = Request::create('http://localhost/test', 'GET');
        $response = new Response();
        $response->headers->set('Set-Cookie', 'test_cookie=value; path=/; secure; httponly');

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $cookieHeader = $result->headers->get('Set-Cookie');
        $this->assertStringNotContainsString('secure', $cookieHeader);
        $this->assertStringContainsString('httponly', $cookieHeader);
    }

    public function test_adds_samesite_attribute_if_missing()
    {
        $request = Request::create('https://example.com/test', 'GET');
        $response = new Response();
        $response->headers->set('Set-Cookie', 'test_cookie=value; path=/');

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $cookieHeader = $result->headers->get('Set-Cookie');
        $this->assertStringContainsString('samesite=', strtolower($cookieHeader));
    }

    public function test_adds_httponly_to_session_cookies()
    {
        // Set the session cookie name for testing
        config(['session.cookie' => 'test_session']);

        $request = Request::create('https://example.com/test', 'GET');
        $response = new Response();
        $response->headers->set('Set-Cookie', 'test_session=value; path=/');

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $cookieHeader = $result->headers->get('Set-Cookie');
        $this->assertStringContainsString('httponly', $cookieHeader);
    }

    public function test_handles_multiple_cookies()
    {
        $request = Request::create('https://example.com/test', 'GET');
        $response = new Response();
        
        // Add multiple cookies
        $response->headers->set('Set-Cookie', 'cookie1=value1; path=/', false);
        $response->headers->set('Set-Cookie', 'cookie2=value2; path=/', false);

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $cookies = $result->headers->all()['set-cookie'] ?? [];
        $this->assertCount(2, $cookies);
        
        foreach ($cookies as $cookie) {
            $this->assertStringContainsString('secure', $cookie);
            $this->assertStringContainsString('samesite=', strtolower($cookie));
        }
    }

    public function test_preserves_existing_attributes()
    {
        $request = Request::create('https://example.com/test', 'GET');
        $response = new Response();
        $response->headers->set('Set-Cookie', 'test_cookie=value; path=/test; domain=.example.com; Max-Age=3600');

        $result = $this->middleware->handle($request, function ($req) use ($response) {
            return $response;
        });

        $cookieHeader = $result->headers->get('Set-Cookie');
        $this->assertStringContainsString('path=/test', $cookieHeader);
        $this->assertStringContainsString('domain=.example.com', $cookieHeader);
        $this->assertStringContainsString('Max-Age=3600', $cookieHeader);
        $this->assertStringContainsString('secure', $cookieHeader);
    }
} 