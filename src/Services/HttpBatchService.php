<?php

namespace ThreeRN\HttpService\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use ThreeRN\HttpService\Jobs\ProcessHttpRequestJob;

class HttpBatchService
{
    protected int $requestsPerInterval = 10;
    protected int $intervalSeconds = 60;
    protected bool $async = false;
    protected array $options = [];

    /**
     * Define quantas requisições podem ser feitas por intervalo
     */
    public function rateLimit(int $requests, int $intervalSeconds): self
    {
        $this->requestsPerInterval = $requests;
        $this->intervalSeconds = $intervalSeconds;
        return $this;
    }

    /**
     * Define execução assíncrona (usando Jobs)
     */
    public function async(): self
    {
        $this->async = true;
        return $this;
    }

    /**
     * Define execução síncrona
     */
    public function sync(): self
    {
        $this->async = false;
        return $this;
    }

    /**
     * Define opções globais para todas as requisições do batch
     */
    public function withOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Executa uma lista de requisições com rate limiting
     * 
     * @param array $requests Array de requisições no formato:
     * [
     *   ['method' => 'GET', 'url' => '...', 'data' => [], 'headers' => [], 'options' => []],
     *   ['method' => 'POST', 'url' => '...', 'data' => [], 'headers' => [], 'options' => []],
     * ]
     * @return array|string Array de respostas (sync) ou ID do batch (async)
     */
    public function execute(array $requests): array|string
    {
        if ($this->async) {
            return $this->executeAsync($requests);
        }

        return $this->executeSync($requests);
    }

    /**
     * Executa requisições de forma síncrona com rate limiting
     */
    protected function executeSync(array $requests): array
    {
        $results = [];
        $httpService = new HttpService();
        $delay = ($this->intervalSeconds * 1000000) / $this->requestsPerInterval; // microsegundos

        foreach ($requests as $index => $request) {
            // Aplica throttling entre requisições
            if ($index > 0) {
                usleep((int) $delay);
            }

            try {
                $service = clone $httpService;
                
                // Aplica opções globais
                $this->applyOptions($service, $this->options);
                
                // Aplica opções específicas da requisição
                if (isset($request['options'])) {
                    $this->applyOptions($service, $request['options']);
                }

                $method = strtoupper($request['method'] ?? 'GET');
                $url = $request['url'];
                $data = $request['data'] ?? [];
                $headers = $request['headers'] ?? [];

                $response = match ($method) {
                    'GET' => $service->get($url, $data, $headers),
                    'POST' => $service->post($url, $data, $headers),
                    'PUT' => $service->put($url, $data, $headers),
                    'PATCH' => $service->patch($url, $data, $headers),
                    'DELETE' => $service->delete($url, $data, $headers),
                    default => throw new \InvalidArgumentException("Invalid HTTP method: {$method}"),
                };

                $results[] = [
                    'success' => true,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'json' => $response->json(),
                    'headers' => $response->headers(),
                    'request' => $request,
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'request' => $request,
                ];
            }
        }

        return $results;
    }

    /**
     * Executa requisições de forma assíncrona usando Jobs
     */
    protected function executeAsync(array $requests): string
    {
        $batchId = Str::uuid()->toString();
        $delaySeconds = 0;
        $incrementDelay = $this->intervalSeconds / $this->requestsPerInterval;

        foreach ($requests as $index => $request) {
            $method = $request['method'] ?? 'GET';
            $url = $request['url'];
            $data = $request['data'] ?? [];
            $headers = $request['headers'] ?? [];
            
            // Mescla opções globais com opções específicas
            $options = array_merge($this->options, $request['options'] ?? []);

            // Despacha job com delay para respeitar rate limit
            ProcessHttpRequestJob::dispatch(
                $method,
                $url,
                $data,
                $headers,
                $options,
                $batchId,
                $index
            )->delay(now()->addSeconds((int) $delaySeconds));

            $delaySeconds += $incrementDelay;
        }

        // Armazena metadados do batch
        Cache::put("http_batch_{$batchId}_meta", [
            'total' => count($requests),
            'started_at' => now(),
            'requests_per_interval' => $this->requestsPerInterval,
            'interval_seconds' => $this->intervalSeconds,
        ], 3600);

        return $batchId;
    }

    /**
     * Verifica o status de um batch assíncrono
     */
    public function checkBatchStatus(string $batchId): array
    {
        $meta = Cache::get("http_batch_{$batchId}_meta");
        
        if (!$meta) {
            return [
                'status' => 'not_found',
                'message' => 'Batch não encontrado ou expirado',
            ];
        }

        $results = [];
        $completed = 0;

        for ($i = 0; $i < $meta['total']; $i++) {
            $result = Cache::get("http_batch_{$batchId}_result_{$i}");
            
            if ($result) {
                $completed++;
                $results[] = $result;
            } else {
                $results[] = [
                    'status' => 'pending',
                    'index' => $i,
                ];
            }
        }

        return [
            'status' => $completed === $meta['total'] ? 'completed' : 'processing',
            'total' => $meta['total'],
            'completed' => $completed,
            'pending' => $meta['total'] - $completed,
            'started_at' => $meta['started_at'],
            'results' => $results,
        ];
    }

    /**
     * Aguarda a conclusão de um batch assíncrono
     */
    public function waitForBatch(string $batchId, int $timeoutSeconds = 300): array
    {
        $startTime = time();
        
        while (time() - $startTime < $timeoutSeconds) {
            $status = $this->checkBatchStatus($batchId);
            
            if ($status['status'] === 'completed') {
                return $status;
            }
            
            if ($status['status'] === 'not_found') {
                return $status;
            }
            
            sleep(2); // Aguarda 2 segundos antes de verificar novamente
        }
        
        return [
            'status' => 'timeout',
            'message' => 'Timeout aguardando conclusão do batch',
        ];
    }

    /**
     * Aplica opções ao serviço HTTP
     */
    protected function applyOptions(HttpService $service, array $options): void
    {
        if (isset($options['timeout'])) {
            $service->timeout($options['timeout']);
        }

        if (isset($options['withCache'])) {
            $service->withCache($options['withCache']);
        }

        if (isset($options['withoutLogging']) && $options['withoutLogging']) {
            $service->withoutLogging();
        }

        if (isset($options['withoutRateLimit']) && $options['withoutRateLimit']) {
            $service->withoutRateLimit();
        }

        if (isset($options['guzzleOptions'])) {
            $service->withOptions($options['guzzleOptions']);
        }

        if (isset($options['asForm']) && $options['asForm']) {
            $service->asForm();
        }

        if (isset($options['forceHttp']) && $options['forceHttp']) {
            $service->forceHttp();
        }

        if (isset($options['forceHttps']) && $options['forceHttps']) {
            $service->forceHttps();
        }
    }
}
