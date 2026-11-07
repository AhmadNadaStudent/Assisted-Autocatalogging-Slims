<?php
namespace AssistedAutocataloguing\Core;

/**
 * HTTP Request Handler
 */
class Request
{
    private array $get;
    private array $post;
    private array $files;
    private array $server;
    private ?array $jsonBody = null;

    public function __construct()
    {
        $this->get = $_GET ?? [];
        $this->post = $_POST ?? [];
        $this->files = $_FILES ?? [];
        $this->server = $_SERVER ?? [];
    }

    public static function createFromGlobals(): self
    {
        return new self();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function isPost(): bool
    {
        return ($this->server['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    public function isAjax(): bool
    {
        return (!empty($this->server['HTTP_X_REQUESTED_WITH']) &&
            strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (str_contains($this->server['HTTP_ACCEPT'] ?? '', 'application/json'));
    }

    public function json(): array
    {
        if ($this->jsonBody === null) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            $this->jsonBody = is_array($data) ? $data : [];
        }
        return $this->jsonBody;
    }

    public function getHeader(string $name): ?string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$serverKey] ?? null;
    }
}
