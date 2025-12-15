<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Logging Enabled
    |--------------------------------------------------------------------------
    |
    | Determina se as requisições HTTP devem ser registradas no banco de dados.
    | Quando habilitado, todas as requisições (URL, payload, response) serão
    | armazenadas na tabela http_request_logs.
    |
    */

    'logging_enabled' => env('HTTP_SERVICE_LOGGING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Rate Limit Control Enabled
    |--------------------------------------------------------------------------
    |
    | Habilita o controle automático de rate limiting. Quando habilitado,
    | o serviço verifica se um domínio está bloqueado antes de fazer requisições
    | e bloqueia automaticamente domínios que retornam erro 429.
    |
    */

    'rate_limit_enabled' => env('HTTP_SERVICE_RATE_LIMIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Block Time (minutes)
    |--------------------------------------------------------------------------
    |
    | Tempo padrão em minutos para bloquear um domínio após receber um erro 429.
    | Este valor será usado se o servidor não fornecer um header Retry-After.
    |
    */

    'default_block_time' => env('HTTP_SERVICE_DEFAULT_BLOCK_TIME', 15),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Tempo máximo em segundos para aguardar uma resposta HTTP antes de
    | considerar timeout. Padrão: 30 segundos.
    |
    */

    'timeout' => env('HTTP_SERVICE_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Auto Clean Expired Blocks
    |--------------------------------------------------------------------------
    |
    | Habilita a limpeza automática de bloqueios expirados. Quando habilitado,
    | bloqueios antigos serão removidos automaticamente do banco de dados.
    |
    */

    'auto_clean_expired_blocks' => env('HTTP_SERVICE_AUTO_CLEAN_EXPIRED', true),

    /*
    |--------------------------------------------------------------------------
    | Log Retention Days
    |--------------------------------------------------------------------------
    |
    | Número de dias para manter logs de requisições no banco de dados.
    | Logs mais antigos que este período podem ser removidos automaticamente.
    | Defina como null para manter logs indefinidamente.
    |
    */

    'log_retention_days' => env('HTTP_SERVICE_LOG_RETENTION_DAYS', 30),

];
