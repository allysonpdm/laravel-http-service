<?php

namespace ThreeRN\HttpService\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\PendingRequest;
use ThreeRN\HttpService\Models\HttpRequestLog;
use ThreeRN\HttpService\Models\RateLimitControl;
use ThreeRN\HttpService\Exceptions\RateLimitException;

class HttpService
{
    protected bool $loggingEnabled;
    protected bool $rateLimitEnabled;
    protected int $defaultBlockTime;
    protected int $timeout;
    protected ?string $forceProtocol;

    public function __construct()
    {
        $this->loggingEnabled = config('http-service.logging_enabled', true);
        $this->rateLimitEnabled = config('http-service.rate_limit_enabled', true);
        $this->defaultBlockTime = config('http-service.default_block_time', 15);
        $this->timeout = config('http-service.timeout', 30);
        $this->forceProtocol = config('http-service.force_protocol', null);
    }

    /**
     * Executa uma requisição GET
     */
    public function get(string $url, array $query = [], array $headers = []): Response
    {
        return $this->request('GET', $url, [
            'query' => $query,
            'headers' => $headers,
        ]);
    }

    /**
     * Executa uma requisição POST
     */
    public function post(string $url, array $data = [], array $headers = []): Response
    {
        return $this->request('POST', $url, [
            'data' => $data,
            'headers' => $headers,
        ]);
    }

    /**
     * Executa uma requisição PUT
     */
    public function put(string $url, array $data = [], array $headers = []): Response
    {
        return $this->request('PUT', $url, [
            'data' => $data,
            'headers' => $headers,
        ]);
    }

    /**
     * Executa uma requisição PATCH
     */
    public function patch(string $url, array $data = [], array $headers = []): Response
    {
        return $this->request('PATCH', $url, [
            'data' => $data,
            'headers' => $headers,
        ]);
    }

    /**
     * Executa uma requisição DELETE
     */
    public function delete(string $url, array $data = [], array $headers = []): Response
    {
        return $this->request('DELETE', $url, [
            'data' => $data,
            'headers' => $headers,
        ]);
    }

    /**
     * Método principal para executar requisições HTTP
     */
    protected function request(string $method, string $url, array $options = []): Response
    {
        // Força o protocolo se configurado
        $url = $this->applyProtocol($url);
        
        $domain = $this->extractDomain($url);
        $startTime = microtime(true);
        $payload = $options['data'] ?? $options['query'] ?? [];
        $headers = $options['headers'] ?? [];

        // Verifica rate limiting
        if ($this->rateLimitEnabled) {
            $this->checkRateLimit($domain);
        }

        try {
            // Prepara a requisição
            $request = Http::timeout($this->timeout);

            // Adiciona headers
            if (!empty($headers)) {
                $request = $request->withHeaders($headers);
            }

            // Executa a requisição baseado no método
            $response = match (strtoupper($method)) {
                'GET' => $request->get($url, $options['query'] ?? []),
                'POST' => $request->post($url, $options['data'] ?? []),
                'PUT' => $request->put($url, $options['data'] ?? []),
                'PATCH' => $request->patch($url, $options['data'] ?? []),
                'DELETE' => $request->delete($url, $options['data'] ?? []),
                default => throw new \InvalidArgumentException("Invalid HTTP method: {$method}"),
            };

            $responseTime = microtime(true) - $startTime;

            // Verifica se recebeu 429 e bloqueia o domínio
            if ($response->status() === 429 && $this->rateLimitEnabled) {
                $this->handleRateLimit($domain, $response);
            }

            // Registra a requisição
            if ($this->loggingEnabled) {
                $this->logRequest($url, $method, $payload, $response, $responseTime);
            }

            return $response;

        } catch (\Exception $e) {
            $responseTime = microtime(true) - $startTime;

            // Registra erro
            if ($this->loggingEnabled) {
                $this->logError($url, $method, $payload, $e->getMessage(), $responseTime);
            }

            throw $e;
        }
    }

    /**
     * Verifica se o domínio está bloqueado por rate limiting
     */
    protected function checkRateLimit(string $domain): void
    {
        if (RateLimitControl::isBlocked($domain)) {
            $remainingMinutes = RateLimitControl::getRemainingBlockTime($domain);
            throw new RateLimitException($domain, $remainingMinutes ?? 0);
        }
    }

    /**
     * Trata resposta 429 bloqueando o domínio
     */
    protected function handleRateLimit(string $domain, Response $response): void
    {
        // Tenta extrair o tempo de espera do header Retry-After
        $retryAfter = $response->header('Retry-After');
        $waitTime = $this->defaultBlockTime;

        if ($retryAfter) {
            // Retry-After pode ser em segundos ou uma data
            if (is_numeric($retryAfter)) {
                $waitTime = (int) ceil($retryAfter / 60); // Converte segundos para minutos
            }
        }

        RateLimitControl::blockDomain($domain, $waitTime);
    }

    /**
     * Registra a requisição no banco de dados
     */
    protected function logRequest(
        string $url,
        string $method,
        array $payload,
        Response $response,
        float $responseTime
    ): void {
        HttpRequestLog::create([
            'url' => $url,
            'method' => strtoupper($method),
            'payload' => $payload,
            'response' => $response->json() ?? ['body' => $response->body()],
            'status_code' => $response->status(),
            'response_time' => $responseTime,
        ]);
    }

    /**
     * Registra erro de requisição
     */
    protected function logError(
        string $url,
        string $method,
        array $payload,
        string $errorMessage,
        float $responseTime
    ): void {
        HttpRequestLog::create([
            'url' => $url,
            'method' => strtoupper($method),
            'payload' => $payload,
            'error_message' => $errorMessage,
            'response_time' => $responseTime,
        ]);
    }

    /**
     * Extrai o domínio de uma URL
     */
    protected function extractDomain(string $url): string
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? $url;
    }

    /**
     * Aplica o protocolo forçado na URL se configurado
     */
    protected function applyProtocol(string $url): string
    {
        if (empty($this->forceProtocol)) {
            return $url;
        }

        // Valida o protocolo
        $protocol = strtolower($this->forceProtocol);
        if (!in_array($protocol, ['http', 'https'])) {
            return $url;
        }

        // Substitui o protocolo na URL
        return preg_replace('/^https?:\/\//i', $protocol . '://', $url);
    }

    /**
     * Desabilita logging temporariamente
     */
    public function withoutLogging(): self
    {
        $this->loggingEnabled = false;
        return $this;
    }

    /**
     * Habilita logging temporariamente
     */
    public function withLogging(): self
    {
        $this->loggingEnabled = true;
        return $this;
    }

    /**
     * Desabilita rate limiting temporariamente
     */
    public function withoutRateLimit(): self
    {
        $this->rateLimitEnabled = false;
        return $this;
    }

    /**
     * Habilita rate limiting temporariamente
     */
    public function withRateLimit(): self
    {
        $this->rateLimitEnabled = true;
        return $this;
    }

    /**
     * Define timeout customizado para a próxima requisição
     */
    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Força o uso de HTTP em todas as requisições
     */
    public function forceHttp(): self
    {
        $this->forceProtocol = 'http';
        return $this;
    }

    /**
     * Força o uso de HTTPS em todas as requisições
     */
    public function forceHttps(): self
    {
        $this->forceProtocol = 'https';
        return $this;
    }

    /**
     * Remove o protocolo forçado
     */
    public function withoutForcedProtocol(): self
    {
        $this->forceProtocol = null;
        return $this;
    }
}
