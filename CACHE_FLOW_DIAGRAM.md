# Diagrama de Fluxo - Cache com Expiração Dinâmica

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    REQUISIÇÃO COM CACHE DINÂMICO                        │
└─────────────────────────────────────────────────────────────────────────┘

1. CONFIGURAÇÃO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   
   HttpService::cacheUsingExpires('expirationTime', 3600)
       ->expiresAsDatetime()
       ->get('https://api.example.com/token')
   
   ↓ Define
   
   • cacheStrategy = 'always'
   • cacheExpiresField = 'expirationTime'
   • cacheExpiresMask = 'datetime'
   • cacheTtl = 3600 (fallback)


2. VERIFICAÇÃO DE CACHE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   
   generateCacheKey($method, $url, $payload)
   ↓
   'http_service_abc123...'
   ↓
   shouldUseCache($cacheKey) ?
   ├─ YES → getCachedResponse($cacheKey)
   │         ├─ Existe? → RETORNA CACHE ✓
   │         └─ Não existe? → Continua...
   └─ NO → Continua...


3. EXECUÇÃO DA REQUISIÇÃO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   
   Http::get('https://api.example.com/token')
   ↓
   Response:
   {
       "token": "10844259|abc...",
       "expirationTime": "2025-12-17 13:02:14"
   }


4. CÁLCULO DO TTL DINÂMICO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   
   calculateTtlFromResponse($response)
   ↓
   getNestedValue($data, 'expirationTime')
   ↓
   "2025-12-17 13:02:14"
   ↓
   calculateSecondsUntil("2025-12-17 13:02:14")
   ↓
   Exemplo: 7320 segundos (2h 2min)


5. ARMAZENAMENTO EM CACHE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   
   Cache::put('http_service_abc123...', [
       'status' => 200,
       'headers' => [...],
       'body' => '{"token":"...","expirationTime":"..."}'
   ], 7320)  ← TTL calculado
   
   ↓
   Cache válido até 2025-12-17 13:02:14


6. PRÓXIMAS REQUISIÇÕES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   
   HttpService::cacheUsingExpires('expirationTime')
       ->get('https://api.example.com/token')
   ↓
   Mesmo cacheKey → Cache HIT ✓
   ↓
   Retorna Response do cache (sem fazer requisição HTTP)
   ↓
   Performance: ~1ms vs ~200-500ms
```

---

## Comparação: Máscaras de Tempo

```
┌──────────────────┬──────────────────────────┬─────────────────────────┐
│     MÁSCARA      │      VALOR NA API        │      TTL CALCULADO      │
├──────────────────┼──────────────────────────┼─────────────────────────┤
│  expiresAs       │  "2025-12-17 13:02:14"   │  calculateSecondsUntil  │
│  Datetime()      │  ou "2025-12-17T13:02Z"  │  → 7320 segundos        │
├──────────────────┼──────────────────────────┼─────────────────────────┤
│  expiresAs       │  3600                    │  (int) 3600             │
│  Seconds()       │                          │  → 3600 segundos        │
├──────────────────┼──────────────────────────┼─────────────────────────┤
│  expiresAs       │  60                      │  60 * 60                │
│  Minutes()       │                          │  → 3600 segundos        │
└──────────────────┴──────────────────────────┴─────────────────────────┘
```

---

## Notação de Ponto - Extração de Valores

```
RESPOSTA JSON:
{
    "data": {
        "auth": {
            "token": "abc123",
            "expires": "2025-12-17 15:00:00",
            "user": {
                "id": 1,
                "preferences": {
                    "cache": {
                        "ttl": 3600
                    }
                }
            }
        }
    }
}

EXTRAÇÃO:
┌─────────────────────────────────────┬──────────────────────────┐
│         CAMPO SOLICITADO            │      VALOR EXTRAÍDO      │
├─────────────────────────────────────┼──────────────────────────┤
│  'data.auth.expires'                │  "2025-12-17 15:00:00"   │
│  'data.auth.token'                  │  "abc123"                │
│  'data.auth.user.id'                │  1                       │
│  'data.auth.user.preferences.       │  3600                    │
│   cache.ttl'                        │                          │
└─────────────────────────────────────┴──────────────────────────┘

IMPLEMENTAÇÃO:
getNestedValue($array, 'data.auth.expires')
↓
explode('.') → ['data', 'auth', 'expires']
↓
$array['data'] → {...}
↓
$array['auth'] → {...}
↓
$array['expires'] → "2025-12-17 15:00:00" ✓
```

---

## Fluxo com Fallback TTL

```
┌───────────────────────────────────────────────────────────────────┐
│  cacheUsingExpires('expirationTime', 7200)  ← Fallback 2 horas   │
└───────────────────────────────────────────────────────────────────┘
                              ↓
                    Requisição executada
                              ↓
              ┌───────────────┴───────────────┐
              │                               │
        Campo existe?                   Campo NÃO existe
              │                               │
              ↓                               ↓
   calculateTtlFromResponse()         Usa cacheTtl (7200)
              ↓                               ↓
   Retorna TTL dinâmico (ex: 5400)    TTL = 7200 segundos
              │                               │
              └───────────────┬───────────────┘
                              ↓
                  Cache::put($key, $data, $ttl)
                              ↓
                         Cache ativo ✓
```

---

## Estados do Cache

```
TEMPO DE VIDA DO CACHE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

T = 0s          T = 3600s         T = 7200s         T = 7320s
│               │                 │                 │
│               │                 │                 │
│  1ª Req       │  Cache HIT      │  Cache HIT      │  Cache MISS
│  Cache MISS   │  Response: 1ms  │  Response: 1ms  │  Nova requisição
│  Req HTTP     │                 │                 │  HTTP
│  Cache SET    │                 │                 │  Cache SET
│  TTL: 7320s   │                 │                 │  Novo TTL
│               │                 │                 │
└───────────────┴─────────────────┴─────────────────┴──────────────→
    Cache criado                                        Cache expirou
                         Cache válido


LEGENDA:
━━━━━━━━━━━━━━━━━━━━━━━━━━ = Cache ativo
───────────────────────── = Cache inativo/expirado
```

---

## Encadeamento de Métodos

```
HttpService::
    withoutLogging()              ← Desativa logging
    ->withoutRateLimit()          ← Desativa rate limit
    ->timeout(60)                 ← Timeout de 60s
    ->cacheUsingExpires(          ← Cache dinâmico
        'data.auth.expires',      ← Campo aninhado
        3600                      ← Fallback 1h
    )
    ->expiresAsDatetime()         ← Formato datetime
    ->withHeaders([               ← Headers customizados
        'Authorization' => 'Bearer token',
        'Accept' => 'application/json'
    ])
    ->post($url, $data);          ← Método HTTP

ORDEM DE EXECUÇÃO:
1. Configurações aplicadas (logging, rate limit, timeout)
2. Cache verificado
3. Se cache miss → HTTP request
4. Response processada
5. TTL calculado dinamicamente
6. Response cacheada
7. Response retornada
```

---

## Estrutura de Dados em Cache

```
Cache Key: 'http_service_md5(method+url+payload)'
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Cache Value (array):
{
    "status": 200,                        ← Status code HTTP
    "headers": {                          ← Headers da resposta
        "content-type": [
            "application/json"
        ],
        "cache-control": [
            "no-cache"
        ]
    },
    "body": "{                            ← Body completo da resposta
        \"token\": \"10844259|abc...\",
        \"expirationTime\": \"2025-12-17 13:02:14\"
    }"
}

TTL: 7320 segundos                        ← Calculado dinamicamente

RECONSTITUIÇÃO:
new Response(
    new GuzzleHttp\Psr7\Response(
        $cached['status'],
        $cached['headers'],
        $cached['body']
    )
)
↓
Response idêntica à original
```

---

## Casos de Erro e Tratamento

```
CENÁRIO 1: Campo não existe
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Response: {"token": "abc"}  ← Sem campo 'expirationTime'
↓
getNestedValue() → null
↓
calculateTtlFromResponse() → null
↓
Usa cacheTtl (fallback)
↓
TTL = 3600 segundos ✓


CENÁRIO 2: Data inválida
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Response: {"expirationTime": "data-invalida"}
↓
calculateSecondsUntil("data-invalida")
↓
try { new DateTime(...) } catch (Exception $e) { return null; }
↓
Retorna null
↓
Usa cacheTtl (fallback)
↓
TTL = 3600 segundos ✓


CENÁRIO 3: Data no passado
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Response: {"expirationTime": "2020-01-01 00:00:00"}
↓
calculateSecondsUntil("2020-01-01 00:00:00")
↓
$diff = -157766400 (negativo)
↓
max(1, $diff) → 1 segundo
↓
TTL = 1 segundo (mínimo) ✓


CENÁRIO 4: Resposta não-JSON
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Response: "texto simples"
↓
$response->json() → null
↓
calculateTtlFromResponse() → null
↓
Usa cacheTtl (fallback)
↓
TTL = 3600 segundos ✓
```