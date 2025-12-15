<?php

/**
 * Exemplos de uso do HTTP Service
 */

use ThreeRN\HttpService\Facades\HttpService;
use ThreeRN\HttpService\Exceptions\RateLimitException;
use ThreeRN\HttpService\Models\HttpRequestLog;
use ThreeRN\HttpService\Models\RateLimitControl;

// ========================================
// 1. REQUISIÇÕES BÁSICAS
// ========================================

// GET simples
$response = HttpService::get('https://jsonplaceholder.typicode.com/users');
$users = $response->json();

// GET com query parameters
$response = HttpService::get('https://api.example.com/search', [
    'q' => 'laravel',
    'limit' => 10,
]);

// POST com dados
$response = HttpService::post('https://api.example.com/users', [
    'name' => 'João Silva',
    'email' => 'joao@example.com',
    'age' => 30,
]);

// PUT para atualização
$response = HttpService::put('https://api.example.com/users/1', [
    'name' => 'João Silva Atualizado',
]);

// DELETE
$response = HttpService::delete('https://api.example.com/users/1');

// ========================================
// 2. REQUISIÇÕES COM HEADERS CUSTOMIZADOS
// ========================================

$response = HttpService::post(
    'https://api.example.com/data',
    ['key' => 'value'],
    [
        'Authorization' => 'Bearer token123',
        'X-Custom-Header' => 'custom-value',
    ]
);

// ========================================
// 3. CONTROLE DE LOGGING
// ========================================

// Desabilitar logging para uma requisição específica
$response = HttpService::withoutLogging()
    ->get('https://api.example.com/public-data');

// Habilitar logging explicitamente
$response = HttpService::withLogging()
    ->post('https://api.example.com/important-data', $data);

// ========================================
// 4. CONTROLE DE RATE LIMITING
// ========================================

// Desabilitar verificação de rate limit temporariamente
$response = HttpService::withoutRateLimit()
    ->get('https://api.example.com/data');

// Tratar exceção de rate limit
try {
    $response = HttpService::get('https://api.example.com/data');
} catch (RateLimitException $e) {
    echo "Domínio bloqueado: " . $e->getDomain();
    echo "Aguarde " . $e->getRemainingMinutes() . " minutos";
    
    // Você pode optar por aguardar ou usar outro endpoint
}

// ========================================
// 5. TIMEOUT CUSTOMIZADO
// ========================================

// Definir timeout de 60 segundos para requisição lenta
$response = HttpService::timeout(60)
    ->get('https://api-lenta.example.com/big-data');

// ========================================
// 6. CONSULTAR LOGS
// ========================================

// Buscar todas as requisições para uma URL
$logs = HttpRequestLog::byUrl('api.example.com')->get();

// Buscar requisições com erro
$errorLogs = HttpRequestLog::withErrors()->get();

// Buscar requisições bem-sucedidas
$successLogs = HttpRequestLog::successful()->get();

// Buscar por método
$postLogs = HttpRequestLog::byMethod('POST')->get();

// Buscar por status code
$notFoundLogs = HttpRequestLog::byStatusCode(404)->get();

// Logs das últimas 24 horas
$recentLogs = HttpRequestLog::where('created_at', '>=', now()->subDay())->get();

// ========================================
// 7. GERENCIAR RATE LIMITS
// ========================================

// Verificar se domínio está bloqueado
$isBlocked = RateLimitControl::isBlocked('api.example.com');

// Obter tempo restante de bloqueio
$remainingMinutes = RateLimitControl::getRemainingBlockTime('api.example.com');

// Bloquear domínio manualmente por 30 minutos
RateLimitControl::blockDomain('api.example.com', 30);

// Desbloquear domínio manualmente
RateLimitControl::unblockDomain('api.example.com');

// Limpar bloqueios expirados
$removedCount = RateLimitControl::cleanExpiredBlocks();

// Listar todos os bloqueios ativos
$activeBlocks = RateLimitControl::active()->get();

// ========================================
// 8. EXEMPLO COMPLETO: API COM RETRY
// ========================================

function makeApiRequest($url, $data, $maxRetries = 3)
{
    $attempt = 0;
    
    while ($attempt < $maxRetries) {
        try {
            $response = HttpService::timeout(30)
                ->post($url, $data);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            $attempt++;
            
        } catch (RateLimitException $e) {
            // Se rate limited, aguarda e tenta novamente
            $waitMinutes = $e->getRemainingMinutes();
            echo "Rate limited. Aguardando {$waitMinutes} minutos...\n";
            sleep($waitMinutes * 60);
            $attempt++;
            
        } catch (\Exception $e) {
            echo "Erro: " . $e->getMessage() . "\n";
            $attempt++;
            sleep(5); // Aguarda 5 segundos antes de tentar novamente
        }
    }
    
    throw new \Exception("Falha após {$maxRetries} tentativas");
}

// ========================================
// 9. USAR EM CONTROLLERS
// ========================================

namespace App\Http\Controllers;

use ThreeRN\HttpService\Facades\HttpService;

class ExternalApiController extends Controller
{
    public function fetchUsers()
    {
        try {
            $response = HttpService::get('https://api.example.com/users');
            
            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'API request failed',
            ], $response->status());
            
        } catch (RateLimitException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Rate limited. Try again in ' . $e->getRemainingMinutes() . ' minutes',
            ], 429);
        }
    }
}

// ========================================
// 10. USAR EM JOBS/QUEUE
// ========================================

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ThreeRN\HttpService\Facades\HttpService;
use ThreeRN\HttpService\Exceptions\RateLimitException;

class ProcessExternalApiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle()
    {
        try {
            $response = HttpService::post('https://api.example.com/process', $this->data);
            
            // Processar resposta...
            
        } catch (RateLimitException $e) {
            // Re-adiciona o job na fila para tentar novamente depois
            $this->release($e->getRemainingMinutes() * 60);
        }
    }
}
