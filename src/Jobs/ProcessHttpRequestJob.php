<?php

namespace ThreeRN\HttpService\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ThreeRN\HttpService\Services\HttpService;
use Illuminate\Support\Facades\Cache;

class ProcessHttpRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $method;
    public string $url;
    public array $data;
    public array $headers;
    public array $options;
    public string $batchId;
    public int $index;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $method,
        string $url,
        array $data = [],
        array $headers = [],
        array $options = [],
        string $batchId = '',
        int $index = 0
    ) {
        $this->method = $method;
        $this->url = $url;
        $this->data = $data;
        $this->headers = $headers;
        $this->options = $options;
        $this->batchId = $batchId;
        $this->index = $index;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $httpService = new HttpService();

        // Aplica opções customizadas se fornecidas
        if (isset($this->options['timeout'])) {
            $httpService->timeout($this->options['timeout']);
        }

        if (isset($this->options['withCache'])) {
            $httpService->withCache($this->options['withCache']);
        }

        if (isset($this->options['withoutLogging']) && $this->options['withoutLogging']) {
            $httpService->withoutLogging();
        }

        if (isset($this->options['withoutRateLimit']) && $this->options['withoutRateLimit']) {
            $httpService->withoutRateLimit();
        }

        if (isset($this->options['guzzleOptions'])) {
            $httpService->withOptions($this->options['guzzleOptions']);
        }

        if (isset($this->options['asForm']) && $this->options['asForm']) {
            $httpService->asForm();
        }

        try {
            // Executa a requisição
            $response = match (strtoupper($this->method)) {
                'GET' => $httpService->get($this->url, $this->data, $this->headers),
                'POST' => $httpService->post($this->url, $this->data, $this->headers),
                'PUT' => $httpService->put($this->url, $this->data, $this->headers),
                'PATCH' => $httpService->patch($this->url, $this->data, $this->headers),
                'DELETE' => $httpService->delete($this->url, $this->data, $this->headers),
                default => throw new \InvalidArgumentException("Invalid HTTP method: {$this->method}"),
            };

            // Armazena resultado no cache para recuperação posterior
            $this->storeResult($response->status(), $response->body(), null);

        } catch (\Exception $e) {
            $this->storeResult(0, null, $e->getMessage());
        }
    }

    /**
     * Armazena o resultado da requisição
     */
    protected function storeResult(int $status, ?string $body, ?string $error): void
    {
        if (empty($this->batchId)) {
            return;
        }

        $key = "http_batch_{$this->batchId}_result_{$this->index}";
        
        Cache::put($key, [
            'status' => $status,
            'body' => $body,
            'error' => $error,
            'completed_at' => now(),
        ], 3600); // 1 hora
    }
}
