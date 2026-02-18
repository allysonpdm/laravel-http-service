<?php

namespace ThreeRN\HttpService\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RateLimitControl extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'rate_limit_controls';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'domain',
        'blocked_at',
        'wait_time_minutes',
        'unblock_at',
        'reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'blocked_at' => 'datetime',
        'unblock_at' => 'datetime',
        'wait_time_minutes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Ajusta a conexão do model para a conexão de logging, quando
     * configurada. Isso garante que operações como `create`, `delete`
     * e `where` usem a conexão separada definida em config/http-service.php.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $table = config('http-service.ratelimit_table');
        if (!empty($table)) {
            $this->setTable($table);
        }

        // Bug fix: config() só usa o segundo argumento como default quando a
        // chave não existe no array de config — não quando o valor é null.
        // O operador ?? garante o fallback correto para ambos os casos.
        $conn = config('http-service.ratelimit_connection')
            ?? config('http-service.logging_connection');
        if (!empty($conn)) {
            $this->setConnection($conn);
        }
    }

    /**
     * Verifica se o domínio está bloqueado
     */
    public static function isBlocked(string $domain): bool
    {
        $control = static::where('domain', $domain)
            ->where('unblock_at', '>', now())
            ->first();

        return $control !== null;
    }

    /**
     * Obtém o tempo restante de bloqueio em minutos
     */
    public static function getRemainingBlockTime(string $domain): ?int
    {
        $control = static::where('domain', $domain)
            ->where('unblock_at', '>', now())
            ->first();

        if (!$control) {
            return null;
        }

        return (int) now()->diffInMinutes($control->unblock_at);
    }

    /**
     * Obtém o tempo restante de bloqueio em segundos.
     * Usado pela estratégia waitOnRateLimit para sleep síncrono preciso.
     */
    public static function getRemainingBlockSeconds(string $domain): ?int
    {
        $control = static::where('domain', $domain)
            ->where('unblock_at', '>', now())
            ->first();

        if (!$control) {
            return null;
        }

        return (int) now()->diffInSeconds($control->unblock_at);
    }

    /**
     * Bloqueia um domínio por um determinado tempo
     */
    public static function blockDomain(string $domain, int $waitTimeMinutes): self
    {
        // Remove bloqueios antigos do mesmo domínio
        static::where('domain', $domain)->delete();

        return static::create([
            'domain' => $domain,
            'blocked_at' => now(),
            'wait_time_minutes' => $waitTimeMinutes,
            'unblock_at' => now()->addMinutes($waitTimeMinutes),
        ]);
    }

    /**
     * Desbloqueia um domínio manualmente
     */
    public static function unblockDomain(string $domain): bool
    {
        return static::where('domain', $domain)->delete() > 0;
    }

    /**
     * Limpa bloqueios expirados
     */
    public static function cleanExpiredBlocks(): int
    {
        return static::where('unblock_at', '<=', now())->delete();
    }

    /**
     * Scope para bloqueios ativos
     */
    public function scopeActive($query)
    {
        return $query->where('unblock_at', '>', now());
    }

    /**
     * Scope para bloqueios expirados
     */
    public function scopeExpired($query)
    {
        return $query->where('unblock_at', '<=', now());
    }

    /**
     * Scope por domínio
     */
    public function scopeByDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }
}
