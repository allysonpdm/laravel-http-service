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

    /*
    |--------------------------------------------------------------------------
    | Force Protocol
    |--------------------------------------------------------------------------
    |
    | Força o uso de um protocolo específico (http ou https) em todas as URLs.
    | Quando definido, todas as URLs terão seu protocolo substituído pelo
    | valor configurado antes de executar as requisições.
    | Valores aceitos: 'http', 'https', ou null para não forçar.
    |
    */

    'force_protocol' => env('HTTP_SERVICE_FORCE_PROTOCOL', null),

    /*
    |--------------------------------------------------------------------------
    | Logging DB Connection
    |--------------------------------------------------------------------------
    |
    | Nome da conexão de banco a ser usada exclusivamente para gravação de
    | logs do HttpService. Defina para o nome de uma conexão em
    | `config/database.php` (ex: 'logging') para garantir que as escritas
    | de log não participem de transactions da conexão principal.
    |
    */
    'logging_connection' => env('HTTP_SERVICE_LOGGING_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Logging Table Name
    |--------------------------------------------------------------------------
    |
    | Nome da tabela utilizada para armazenar os logs de requisições HTTP.
    | Permite customizar o nome da tabela sem precisar alterar as migrations.
    | Padrão: 'http_request_logs'.
    |
    */
    'logging_table' => env('HTTP_SERVICE_LOGGING_TABLE', null),

    /*
    |--------------------------------------------------------------------------
    | RateLimit Table Name
    |--------------------------------------------------------------------------
    |
    | Nome da tabela utilizada para controle de rate limiting.
    | Permite customizar o nome da tabela sem precisar alterar as migrations.
    | Padrão: 'rate_limit_controls'.
    |
    */
    'ratelimit_table' => env('HTTP_SERVICE_RATELIMIT_TABLE', null),

    /*
    |--------------------------------------------------------------------------
    | RateLimit DB Connection
    |--------------------------------------------------------------------------
    |
    | Nome da conexão de banco a ser usada para operações relacionadas ao
    | controle de rate limit (tabela `rate_limit_controls`). Se definido,
    | `RateLimitControl` usará esta conexão. Se nulo, cairá para
    | `logging_connection` quando aplicável, ou para a conexão padrão.
    |
    */
    'ratelimit_connection' => env('HTTP_SERVICE_RATELIMIT_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Cache Strategy
    |--------------------------------------------------------------------------
    |
    | Define a estratégia padrão de cache para as requisições HTTP.
    | 
    | Valores aceitos:
    | - 'never': Nunca usa cache (padrão)
    | - 'always': Sempre armazena respostas em cache
    | - 'conditional': Usa cache apenas quando atingir o threshold de chamadas
    |
    */

    'cache_strategy' => env('HTTP_SERVICE_CACHE_STRATEGY', 'never'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | Tempo de vida padrão do cache em segundos.
    | Este valor será usado quando cache estiver habilitado.
    | Padrão: 3600 (1 hora)
    |
    */

    'cache_ttl' => env('HTTP_SERVICE_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Cache Threshold
    |--------------------------------------------------------------------------
    |
    | Número de chamadas necessárias para ativar o cache quando a estratégia
    | for 'conditional'. Se a mesma requisição for feita X vezes dentro do
    | período definido, o cache será ativado.
    | Usado apenas quando cache_strategy = 'conditional'.
    |
    */

    'cache_threshold' => env('HTTP_SERVICE_CACHE_THRESHOLD', null),

    /*
    |--------------------------------------------------------------------------
    | Cache Threshold Period (seconds)
    |--------------------------------------------------------------------------
    |
    | Período em segundos para contar as chamadas no cache condicional.
    | Se a mesma requisição for chamada X vezes dentro deste período,
    | o cache será ativado.
    | Usado apenas quando cache_strategy = 'conditional'.
    |
    */

    'cache_threshold_period' => env('HTTP_SERVICE_CACHE_THRESHOLD_PERIOD', null),

];
