<?php

/**
 * Exemplos de uso do sistema de batch de requisições HTTP com rate limiting
 */

use ThreeRN\HttpService\Facades\HttpBatch;

// ============================================
// EXEMPLO 1: Batch Síncrono (Sequential)
// ============================================

// Executa 5 requisições com rate limit de 10 req/min
$requests = [
    ['method' => 'GET', 'url' => 'https://api.example.com/users/1'],
    ['method' => 'GET', 'url' => 'https://api.example.com/users/2'],
    ['method' => 'POST', 'url' => 'https://api.example.com/posts', 'data' => ['title' => 'Test']],
    ['method' => 'PUT', 'url' => 'https://api.example.com/posts/1', 'data' => ['title' => 'Updated']],
    ['method' => 'DELETE', 'url' => 'https://api.example.com/posts/1'],
];

$results = HttpBatch::rateLimit(10, 60) // 10 requisições por 60 segundos
    ->sync()
    ->execute($requests);

// Acessa os resultados
foreach ($results as $result) {
    if ($result['success']) {
        echo "Status: {$result['status']}\n";
        echo "Body: {$result['body']}\n";
    } else {
        echo "Erro: {$result['error']}\n";
    }
}

// ============================================
// EXEMPLO 2: Batch Assíncrono (com Jobs)
// ============================================

// Executa 100 requisições assíncronas com rate limit de 30 req/min
$batchId = HttpBatch::rateLimit(30, 60)
    ->async()
    ->execute($requests);

echo "Batch ID: {$batchId}\n";

// Verifica o status do batch
$status = HttpBatch::checkBatchStatus($batchId);
echo "Status: {$status['status']}\n";
echo "Completas: {$status['completed']}/{$status['total']}\n";

// Ou aguarda a conclusão (com timeout de 5 minutos)
$finalStatus = HttpBatch::waitForBatch($batchId, 300);

if ($finalStatus['status'] === 'completed') {
    foreach ($finalStatus['results'] as $result) {
        echo "Status: {$result['status']}\n";
    }
}

// ============================================
// EXEMPLO 3: Com opções globais
// ============================================

$results = HttpBatch::rateLimit(20, 60)
    ->sync()
    ->withOptions([
        'timeout' => 30,
        'withCache' => 1800, // Cache de 30 minutos
        'withoutLogging' => true,
        'guzzleOptions' => [
            'verify' => false, // Desabilita verificação SSL
        ],
    ])
    ->execute($requests);

// ============================================
// EXEMPLO 4: Com opções específicas por requisição
// ============================================

$requests = [
    [
        'method' => 'POST',
        'url' => 'https://api.example.com/auth',
        'data' => ['username' => 'user', 'password' => 'pass'],
        'headers' => ['Content-Type' => 'application/json'],
        'options' => [
            'asForm' => true,
            'timeout' => 10,
            'guzzleOptions' => [
                'verify' => '/path/to/cacert.pem',
            ],
        ],
    ],
    [
        'method' => 'GET',
        'url' => 'https://api.example.com/data',
        'options' => [
            'withCache' => 3600, // Cache de 1 hora apenas para esta requisição
        ],
    ],
];

$results = HttpBatch::rateLimit(15, 60)
    ->sync()
    ->execute($requests);

// ============================================
// EXEMPLO 5: Rate limiting personalizado
// ============================================

// Cenário 1: API muito restritiva (5 req/min)
$results = HttpBatch::rateLimit(5, 60)
    ->sync()
    ->execute($requests);

// Cenário 2: API permissiva (100 req/min)
$results = HttpBatch::rateLimit(100, 60)
    ->sync()
    ->execute($requests);

// Cenário 3: Burst curto (20 req a cada 10 segundos)
$results = HttpBatch::rateLimit(20, 10)
    ->sync()
    ->execute($requests);

// ============================================
// EXEMPLO 6: Processamento em background
// ============================================

// Dispara requisições em background e continua a execução
$batchId = HttpBatch::rateLimit(50, 60)
    ->async()
    ->withOptions(['withoutLogging' => true])
    ->execute($largeRequestList);

// Salva o batch ID para verificar depois
cache()->put("my_batch_id", $batchId, now()->addHours(2));

// Mais tarde... em outro request/comando
$batchId = cache()->get("my_batch_id");
$status = HttpBatch::checkBatchStatus($batchId);

if ($status['status'] === 'completed') {
    echo "Batch finalizado!\n";
    // Processa os resultados
}

// ============================================
// EXEMPLO 7: Scraping com rate limiting
// ============================================

$urls = [
    'https://example.com/page1',
    'https://example.com/page2',
    'https://example.com/page3',
    // ... 100 URLs
];

$requests = array_map(function($url) {
    return ['method' => 'GET', 'url' => $url];
}, $urls);

// Scraping respeitoso: 10 requisições por minuto
$results = HttpBatch::rateLimit(10, 60)
    ->sync()
    ->withOptions([
        'timeout' => 30,
        'withCache' => 7200, // Cache de 2 horas
    ])
    ->execute($requests);

// ============================================
// EXEMPLO 8: Integração com múltiplas APIs
// ============================================

$requests = [
    // API 1
    ['method' => 'GET', 'url' => 'https://api1.example.com/data'],
    // API 2
    ['method' => 'GET', 'url' => 'https://api2.example.com/info'],
    // API 3
    ['method' => 'POST', 'url' => 'https://api3.example.com/webhook', 'data' => ['event' => 'test']],
];

// Rate limit global para não sobrecarregar a rede
$results = HttpBatch::rateLimit(25, 60)
    ->sync()
    ->execute($requests);
