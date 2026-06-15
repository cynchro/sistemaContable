<?php

namespace App\Support;

final class Response
{
    private int $status  = 200;
    private array $headers = ['Content-Type' => 'application/json; charset=utf-8'];
    private ?array $body    = null;

    public function withStatus(int $status): static
    {
        $clone         = clone $this;
        $clone->status = $status;
        return $clone;
    }

    public function withHeader(string $name, string $value): static
    {
        $clone                 = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function json(array $data, int $status = 200): static
    {
        $clone         = clone $this;
        $clone->status = $status;
        $clone->body   = $data;
        return $clone;
    }

    public static function success(array $data = [], int $status = 200): static
    {
        return (new static())->json([
            'success' => true,
            'data'    => $data,
        ], $status);
    }

    public static function error(string $message, int $status = 422): static
    {
        return (new static())->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }

    public static function redirect(string $url, int $status = 302): static
    {
        $clone                      = new static();
        $clone->status              = $status;
        $clone->headers['Location'] = $url;
        unset($clone->headers['Content-Type']);
        return $clone;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** @return array<string, mixed>|null */
    public function getBody(): ?array
    {
        return $this->body;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($this->body !== null) {
            $encoded = json_encode($this->body, JSON_UNESCAPED_UNICODE);
            echo $encoded !== false
                ? $encoded
                : '{"success":false,"message":"Response encoding error."}';
        }
    }
}
