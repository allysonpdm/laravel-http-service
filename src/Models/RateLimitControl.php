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
