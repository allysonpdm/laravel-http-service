# Guia de Instalação e Uso

## Instalação

### 1. Instalar via Composer

```bash
composer require allysonpdm/laravel-http-service
```

### 2. Executar o Comando de Instalação

```bash
php artisan http-service:install
```

Este comando irá:
- Criar o arquivo `config/http-service.php`
- Publicar as migrations para `database/migrations/`

### 3. Executar as Migrations

```bash
php artisan migrate
```

Isso criará duas tabelas:
- `http_request_logs` - Armazena logs de todas as requisições HTTP
- `rate_limit_controls` - Gerencia bloqueios de domínios por rate limiting

## Configuração

Edite o arquivo `config/http-service.php`:

```php
return [
    // Habilitar logging de requisições
    'logging_enabled' => true,
    
    // Tempo de bloqueio padrão em minutos após receber 429
    'default_block_time' => 15,
    
    // Habilitar controle automático de rate limiting
    'rate_limit_enabled' => true,
    
    // Timeout de requisições em segundos
    'timeout' => 30,
];
```

Ou use variáveis de ambiente no `.env`:

```env
HTTP_SERVICE_LOGGING_ENABLED=true
HTTP_SERVICE_RATE_LIMIT_ENABLED=true
HTTP_SERVICE_DEFAULT_BLOCK_TIME=15
HTTP_SERVICE_TIMEOUT=30
```

## Uso Básico

### Importar a Facade

```php
use ThreeRN\HttpService\Facades\HttpService;
```

### Fazer Requisições

```php
// GET
$response = HttpService::get('https://api.example.com/users');

// POST
$response = HttpService::post('https://api.example.com/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// PUT
$response = HttpService::put('https://api.example.com/users/1', $data);

// PATCH
$response = HttpService::patch('https://api.example.com/users/1', $data);

// DELETE
$response = HttpService::delete('https://api.example.com/users/1');
```

### Trabalhar com Respostas

```php
$response = HttpService::get('https://api.example.com/users');

// Verificar sucesso
if ($response->successful()) {
    $data = $response->json();
}

// Obter status code
$status = $response->status();

// Obter headers
$contentType = $response->header('Content-Type');
```

## Recursos Avançados

### Controlar Logging

```php
// Desabilitar logging para uma requisição
$response = HttpService::withoutLogging()
    ->get('https://api.example.com/data');

// Habilitar logging explicitamente
$response = HttpService::withLogging()
    ->post('https://api.example.com/data', $data);
```

### Timeout Customizado

```php
// Definir timeout de 60 segundos
$response = HttpService::timeout(60)
    ->get('https://api-lenta.example.com/data');
```

### Headers Customizados

```php
$response = HttpService::post(
    'https://api.example.com/data',
    ['key' => 'value'],
    [
        'Authorization' => 'Bearer your-token',
        'X-Custom-Header' => 'value'
    ]
);
```

### Tratar Rate Limiting

```php
use ThreeRN\HttpService\Exceptions\RateLimitException;

try {
    $response = HttpService::get('https://api.example.com/data');
} catch (RateLimitException $e) {
    echo "Domínio bloqueado: " . $e->getDomain();
    echo "Aguarde: " . $e->getRemainingMinutes() . " minutos";
}
```

## Comandos Artisan

### Listar Domínios Bloqueados

```bash
php artisan http-service:list-blocks
```

### Desbloquear Domínio Manualmente

```bash
php artisan http-service:unblock api.example.com
```

### Limpar Bloqueios Expirados

```bash
php artisan http-service:clean-blocks
```

## Consultar Logs

### Usando Models

```php
use ThreeRN\HttpService\Models\HttpRequestLog;

// Todas as requisições
$logs = HttpRequestLog::all();

// Requisições com erro
$errors = HttpRequestLog::withErrors()->get();

// Requisições bem-sucedidas
$success = HttpRequestLog::successful()->get();

// Por URL
$logs = HttpRequestLog::byUrl('api.example.com')->get();

// Por método
$posts = HttpRequestLog::byMethod('POST')->get();

// Por status code
$notFound = HttpRequestLog::byStatusCode(404)->get();

// Últimas 24 horas
$recent = HttpRequestLog::where('created_at', '>=', now()->subDay())->get();
```

## Gerenciar Rate Limits Manualmente

```php
use ThreeRN\HttpService\Models\RateLimitControl;

// Verificar se domínio está bloqueado
$isBlocked = RateLimitControl::isBlocked('api.example.com');

// Obter tempo restante
$minutes = RateLimitControl::getRemainingBlockTime('api.example.com');

// Bloquear domínio por 30 minutos
RateLimitControl::blockDomain('api.example.com', 30);

// Desbloquear domínio
RateLimitControl::unblockDomain('api.example.com');

// Bloqueios ativos
$blocks = RateLimitControl::active()->get();
```

## Integração com Jobs/Queue

```php
namespace App\Jobs;

use ThreeRN\HttpService\Facades\HttpService;
use ThreeRN\HttpService\Exceptions\RateLimitException;

class ProcessApiJob implements ShouldQueue
{
    public function handle()
    {
        try {
            $response = HttpService::post('https://api.example.com/data', $this->data);
            // Processar...
        } catch (RateLimitException $e) {
            // Reagendar job
            $this->release($e->getRemainingMinutes() * 60);
        }
    }
}
```

## Manutenção

### Limpeza Automática

Configure no `config/http-service.php`:

```php
'auto_clean_expired_blocks' => true,
'log_retention_days' => 30,
```

### Limpeza Manual

```bash
# Limpar bloqueios expirados
php artisan http-service:clean-blocks

# Limpar logs antigos (você pode criar um comando personalizado)
php artisan tinker
>>> HttpRequestLog::where('created_at', '<', now()->subDays(30))->delete();
```

## Troubleshooting

### Logs não estão sendo salvos

- Verifique se `logging_enabled` está `true` no config
- Verifique se as migrations foram executadas
- Verifique as permissões do banco de dados

### Rate limiting não está funcionando

- Verifique se `rate_limit_enabled` está `true` no config
- Verifique se a migration `rate_limit_controls` foi executada
- Use `php artisan http-service:list-blocks` para ver bloqueios ativos

### Erro ao instalar

- Certifique-se de ter Laravel 12+ instalado
- Execute `composer dump-autoload`
- Limpe o cache: `php artisan config:clear`
