<?php

namespace Kailyn\Http;

class Response
{
    private string $content;
    private int $status;
    private array $headers;

    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->content = $content;
        $this->status = $status;
        $this->headers = array_merge(['Content-Type' => 'text/html; charset=utf-8'], $headers);
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): static
    {
        return new static(
            json_encode($data, JSON_UNESCAPED_UNICODE),
            $status,
            array_merge(['Content-Type' => 'application/json'], $headers)
        );
    }

    public static function redirect(string $url, int $status = 302, array $headers = []): static
    {
        return new static('', $status, array_merge(['Location' => $url], $headers));
    }

    public static function noContent(int $status = 204): static
    {
        return new static('', $status);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }

        echo $this->content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function setHeader(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;
        return $this;
    }
}
