<?php

namespace ThreeRN\HttpService\Models;

use Illuminate\Database\Eloquent\Model;

class HttpRequestLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'http_request_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'url',
        'method',
        'headers',
        'payload',
        'response',
        'status_code',
        'response_time',
        'error_message',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'response' => 'array',
        'status_code' => 'integer',
        'response_time' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Ajusta a conexão do model para a conexão de logging, quando
     * configurada. Isso garante que chamadas como `HttpRequestLog::create`
     * usem a conexão separada definida em config/http-service.php.
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $table = config('http-service.logging_table');
        if (!empty($table)) {
            $this->setTable($table);
        }

        $conn = config('http-service.logging_connection');
        if (!empty($conn)) {
            $this->setConnection($conn);
        }
    }

    /**
     * Scope para filtrar por URL
     */
    public function scopeByUrl($query, string $url)
    {
        return $query->where('url', 'like', "%{$url}%");
    }

    /**
     * Scope para filtrar por método HTTP
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('method', strtoupper($method));
    }

    /**
     * Scope para filtrar por status code
     */
    public function scopeByStatusCode($query, int $statusCode)
    {
        return $query->where('status_code', $statusCode);
    }

    /**
     * Scope para requisições com erro
     */
    public function scopeWithErrors($query)
    {
        return $query->whereNotNull('error_message');
    }

    /**
     * Scope para requisições bem-sucedidas
     */
    public function scopeSuccessful($query)
    {
        return $query->whereBetween('status_code', [200, 299]);
    }
}
