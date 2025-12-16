# Sistema de Batch de Requisições HTTP com Rate Limiting

Sistema inteligente para executar múltiplas requisições HTTP com controle de taxa (rate limiting), suportando execução síncrona e assíncrona usando Jobs do Laravel.

## Características

- **Rate Limiting Inteligente**: Controle preciso de quantas requisições por intervalo de tempo
- **Síncrono ou Assíncrono**: Escolha entre execução imediata ou em background
- **Jobs do Laravel**: Utiliza o sistema de filas do Laravel para processamento assíncrono
- **Opções Globais e Específicas**: Configure opções para todo o batch ou por requisição
- **Rastreamento de Status**: Monitore o progresso de batches assíncronos
- **Throttling Automático**: Distribui requisições automaticamente no tempo

## Instalação

O sistema de batch já vem incluído no pacote HttpService. Apenas certifique-se de que o Laravel Queue está configurado:

```bash
php artisan queue:table
php artisan migrate
```

## Uso Básico

### Batch Síncrono (Sequencial)

```php
use ThreeRN\HttpService\Facades\HttpBatch;

$requests = [
    ['method' => 'GET', 'url' => 'https://api.example.com/users/1'],
    ['method' => 'GET', 'url' => 'https://api.example.com/users/2'],
    ['method' => 'POST', 'url' => 'https://api.example.com/posts', 'data' => ['title' => 'Test']],
];

// Executa 10 requisições por minuto
$results = HttpBatch::rateLimit(10, 60)
    ->sync()
    ->execute($requests);

foreach ($results as $result) {
    if ($result['success']) {
        echo "Status: {$result['status']}\n";
        echo "Body: {$result['body']}\n";
        echo "JSON: " . json_encode($result['json']) . "\n";
    } else {
        echo "Erro: {$result['error']}\n";
    }
}
```

### Batch Assíncrono (Background)

```php
// Dispara as requisições em background
$batchId = HttpBatch::rateLimit(30, 60)
    ->async()
    ->execute($requests);

// Verifica o status
$status = HttpBatch::checkBatchStatus($batchId);
echo "Completas: {$status['completed']}/{$status['total']}\n";

// Ou aguarda a conclusão
$finalStatus = HttpBatch::waitForBatch($batchId, 300); // timeout de 5 minutos

if ($finalStatus['status'] === 'completed') {
    foreach ($finalStatus['results'] as $result) {
        // Processa resultados
    }
}
```

## Rate Limiting

### Configuração Básica

```php
// 10 requisições por 60 segundos (10 req/min)
HttpBatch::rateLimit(10, 60)

// 100 requisições por 60 segundos (100 req/min)
HttpBatch::rateLimit(100, 60)

// 20 requisições a cada 10 segundos (burst rápido)
HttpBatch::rateLimit(20, 10)
```

### Como Funciona

- **Síncrono**: Adiciona um delay calculado entre cada requisição (`intervalSeconds / requestsPerInterval`)
- **Assíncrono**: Distribui os jobs no tempo usando `delay()` do Laravel

Exemplo: `rateLimit(10, 60)` = 1 requisição a cada 6 segundos

## Opções Globais

Configure opções que se aplicam a todas as requisições do batch:

```php
HttpBatch::rateLimit(20, 60)
    ->sync()
    ->withOptions([
        'timeout' => 30,              // Timeout em segundos
        'withCache' => 1800,          // Cache de 30 minutos
        'withoutLogging' => true,     // Não registrar no banco
        'withoutRateLimit' => true,   // Desabilita rate limit do domínio
        'asForm' => true,             // Envia como form data
        'forceHttps' => true,         // Força HTTPS
        'guzzleOptions' => [          // Opções do Guzzle
            'verify' => false,
        ],
    ])
    ->execute($requests);
```

## Opções Específicas por Requisição

Cada requisição pode ter suas próprias opções:

```php
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
            'withCache' => 3600, // Cache apenas para esta requisição
        ],
    ],
];

$results = HttpBatch::rateLimit(15, 60)->sync()->execute($requests);
```

## Formato das Requisições

```php
[
    'method' => 'GET|POST|PUT|PATCH|DELETE',
    'url' => 'https://...',
    'data' => [],           // Opcional: dados da requisição
    'headers' => [],        // Opcional: headers customizados
    'options' => [],        // Opcional: opções específicas
]
```

## Formato das Respostas (Síncrono)

```php
[
    'success' => true,
    'status' => 200,
    'body' => '...',
    'json' => [...],        // null se não for JSON
    'headers' => [...],
    'request' => [...],     // A requisição original
]

// Ou em caso de erro:
[
    'success' => false,
    'error' => 'mensagem de erro',
    'request' => [...],
]
```

## Status de Batch Assíncrono

```php
[
    'status' => 'processing|completed|not_found|timeout',
    'total' => 10,
    'completed' => 7,
    'pending' => 3,
    'started_at' => '2025-12-16 10:30:00',
    'results' => [
        [
            'status' => 200,
            'body' => '...',
            'error' => null,
            'completed_at' => '2025-12-16 10:30:15',
        ],
        // ...
    ],
]
```

## Casos de Uso

### 1. Web Scraping Respeitoso

```php
$urls = ['https://site.com/page1', 'https://site.com/page2', ...];

$requests = array_map(fn($url) => ['method' => 'GET', 'url' => $url], $urls);

$results = HttpBatch::rateLimit(10, 60) // 10 páginas por minuto
    ->sync()
    ->withOptions(['withCache' => 7200]) // Cache de 2 horas
    ->execute($requests);
```

### 2. Sincronização com API Externa

```php
$users = User::all();

$requests = $users->map(fn($user) => [
    'method' => 'POST',
    'url' => 'https://external-api.com/sync',
    'data' => ['user_id' => $user->id, 'email' => $user->email],
])->toArray();

$batchId = HttpBatch::rateLimit(50, 60)
    ->async()
    ->execute($requests);
```

### 3. Múltiplas APIs com Rate Limit Global

```php
$requests = [
    ['method' => 'GET', 'url' => 'https://api1.com/data'],
    ['method' => 'GET', 'url' => 'https://api2.com/info'],
    ['method' => 'POST', 'url' => 'https://api3.com/webhook', 'data' => [...]],
];

$results = HttpBatch::rateLimit(25, 60)->sync()->execute($requests);
```

## Performance

### Síncrono vs Assíncrono

- **Síncrono**: Bloqueia a execução até todas requisições terminarem
  - Use para: poucos requests, resultados imediatos necessários
  - Performance: ~10-50 requisições

- **Assíncrono**: Retorna imediatamente, processa em background
  - Use para: muitos requests, pode aguardar processamento
  - Performance: 100+ requisições

### Otimização

```php
// Para APIs lentas: menor taxa
HttpBatch::rateLimit(5, 60)

// Para APIs rápidas: maior taxa
HttpBatch::rateLimit(100, 60)

// Para burst control: intervalos curtos
HttpBatch::rateLimit(20, 10)
```

## Configuração do Worker

Para processamento assíncrono, certifique-se de ter um worker rodando:

```bash
php artisan queue:work
```

Ou usando Supervisor (recomendado para produção):

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=8
```

## Notas Importantes

1. **Cache de Resultados**: Batches assíncronos armazenam resultados por 1 hora
2. **Timeout Padrão**: `waitForBatch()` tem timeout de 5 minutos por padrão
3. **Memory**: Batches síncronos grandes podem consumir muita memória
4. **Rate Limiting**: É independente do rate limiting de domínio do HttpService

## Troubleshooting

**Jobs não executando?**
```bash
php artisan queue:work
php artisan queue:listen
```

**Resultados não encontrados?**
- Verifique se o cache está configurado
- Batch IDs expiram após 1 hora

**Performance lenta?**
- Aumente `requestsPerInterval` se a API permitir
- Use `async()` para grandes volumes
- Configure mais workers
