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
    protected array $guzzleOptions = [];
    protected bool $asFormData = false;
    protected string $cacheStrategy = 'never'; // never, always, conditional
    protected int $cacheTtl = 3600; // segundos
    protected ?int $cacheThreshold = null; // número de chamadas
    protected ?int $cacheThresholdPeriod = null; // período em segundos
    protected ?string $cacheExpiresField = null; // campo da resposta com data de expiração
    protected string $cacheExpiresMask = 'datetime'; // datetime, seconds, minutes
    protected ?array $cacheOnlyStatuses = null;   // array<int> HTTP statuses que SÓ devem ser cacheados
    protected ?array $cacheExceptStatuses = null; // array<int> HTTP statuses que NÃO devem ser cacheados

    public function __construct()
    {
        $this->loggingEnabled = config('http-service.logging_enabled', true);
        $this->rateLimitEnabled = config('http-service.rate_limit_enabled', true);
        $this->defaultBlockTime = config('http-service.default_block_time', 15);
        $this->timeout = config('http-service.timeout', 30);
        $this->forceProtocol = config('http-service.force_protocol', null);
        $this->cacheStrategy = config('http-service.cache_strategy', 'never');
        $this->cacheTtl = config('http-service.cache_ttl', 3600);
        $this->cacheThreshold = config('http-service.cache_threshold', null);
        $this->cacheThresholdPeriod = config('http-service.cache_threshold_period', null);
        $this->loggingConnection = config('http-service.logging_connection', null);
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

        // Verifica se deve usar cache
        $cacheKey = $this->generateCacheKey($method, $url, $payload);
        $shouldUseCache = $this->shouldUseCache($cacheKey);

        if ($shouldUseCache && $cachedResponse = $this->getCachedResponse($cacheKey)) {
            return $cachedResponse;
        }

        // Verifica rate limiting
        if ($this->rateLimitEnabled) {
            $this->checkRateLimit($domain);
        }

        try {
            // Prepara a requisição
            $request = Http::timeout($this->timeout);

            // Adiciona opções do Guzzle (incluindo SSL)
            if (!empty($this->guzzleOptions)) {
                $request = $request->withOptions($this->guzzleOptions);
            }

            // Define formato de envio como formulário
            if ($this->asFormData) {
                $request = $request->asForm();
            }

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
                $this->logRequest($url, $method, $payload, $response, $responseTime, $headers);
            }

            // Decide se deve armazenar em cache usando possíveis filtros por status
            $shouldCacheResponse = $shouldUseCache;

            if ($shouldUseCache && ($this->cacheOnlyStatuses !== null || $this->cacheExceptStatuses !== null)) {
                $status = $response->status();
                if ($this->cacheOnlyStatuses !== null) {
                    $shouldCacheResponse = in_array($status, $this->cacheOnlyStatuses, true);
                } elseif ($this->cacheExceptStatuses !== null) {
                    $shouldCacheResponse = !in_array($status, $this->cacheExceptStatuses, true);
                }
            }

            if ($shouldCacheResponse) {
                $this->cacheResponse($cacheKey, $response);
            }

            return $response;
        } catch (\Exception $e) {
            $responseTime = microtime(true) - $startTime;

            // Registra erro
            if ($this->loggingEnabled) {
                $this->logError($url, $method, $payload, $e->getMessage(), $responseTime, $headers);
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
        float $responseTime,
        array $headers = []
    ): void {
        HttpRequestLog::create([
            'url' => $url,
            'method' => strtoupper($method),
            'headers' => $headers,
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
        float $responseTime,
        array $headers = []
    ): void {
        HttpRequestLog::create([
            'url' => $url,
            'method' => strtoupper($method),
            'headers' => $headers,
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

    /**
     * Define opções personalizadas do Guzzle (ex: configuração SSL)
     */
    public function withOptions(array $options): self
    {
        $this->guzzleOptions = array_merge($this->guzzleOptions, $options);
        return $this;
    }

    /**
     * Envia dados como formulário (application/x-www-form-urlencoded)
     */
    public function asForm(): self
    {
        $clone = clone $this;
        $clone->asFormData = true;
        return $clone;
    }

    /**
     * Sempre usar cache para as requisições
     */
    public function withCache(int $ttl = 3600): self
    {
        $clone = clone $this;
        $clone->cacheStrategy = 'always';
        $clone->cacheTtl = $ttl;
        return $clone;
    }

    /**
     * Nunca usar cache (padrão)
     */
    public function withoutCache(): self
    {
        $this->cacheStrategy = 'never';
        return $this;
    }

    /**
     * Usar cache apenas se a mesma requisição for chamada X vezes em um período
     *
     * @param int $threshold Número de chamadas necessárias para ativar cache
     * @param int $period Período em segundos para contar as chamadas
     * @param int $ttl Tempo de vida do cache em segundos
     */
    public function cacheWhen(int $threshold, int $period, int $ttl = 3600): self
    {
        $this->cacheStrategy = 'conditional';
        $this->cacheThreshold = $threshold;
        $this->cacheThresholdPeriod = $period;
        $this->cacheTtl = $ttl;
        return $this;
    }

    /**
     * Gera chave única para cache baseada na requisição
     */
    protected function generateCacheKey(string $method, string $url, array $payload): string
    {
        $data = [
            'method' => $method,
            'url' => $url,
            'payload' => $payload,
        ];

        return 'http_service_' . md5(json_encode($data));
    }

    /**
     * Verifica se deve usar cache para esta requisição
     */
    protected function shouldUseCache(string $cacheKey): bool
    {
        if ($this->cacheStrategy === 'never') {
            return false;
        }

        if ($this->cacheStrategy === 'always') {
            return true;
        }

        if ($this->cacheStrategy === 'conditional') {
            return $this->shouldUseCacheConditional($cacheKey);
        }

        return false;
    }

    /**
     * Verifica se deve usar cache baseado em frequência de chamadas
     */
    protected function shouldUseCacheConditional(string $cacheKey): bool
    {
        if (!$this->cacheThreshold || !$this->cacheThresholdPeriod) {
            return false;
        }

        $counterKey = $cacheKey . '_counter';
        $counter = \Illuminate\Support\Facades\Cache::get($counterKey, []);

        // Remove chamadas antigas fora do período
        $now = time();
        $counter = array_filter($counter, function ($timestamp) use ($now) {
            return ($now - $timestamp) <= $this->cacheThresholdPeriod;
        });

        // Adiciona nova chamada
        $counter[] = $now;

        // Salva contador
        \Illuminate\Support\Facades\Cache::put(
            $counterKey,
            $counter,
            $this->cacheThresholdPeriod
        );

        // Verifica se atingiu o threshold
        return count($counter) >= $this->cacheThreshold;
    }

    /**
     * Recupera resposta do cache
     */
    protected function getCachedResponse(string $cacheKey): ?Response
    {
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (!$cached) {
            return null;
        }

        // Reconstrói o Response do Laravel
        return new Response(new \GuzzleHttp\Psr7\Response(
            $cached['status'],
            $cached['headers'],
            $cached['body']
        ));
    }

    /**
     * Armazena resposta no cache
     */
    protected function cacheResponse(string $cacheKey, Response $response): void
    {
        $cacheData = [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->body(),
        ];

        // Calcula TTL dinâmico se campo de expiração estiver configurado
        $ttl = $this->cacheTtl;
        if ($this->cacheExpiresField) {
            $dynamicTtl = $this->calculateTtlFromResponse($response);
            if ($dynamicTtl !== null) {
                $ttl = $dynamicTtl;
            }
        }

        \Illuminate\Support\Facades\Cache::put($cacheKey, $cacheData, $ttl);
    }

    /**
     * Define campo da resposta que contém o tempo de expiração do cache
     *
     * @param string $field Caminho do campo na resposta (ex: 'expirationTime', 'data.auth.expires')
     * @param int|null $fallbackTtl TTL padrão caso o campo não seja encontrado (em segundos)
     * @return self
     */
    public function cacheUsingExpires(string $field, ?int $fallbackTtl = null): self
    {
        $clone = clone $this;
        $clone->cacheStrategy = 'always';
        $clone->cacheExpiresField = $field;
        if ($fallbackTtl !== null) {
            $clone->cacheTtl = $fallbackTtl;
        }
        return $clone;
    }

    /**
     * Cachear somente respostas com códigos HTTP informados.
     * Ex: ->cacheOnly([\Symfony\Component\HttpFoundation\Response::HTTP_OK])
     */
    public function cacheOnly(array $statuses, ?int $ttl = null): self
    {
        $clone = clone $this;
        $clone->cacheOnlyStatuses = array_map('intval', array_values($statuses));
        $clone->cacheExceptStatuses = null;
        if ($ttl !== null) {
            $clone->cacheTtl = $ttl;
        }
        $clone->cacheStrategy = 'always';
        return $clone;
    }

    /**
     * Cachear todas as respostas, exceto os códigos HTTP informados.
     */
    public function cacheExcept(array $statuses, ?int $ttl = null): self
    {
        $clone = clone $this;
        $clone->cacheExceptStatuses = array_map('intval', array_values($statuses));
        $clone->cacheOnlyStatuses = null;
        if ($ttl !== null) {
            $clone->cacheTtl = $ttl;
        }
        $clone->cacheStrategy = 'always';
        return $clone;
    }

    /**
     * Limpa filtros de status de cache
     */
    public function clearCacheStatusFilters(): self
    {
        $this->cacheOnlyStatuses = null;
        $this->cacheExceptStatuses = null;
        return $this;
    }

    /**
     * Define que o campo de expiração contém data/hora (formato Y-m-d H:i:s ou ISO 8601)
     *
     * @return self
     */
    public function expiresAsDatetime(): self
    {
        $this->cacheExpiresMask = 'datetime';
        return $this;
    }

    /**
     * Define que o campo de expiração contém segundos
     *
     * @return self
     */
    public function expiresAsSeconds(): self
    {
        $this->cacheExpiresMask = 'seconds';
        return $this;
    }

    /**
     * Define que o campo de expiração contém minutos
     *
     * @return self
     */
    public function expiresAsMinutes(): self
    {
        $this->cacheExpiresMask = 'minutes';
        return $this;
    }

    /**
     * Calcula o TTL baseado no campo de expiração da resposta
     *
     * @param Response $response
     * @return int|null TTL em segundos, ou null se não conseguir calcular
     */
    protected function calculateTtlFromResponse(Response $response): ?int
    {
        $data = $response->json();

        if (!$data || !$this->cacheExpiresField) {
            return null;
        }

        // Extrai o valor do campo usando notação de ponto (ex: 'data.auth.expires')
        $value = $this->getNestedValue($data, $this->cacheExpiresField);

        if ($value === null) {
            return null;
        }

        // Converte o valor para segundos baseado na máscara
        return match ($this->cacheExpiresMask) {
            'seconds' => (int) $value,
            'minutes' => (int) $value * 60,
            'datetime' => $this->calculateSecondsUntil($value),
            default => null,
        };
    }

    /**
     * Obtém valor aninhado de um array usando notação de ponto
     *
     * @param array $array
     * @param string $key
     * @return mixed
     */
    protected function getNestedValue(array $array, string $key)
    {
        $keys = explode('.', $key);

        foreach ($keys as $k) {
            if (!is_array($array) || !isset($array[$k])) {
                return null;
            }
            $array = $array[$k];
        }

        return $array;
    }

    /**
     * Calcula quantos segundos faltam até uma data/hora
     *
     * @param string $datetime Data no formato Y-m-d H:i:s ou ISO 8601
     * @return int|null Segundos até a data, ou null se inválido
     */
    protected function calculateSecondsUntil(string $datetime): ?int
    {
        try {
            $expiresAt = new \DateTime($datetime);
            $now = new \DateTime();

            $diff = $expiresAt->getTimestamp() - $now->getTimestamp();

            // Retorna no mínimo 1 segundo, no máximo o valor calculado
            return max(1, $diff);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Limpa todo o cache de requisições HTTP
     */
    public function clearCache(): void
    {
        // Laravel não tem um método nativo para limpar por prefixo
        // Esta é uma implementação básica
        \Illuminate\Support\Facades\Cache::flush();
    }
}
