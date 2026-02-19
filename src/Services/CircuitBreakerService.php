<?php

namespace ThreeRN\HttpService\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Implementa o padrão Circuit Breaker para requisições HTTP.
 *
 * Estados:
 *  - CLOSED    : operação normal; falhas são contadas.
 *  - OPEN      : circuito aberto; requisições são rejeitadas imediatamente.
 *  - HALF-OPEN : após o tempo de recuperação, uma requisição de sondagem
 *                é permitida. Se bem-sucedida, o circuito fecha; se falhar,
 *                volta a OPEN.
 *
 * O estado é armazenado no cache do Laravel (sem necessidade de banco).
 */
class CircuitBreakerService
{
    public const STATE_CLOSED = 'closed';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    /** Número de falhas consecutivas para abrir o circuito */
    protected int $failureThreshold;

    /** Tempo em segundos no estado OPEN antes de tentar HALF-OPEN */
    protected int $recoveryTime;

    /** HTTP status codes que contam como falha */
    protected array $failureStatuses;

    /** TTL do cache de estado (deve ser maior que o maior recoveryTime esperado) */
    protected int $stateCacheTtl = 86400; // 24h

    /**
     * Namespace das chaves de cache.
     *
     * Quando nulo, as chaves ficam isoladas por instância da aplicação
     * (comportamento padrão). Quando definido, múltiplos projetos que usam
     * o mesmo cache driver e o mesmo namespace compartilham o estado do
     * circuit breaker — útil para evitar que o App A repita erros que o
     * App B já detectou.
     */
    protected ?string $namespace;

    public function __construct(
        int $failureThreshold,
        int $recoveryTime,
        array $failureStatuses,
        ?string $namespace = null
    ) {
        $this->failureThreshold = $failureThreshold;
        $this->recoveryTime = $recoveryTime;
        $this->failureStatuses = $failureStatuses;
        $this->namespace = $namespace;
    }

    // -------------------------------------------------------------------------
    // API pública
    // -------------------------------------------------------------------------

    /**
     * Verifica se uma requisição para o domínio é permitida.
     * Deve ser chamado ANTES de executar a requisição.
     *
     * Lança CircuitBreakerException se o circuito estiver OPEN.
     * Retorna silenciosamente se CLOSED ou HALF-OPEN.
     */
    public function guardRequest(string $domain): void
    {
        $state = $this->resolveState($domain);

        if ($state === self::STATE_OPEN) {
            $data = $this->read($domain);
            $remaining = max(0, $this->recoveryTime - (time() - ($data['opened_at'] ?? 0)));
            throw new \ThreeRN\HttpService\Exceptions\CircuitBreakerException($domain, $remaining);
        }

        // HALF_OPEN: usa Cache::add para garantir que apenas uma sondagem
        // aconteça de cada vez (atômico na maioria dos drivers).
        if ($state === self::STATE_HALF_OPEN) {
            $probeKey = $this->probeKey($domain);
            $acquired = Cache::add($probeKey, true, $this->timeout());
            if (!$acquired) {
                // Outra sondagem já está em andamento; bloqueia esta requisição.
                $data = $this->read($domain);
                throw new \ThreeRN\HttpService\Exceptions\CircuitBreakerException($domain, 0);
            }
        }
    }

    /**
     * Registra que a requisição foi bem-sucedida.
     * Deve ser chamado APÓS receber a resposta sem falha.
     */
    public function recordSuccess(string $domain): void
    {
        $data = $this->read($domain);

        if ($data['state'] === self::STATE_HALF_OPEN) {
            // Sondagem bem-sucedida → fecha o circuito
            $this->reset($domain);
        } elseif ($data['state'] === self::STATE_CLOSED) {
            // Reseta contagem de falhas ao ter sucesso
            if (($data['failures'] ?? 0) > 0) {
                $data['failures'] = 0;
                $this->write($domain, $data);
            }
        }

        Cache::forget($this->probeKey($domain));
    }

    /**
     * Registra que a requisição falhou (exceção ou status de falha).
     * Deve ser chamado quanto a resposta for considerada uma falha.
     */
    public function recordFailure(string $domain): void
    {
        $data = $this->read($domain);

        if ($data['state'] === self::STATE_HALF_OPEN) {
            // Sondagem falhou → reabre o circuito
            Cache::forget($this->probeKey($domain));
            $this->trip($domain);
            return;
        }

        if ($data['state'] === self::STATE_CLOSED) {
            $failures = ($data['failures'] ?? 0) + 1;

            if ($failures >= $this->failureThreshold) {
                $this->trip($domain);
            } else {
                $data['failures'] = $failures;
                $this->write($domain, $data);
            }
        }
    }

    /**
     * Verifica se um código de status HTTP deve ser tratado como falha.
     */
    public function isFailureStatus(int $status): bool
    {
        return in_array($status, $this->failureStatuses, true);
    }

    /**
     * Retorna o estado atual (já resolvendo a transição OPEN → HALF_OPEN).
     */
    public function getState(string $domain): string
    {
        return $this->resolveState($domain);
    }

    /**
     * Retorna os dados completos de estado do circuito para um domínio.
     */
    public function getStatus(string $domain): array
    {
        $data = $this->read($domain);
        $data['state'] = $this->resolveState($domain);
        return $data;
    }

    /**
     * Reseta manualmente o circuito para CLOSED.
     */
    public function reset(string $domain): void
    {
        $this->write($domain, [
            'state'     => self::STATE_CLOSED,
            'failures'  => 0,
            'opened_at' => null,
        ]);
        Cache::forget($this->probeKey($domain));
    }

    // -------------------------------------------------------------------------
    // Internos
    // -------------------------------------------------------------------------

    /**
     * Resolve o estado atual, aplicando a transição automática OPEN → HALF_OPEN
     * quando o tempo de recuperação já passou.
     */
    protected function resolveState(string $domain): string
    {
        $data = $this->read($domain);

        if ($data['state'] === self::STATE_OPEN) {
            $elapsed = time() - ($data['opened_at'] ?? 0);
            if ($elapsed >= $this->recoveryTime) {
                $data['state'] = self::STATE_HALF_OPEN;
                $this->write($domain, $data);
                return self::STATE_HALF_OPEN;
            }
        }

        return $data['state'];
    }

    /**
     * Abre o circuito (transição para OPEN).
     */
    protected function trip(string $domain): void
    {
        $this->write($domain, [
            'state'     => self::STATE_OPEN,
            'failures'  => $this->failureThreshold,
            'opened_at' => time(),
        ]);
    }

    protected function read(string $domain): array
    {
        return Cache::get($this->stateKey($domain), [
            'state'     => self::STATE_CLOSED,
            'failures'  => 0,
            'opened_at' => null,
        ]);
    }

    protected function write(string $domain, array $data): void
    {
        Cache::put($this->stateKey($domain), $data, $this->stateCacheTtl);
    }

    protected function stateKey(string $domain): string
    {
        $prefix = $this->namespace ? 'http_cb_' . $this->namespace . '_' : 'http_cb_';
        return $prefix . md5($domain);
    }

    protected function probeKey(string $domain): string
    {
        $prefix = $this->namespace ? 'http_cb_probe_' . $this->namespace . '_' : 'http_cb_probe_';
        return $prefix . md5($domain);
    }

    /**
     * TTL da lock de sondagem: tempo suficiente para a requisição completar.
     * Usa um valor fixo razoável (30s); o HttpService normalmente tem timeout menor.
     */
    protected function timeout(): int
    {
        return 30;
    }
}
