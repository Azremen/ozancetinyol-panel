<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class StaticSecurityHeadersTest extends TestCase
{
    public function test_apache_sets_nosniff_for_all_responses_not_only_storage_or_successes(): void
    {
        $htaccess = file_get_contents(dirname(__DIR__, 2).'/public/.htaccess');
        $headersBlock = explode('<IfModule mod_headers.c>', $htaccess)[1];
        $globalHeaders = explode('<FilesMatch', $headersBlock)[0];

        $this->assertStringContainsString('Header always set X-Content-Type-Options "nosniff"', $globalHeaders);
    }

    public function test_development_server_sends_nosniff_for_static_storage_and_errors(): void
    {
        $root = dirname(__DIR__, 2);
        $name = 'nosniff-test-'.bin2hex(random_bytes(8));
        $directory = $root.'/storage/app/public/'.$name;
        $storageLink = $root.'/public/storage';
        $createdLink = ! file_exists($storageLink);
        $server = null;
        mkdir($directory, 0755, true);
        copy($root.'/public/favicon.ico', $directory.'/favicon.ico');
        file_put_contents($directory.'/upload.txt', 'static storage content');
        file_put_contents($directory.'/blocked.php', '<?php echo "must not execute";');

        try {
            if ($createdLink) {
                (new Filesystem)->link($root.'/storage/app/public', $storageLink);
            }

            $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
            $this->assertIsResource($socket, $error);
            $address = stream_socket_get_name($socket, false);
            fclose($socket);

            $server = new Process([PHP_BINARY, '-S', $address, $root.'/server.php'], $root.'/public', [
                'APP_ENV' => 'testing',
                'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
                'APP_CONFIG_CACHE' => $root.'/bootstrap/cache/config-testing.php',
                'APP_ROUTES_CACHE' => $root.'/bootstrap/cache/routes-testing.php',
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => ':memory:',
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array',
            ]);
            $server->setTimeout(10);
            $server->start();
            $this->assertTrue($server->waitUntil(
                fn (string $type, string $output): bool => str_contains($output, 'Development Server')
            ), $server->getErrorOutput());
            $this->assertTrue($server->isRunning(), $server->getErrorOutput());

            foreach ([
                ['/storage/'.$name.'/favicon.ico', 'GET', 200, null, null],
                ['/storage/'.$name.'/upload.txt', 'GET', 200, 'static storage content', null],
                ['/storage/'.$name.'/favicon.ico', 'HEAD', 200, '', null],
                ['/storage/'.$name.'/favicon.ico', 'GET', 206, null, 'Range: bytes=0-3'],
                ['/storage/'.$name.'/favicon.ico', 'GET', 416, '', 'Range: bytes=999999999-9999999999'],
                ['/storage/'.$name.'/favicon.ico', 'GET', 304, '', 'If-Modified-Since: '.gmdate('D, d M Y H:i:s', filemtime($directory.'/favicon.ico')).' GMT'],
                ['/storage/'.$name.'/blocked.php', 'GET', 403, '', null],
                // Missing public uploads fall through to Laravel's signed private-disk route.
                ['/storage/'.$name.'/missing.ico', 'GET', 403, null, null],
                ['/'.$name.'-missing.ico', 'GET', 404, null, null],
                ['/favicon.ico', 'GET', 200, null, null],
            ] as [$path, $method, $status, $expectedBody, $extraHeader]) {
                $context = stream_context_create(['http' => [
                    'method' => $method,
                    'header' => $extraHeader ?? '',
                    'ignore_errors' => true,
                    'timeout' => 10,
                ]]);
                $body = file_get_contents('http://'.$address.$path, false, $context);
                $headers = array_map('strtolower', $http_response_header);

                $this->assertStringContainsString(' '.$status.' ', $headers[0], $path);
                $this->assertContains('x-content-type-options: nosniff', $headers, $path);
                $this->assertCount(1, array_keys($headers, 'x-content-type-options: nosniff'), $path);

                if ($expectedBody !== null) {
                    $this->assertSame($expectedBody, $body, $path);
                }

                if ($status === 200 && $method === 'GET' && str_ends_with($path, '.ico')) {
                    $this->assertSame(file_get_contents($root.'/public/favicon.ico'), $body);
                    $this->assertContains('content-type: image/x-icon', $headers);
                }
            }
        } finally {
            $server?->stop();
            unlink($directory.'/favicon.ico');
            unlink($directory.'/upload.txt');
            unlink($directory.'/blocked.php');
            rmdir($directory);

            if ($createdLink && file_exists($storageLink)) {
                if (PHP_OS_FAMILY === 'Windows') {
                    rmdir($storageLink);
                } else {
                    unlink($storageLink);
                }
            }
        }
    }
}
