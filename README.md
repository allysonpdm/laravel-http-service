# HTTP Service - Laravel Package

Pacote Laravel para gerenciamento avançado de requisições HTTP com logging automático e controle de rate limiting.

## Características

- **Logging automático** de todas as requisições HTTP (URL, payload, response)
- **Controle inteligente** de rate limiting (429) usando banco de dados
- **Armazenamento completo** do histórico de requisições
- **Totalmente configurável** via arquivo de config ou .env
- **Compatível** com Laravel 12
- **Gerenciamento de domínios** bloqueados com timestamps
- **Tracking de tempo** de resposta
- **Comandos Artisan** para gerenciamento
- **Controle granular** - habilite/desabilite logging e rate limit por requisição

## Instalação

### Via Composer

```bash
composer require 3rn/http-service
```

### Configuração Inicial

Execute o comando de instalação que irá publicar config e migrations:

```bash
php artisan http-service:install
```

Execute as migrations:

```bash
php artisan migrate
```

## Uso Rápido

```php
use ThreeRN\HttpService\Facades\HttpService;

// Requisição GET
$response = HttpService::get('https://api.example.com/users');
$users = $response->json();

// Requisição POST
$response = HttpService::post('https://api.example.com/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Com headers customizados
$response = HttpService::post(
    'https://api.example.com/data',
    ['key' => 'value'],
    ['Authorization' => 'Bearer token123']
);
```

## Métodos Disponíveis

### Requisições HTTP

```php
// GET com query parameters
HttpService::get($url, $query = [], $headers = []);

// POST com dados
HttpService::post($url, $data = [], $headers = []);

// PUT para atualização completa
HttpService::put($url, $data = [], $headers = []);

// PATCH para atualização parcial
HttpService::patch($url, $data = [], $headers = []);

// DELETE
HttpService::delete($url, $data = [], $headers = []);
```

### Controles Opcionais

```php
// Desabilitar logging temporariamente
HttpService::withoutLogging()->get($url);

// Habilitar logging explicitamente
HttpService::withLogging()->post($url, $data);

// Desabilitar verificação de rate limit
HttpService::withoutRateLimit()->get($url);

// Timeout customizado (em segundos)
HttpService::timeout(60)->get($url);

// Encadear múltiplas opções
HttpService::withoutLogging()
    ->withoutRateLimit()
    ->timeout(30)
    ->get($url);
```

## Cache de Requisições

O pacote oferece recursos avançados de cache para otimizar requisições repetidas.

### Cache com Expiração Dinâmica

Cache baseado em campos da resposta que contêm informações de expiração:

```php
// Cache usando campo de data/hora
// Resposta: {"token": "abc123", "expirationTime": "2025-12-17 13:02:14"}
$response = HttpService::cacheUsingExpires('expirationTime')
    ->expiresAsDatetime()  // Padrão, pode ser omitido
    ->get('https://api.example.com/auth/token');

// Cache usando campo aninhado
// Resposta: {"data": {"auth": {"expires": "2025-12-17 15:30:00"}}}
$response = HttpService::cacheUsingExpires('data.auth.expires')
    ->expiresAsDatetime()
    ->post('https://api.example.com/login', $credentials);

// Cache usando segundos
// Resposta: {"token": "xyz789", "expires_in": 3600}
$response = HttpService::cacheUsingExpires('expires_in')
    ->expiresAsSeconds()
    ->get('https://api.example.com/token');

// Cache usando minutos
// Resposta: {"session_id": "sess_123", "ttl": 30}
$response = HttpService::cacheUsingExpires('ttl')
    ->expiresAsMinutes()
    ->get('https://api.example.com/session');
```

#### Máscara de Expiração

Defina como interpretar o valor do campo de expiração:

- `expiresAsDatetime()` - Data/hora no formato Y-m-d H:i:s ou ISO 8601 (padrão)
- `expiresAsSeconds()` - Valor em segundos
- `expiresAsMinutes()` - Valor em minutos

#### TTL de Fallback

Use um TTL padrão caso o campo não seja encontrado:

```php
// Se 'expirationTime' não existir, cacheia por 2 horas (7200 segundos)
$response = HttpService::cacheUsingExpires('expirationTime', 7200)
    ->expiresAsDatetime()
    ->get('https://api.example.com/data');
```

### Cache Fixo

Cache com tempo de vida fixo:

```php
// Cache por 1 hora (3600 segundos)
$response = HttpService::withCache(3600)
    ->get('https://api.example.com/data');
```

### Cache Condicional

Cache ativado apenas após múltiplas chamadas:

```php
// Cache após 3 chamadas em 60 segundos, com TTL de 1 hora
$response = HttpService::cacheWhen(3, 60, 3600)
    ->get('https://api.example.com/data');
```

### Desabilitar Cache

```php
// Desabilitar cache para uma requisição específica
$response = HttpService::withoutCache()
    ->get('https://api.example.com/data');
```

### Limpar Cache

```php
// Limpar todo o cache de requisições
HttpService::clearCache();
```

### Cache por Status (cacheOnly / cacheExcept)

Você pode controlar o cache com base nos códigos HTTP da resposta usando os métodos `cacheOnly` e `cacheExcept`.

- **`cacheOnly(array $statuses, ?int $ttl = null): self`**
    - Armazena em cache **apenas** as respostas cujo `status_code` esteja presente em `$statuses`.
    - Se `$ttl` for passado, ele sobrescreve o TTL para essa requisição específica (em segundos).
    - Retorna uma cópia (`clone`) do `HttpService`, então a configuração afeta apenas a cadeia encadeada desta chamada.

```php
// Cachear SOMENTE respostas 200 por 10 minutos
$response = HttpService::cacheOnly([200], 600)
        ->get('https://api.example.com/data');

// Cachear respostas 200 e 201 (com TTL customizado)
$response = HttpService::cacheOnly([200, 201], 300)
        ->post('https://api.example.com/create', $payload);
```

- **`cacheExcept(array $statuses, ?int $ttl = null): self`**
    - Armazena em cache **todas** as respostas exceto aquelas cujo `status_code` esteja em `$statuses`.
    - Se `$ttl` for passado, ele sobrescreve o TTL para essa requisição específica (em segundos).
    - Retorna uma cópia (`clone`) do `HttpService`, então a configuração afeta apenas a cadeia encadeada desta chamada.

```php
// Cachear tudo exceto erro 500
$response = HttpService::cacheExcept([500])
        ->get('https://api.example.com/data');

// Cachear tudo exceto 4xx e 5xx (exemplo)
$response = HttpService::cacheExcept([400,401,403,404,500,502,503], 3600)
        ->get('https://api.example.com/data');
```

Observações de implementação:

- Os métodos `cacheOnly` e `cacheExcept` definem `cacheStrategy = 'always'`, portanto ativam o cache para aquela chamada.
- Os filtros são aplicados somente no momento de armazenamento: o pacote verifica o `status` da resposta e respeita `cacheOnlyStatuses` e `cacheExceptStatuses` antes de persistir no cache.
- `cacheOnly` limpa `cacheExceptStatuses` e vice-versa; assim, elas não entram em conflito.
- Se você preferir limpar ambos os filtros manualmente, use `clearCacheStatusFilters()`.

Revisão da implementação

Após revisar `src/Services/HttpService.php`, os métodos `cacheOnly` e `cacheExcept` já estão implementados corretamente e não precisam de alterações funcionais imediatas. Uma sugestão opcional para consistência é que `clearCacheStatusFilters()` poderia retornar um `clone` (como outros métodos que configuram comportamento) para manter o padrão imutável/encadeável, mas isso não é obrigatório.

### Formatos de Data/Hora Suportados

Para `expiresAsDatetime()`:
- `Y-m-d H:i:s` - Exemplo: "2025-12-17 13:02:14"
- ISO 8601 - Exemplo: "2025-12-17T13:02:14Z"
- Qualquer formato aceito pelo construtor DateTime do PHP

### Notação de Ponto para Campos Aninhados

- `'field'` → busca `$response['field']`
- `'data.auth.expires'` → busca `$response['data']['auth']['expires']`
- `'user.preferences.cache.ttl'` → busca `$response['user']['preferences']['cache']['ttl']`

### Exemplos Completos

Veja [examples/cache-expires-examples.php](examples/cache-expires-examples.php) para mais exemplos de uso.

## Rate Limiting

O pacote gerencia automaticamente erros 429 (Too Many Requests):

### Funcionamento Automático

1. **Antes da requisição**: Verifica se o domínio está bloqueado
2. **Durante a requisição**: Executa normalmente se não houver bloqueio
3. **Após 429**: Bloqueia o domínio automaticamente
4. **Retry-After**: Respeita o header `Retry-After` do servidor

### Tratamento de Exceções

```php
use ThreeRN\HttpService\Exceptions\RateLimitException;

try {
    $response = HttpService::get('https://api.example.com/data');
} catch (RateLimitException $e) {
    echo "Domínio bloqueado: " . $e->getDomain();
    echo "Aguarde: " . $e->getRemainingMinutes() . " minutos";
}
```

### Estratégia Wait-on-Rate-Limit

Ao invés de lançar `RateLimitException`, o serviço pode **aguardar de forma síncrona** até o bloqueio expirar e então executar a requisição normalmente.

**Ativar globalmente** via config ou `.env`:

```env
HTTP_SERVICE_RATE_LIMIT_WAIT_ON_BLOCK=true
```

**Ativar por chamada** com `waitOnRateLimit()`:

```php
// Aguarda o bloqueio expirar e então executa
$response = HttpService::waitOnRateLimit()->get('https://api.example.com/data');
```

**Desativar pontualmente** (quando estiver ligado globalmente):

```php
// Lança RateLimitException normalmente, ignorando a config global
$response = HttpService::throwOnRateLimit()->get('https://api.example.com/data');
```

> **Atenção:** O processo ficará bloqueado (via `sleep`) pelo tempo exato restante do bloqueio. Não use em requests web síncronos com bloqueios longos. Ideal para jobs/queues ou cenários onde o `default_block_time` é pequeno.

### Gerenciamento Manual

```php
use ThreeRN\HttpService\Models\RateLimitControl;

// Verificar se está bloqueado
$isBlocked = RateLimitControl::isBlocked('api.example.com');

// Tempo restante de bloqueio (em minutos)
$minutes = RateLimitControl::getRemainingBlockTime('api.example.com');

// Tempo restante de bloqueio (em segundos)
$seconds = RateLimitControl::getRemainingBlockSeconds('api.example.com');

// Bloquear manualmente por 30 minutos
RateLimitControl::blockDomain('api.example.com', 30);

// Bloquear com motivo registrado
RateLimitControl::blockDomain('api.example.com', 30);
// (defina 'reason' via create/update direto no model se necessário)

// Desbloquear manualmente
RateLimitControl::unblockDomain('api.example.com');

// Listar bloqueios ativos
$blocks = RateLimitControl::active()->get();

// Limpar bloqueios expirados
$count = RateLimitControl::cleanExpiredBlocks();
```

## Consultar Logs

### Models e Query Scopes

```php
use ThreeRN\HttpService\Models\HttpRequestLog;

// Buscar por URL
$logs = HttpRequestLog::byUrl('api.example.com')->get();

// Buscar por método HTTP
$posts = HttpRequestLog::byMethod('POST')->get();

// Buscar por status code
$errors = HttpRequestLog::byStatusCode(500)->get();

// Requisições com erro
$withErrors = HttpRequestLog::withErrors()->get();

// Requisições bem-sucedidas (2xx)
$successful = HttpRequestLog::successful()->get();

// Últimas 24 horas
$recent = HttpRequestLog::where('created_at', '>=', now()->subDay())
    ->orderBy('created_at', 'desc')
    ->get();

// Requisições lentas (mais de 5 segundos)
$slow = HttpRequestLog::where('response_time', '>', 5)->get();
```

### Estrutura do Log

Cada log contém:
- `url` - URL completa da requisição
- `method` - Método HTTP (GET, POST, etc)
- `payload` - Dados enviados (JSON)
- `response` - Resposta recebida (JSON)
- `status_code` - Código de status HTTP
- `response_time` - Tempo de resposta em segundos
- `error_message` - Mensagem de erro (se houver)
- `created_at` / `updated_at` - Timestamps

### Estrutura do Controle de Rate Limit

Cada registro de bloqueio contém:
- `domain` - Domínio bloqueado
- `blocked_at` - Momento do bloqueio
- `wait_time_minutes` - Duração do bloqueio em minutos
- `unblock_at` - Momento em que o bloqueio expira
- `reason` - Motivo do bloqueio (opcional, preenchível via `create`/`update`)
- `created_at` / `updated_at` - Timestamps

## Comandos Artisan

### Instalação
```bash
# Publicar config e migrations
php artisan http-service:install
```

### Gerenciamento de Bloqueios
```bash
# Listar domínios bloqueados
php artisan http-service:list-blocks

# Desbloquear domínio específico
php artisan http-service:unblock api.example.com

# Limpar bloqueios expirados
php artisan http-service:clean-blocks
```

### Limpeza de Logs
```bash
# Limpar logs antigos (usa log_retention_days do config)
php artisan http-service:clean-logs

# Limpar logs com período customizado
php artisan http-service:clean-logs --days=7
```

## Configuração

Arquivo `config/http-service.php`:

```php
return [
    // Habilitar logging de requisições
    'logging_enabled' => env('HTTP_SERVICE_LOGGING_ENABLED', true),
    
    // Habilitar controle de rate limiting
    'rate_limit_enabled' => env('HTTP_SERVICE_RATE_LIMIT_ENABLED', true),
    
    // Tempo de bloqueio padrão após 429 (minutos)
    'default_block_time' => env('HTTP_SERVICE_DEFAULT_BLOCK_TIME', 15),

    // Aguardar o bloqueio expirar em vez de lançar exceção (wait-on-rate-limit)
    'rate_limit_wait_on_block' => env('HTTP_SERVICE_RATE_LIMIT_WAIT_ON_BLOCK', false),
    
    // Timeout padrão de requisições (segundos)
    'timeout' => env('HTTP_SERVICE_TIMEOUT', 30),
    
    // Limpeza automática de bloqueios expirados
    'auto_clean_expired_blocks' => env('HTTP_SERVICE_AUTO_CLEAN_EXPIRED', true),
    
    // Retenção de logs (dias) - null para manter indefinidamente
    'log_retention_days' => env('HTTP_SERVICE_LOG_RETENTION_DAYS', 30),

    // Conexão de banco para gravação de logs (null = conexão padrão)
    'logging_connection' => env('HTTP_SERVICE_LOGGING_CONNECTION', null),

    // Conexão de banco para rate limiting (null = usa logging_connection ou padrão)
    'ratelimit_connection' => env('HTTP_SERVICE_RATELIMIT_CONNECTION', null),

    // Nome customizado da tabela de logs (null = 'http_request_logs')
    'logging_table' => env('HTTP_SERVICE_LOGGING_TABLE', null),

    // Nome customizado da tabela de rate limit (null = 'rate_limit_controls')
    'ratelimit_table' => env('HTTP_SERVICE_RATELIMIT_TABLE', null),
];
```

### Variáveis de Ambiente (.env)

```env
HTTP_SERVICE_LOGGING_ENABLED=true
HTTP_SERVICE_RATE_LIMIT_ENABLED=true
HTTP_SERVICE_DEFAULT_BLOCK_TIME=15
HTTP_SERVICE_RATE_LIMIT_WAIT_ON_BLOCK=false
HTTP_SERVICE_TIMEOUT=30
HTTP_SERVICE_AUTO_CLEAN_EXPIRED=true
HTTP_SERVICE_LOG_RETENTION_DAYS=30
HTTP_SERVICE_LOGGING_CONNECTION=
HTTP_SERVICE_RATELIMIT_CONNECTION=
HTTP_SERVICE_LOGGING_TABLE=
HTTP_SERVICE_RATELIMIT_TABLE=
```

### Tabelas Customizáveis

Por padrão o pacote usa `http_request_logs` e `rate_limit_controls`. Para usar nomes diferentes sem alterar as migrations:

```env
HTTP_SERVICE_LOGGING_TABLE=minha_tabela_de_logs
HTTP_SERVICE_RATELIMIT_TABLE=meu_controle_de_ratelimit
```

Os models `HttpRequestLog` e `RateLimitControl` aplicam o nome configurado via `setTable()` no construtor.

### Conexões de Banco Separadas

Para isolar logs e rate limiting da conexão principal (útil para garantir persistência mesmo com rollback de transação):

```env
HTTP_SERVICE_LOGGING_CONNECTION=logging
HTTP_SERVICE_RATELIMIT_CONNECTION=logging
```

Se `HTTP_SERVICE_RATELIMIT_CONNECTION` não estiver definido, o pacote usa `HTTP_SERVICE_LOGGING_CONNECTION` como fallback. Se nenhum estiver definido, usa a conexão padrão do Laravel.

## Exemplos de Uso

### Em Controllers

```php
namespace App\Http\Controllers;

use ThreeRN\HttpService\Facades\HttpService;
use ThreeRN\HttpService\Exceptions\RateLimitException;

class ApiController extends Controller
{
    public function fetchData()
    {
        try {
            $response = HttpService::get('https://api.example.com/data');
            
            return response()->json([
                'success' => true,
                'data' => $response->json(),
            ]);
        } catch (RateLimitException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Rate limited',
                'retry_in_minutes' => $e->getRemainingMinutes(),
            ], 429);
        }
    }
}
```

### Em Jobs/Queue

```php
namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use ThreeRN\HttpService\Facades\HttpService;
use ThreeRN\HttpService\Exceptions\RateLimitException;

class ProcessApiDataJob implements ShouldQueue
{
    public function handle()
    {
        try {
            $response = HttpService::post('https://api.example.com/process', $this->data);
            // Processar resposta...
        } catch (RateLimitException $e) {
            // Reagendar job para quando o bloqueio expirar
            $this->release($e->getRemainingMinutes() * 60);
        }
    }
}
```

### Com Retry Logic

```php
function makeApiRequestWithRetry($url, $data, $maxRetries = 3)
{
    $attempt = 0;
    
    while ($attempt < $maxRetries) {
        try {
            $response = HttpService::timeout(30)->post($url, $data);
            
            if ($response->successful()) {
                return $response->json();
            }
            
        } catch (RateLimitException $e) {
            $waitMinutes = $e->getRemainingMinutes();
            sleep($waitMinutes * 60);
        } catch (\Exception $e) {
            sleep(5); // Aguarda antes de tentar novamente
        }
        
        $attempt++;
    }
    
    throw new \Exception("Falha após {$maxRetries} tentativas");
}
```

## Manutenção e Performance

### Limpeza Automática

Configure tarefas agendadas no `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Limpar bloqueios expirados diariamente
    $schedule->command('http-service:clean-blocks')->daily();
    
    // Limpar logs antigos semanalmente
    $schedule->command('http-service:clean-logs')->weekly();
}
```

### Índices de Banco de Dados

As migrations já incluem índices otimizados para:
- Consultas por URL
- Consultas por método HTTP
- Consultas por status code
- Consultas por data
- Verificação de bloqueios de domínio

## Requisitos

- PHP 8.2 ou superior
- Laravel 12.x
- Banco de dados (MySQL, PostgreSQL, SQLite, etc)

## Estrutura do Pacote

```
http-service/
├── config/
│   └── http-service.php
├── database/
│   └── migrations/
├── src/
│   ├── Console/
│   │   └── Commands/
│   ├── Exceptions/
│   ├── Facades/
│   ├── Models/
│   ├── Services/
│   └── HttpServiceProvider.php
├── examples/
└── README.md
```

## Documentação Adicional

- [Guia de Instalação Detalhado](INSTALLATION.md)
- [Exemplos de Uso](examples/usage-examples.php)
- [Changelog](CHANGELOG.md)

## Contribuindo

Contribuições são bem-vindas! Por favor, abra uma issue ou pull request.

## Licença

MIT License - veja [LICENSE](LICENSE) para detalhes.

## Suporte

Para dúvidas ou problemas:
- Abra uma issue no GitHub
- Email: allysonmt@gmail.com

## Créditos

Desenvolvido por Allyson P. da Mata
