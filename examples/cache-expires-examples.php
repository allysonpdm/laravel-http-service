<?php

/**
 * Exemplos de uso do cache com expiração dinâmica baseada em campos da resposta
 */

use ThreeRN\HttpService\Facades\HttpService;

// ===========================================
// Exemplo 1: Cache usando campo de data/hora
// ===========================================
// Resposta esperada:
// {
//     "token": "10844259|nXDBMZzHe4X72yl2laCwcsJxPNzTnARBjRM02sjC6715c588",
//     "expirationTime": "2025-12-17 13:02:14"
// }

$response = HttpService::cacheUsingExpires('expirationTime')
    ->expiresAsDatetime() // Define que o campo contém uma data/hora (padrão)
    ->get('https://api.example.com/auth/token');

// O cache será armazenado até 2025-12-17 13:02:14
// O TTL é calculado automaticamente: expirationTime - agora

// ===========================================
// Exemplo 2: Cache com campo aninhado
// ===========================================
// Resposta esperada:
// {
//     "data": {
//         "auth": {
//             "token": "abc123",
//             "expires": "2025-12-17 15:30:00"
//         }
//     }
// }

$response = HttpService::cacheUsingExpires('data.auth.expires')
    ->expiresAsDatetime()
    ->post('https://api.example.com/login', [
        'username' => 'user',
        'password' => 'pass'
    ]);

// ===========================================
// Exemplo 3: Cache usando segundos
// ===========================================
// Resposta esperada:
// {
//     "token": "xyz789",
//     "expires_in": 3600  // segundos
// }

$response = HttpService::cacheUsingExpires('expires_in')
    ->expiresAsSeconds() // O valor do campo está em segundos
    ->get('https://api.example.com/token');

// O cache será armazenado por 3600 segundos (1 hora)

// ===========================================
// Exemplo 4: Cache usando minutos
// ===========================================
// Resposta esperada:
// {
//     "session_id": "sess_123",
//     "ttl": 30  // minutos
// }

$response = HttpService::cacheUsingExpires('ttl')
    ->expiresAsMinutes() // O valor do campo está em minutos
    ->post('https://api.example.com/session/create');

// O cache será armazenado por 30 minutos (1800 segundos)

// ===========================================
// Exemplo 5: Cache com fallback TTL
// ===========================================
// Se o campo não existir na resposta ou for inválido, usa o TTL de fallback

$response = HttpService::cacheUsingExpires('expirationTime', 7200) // 2 horas de fallback
    ->expiresAsDatetime()
    ->get('https://api.example.com/data');

// Se 'expirationTime' não existir, o cache será por 7200 segundos (2 horas)

// ===========================================
// Exemplo 6: Encadeamento com outras opções
// ===========================================

$response = HttpService::withoutLogging()
    ->cacheUsingExpires('data.expires_at')
    ->expiresAsDatetime()
    ->timeout(60)
    ->withHeaders([
        'Authorization' => 'Bearer token123'
    ])
    ->get('https://api.example.com/protected/data');

// ===========================================
// Exemplo 7: POST com cache dinâmico
// ===========================================
// Resposta esperada:
// {
//     "result": "success",
//     "cache_until": "2025-12-17 18:00:00"
// }

$response = HttpService::cacheUsingExpires('cache_until')
    ->expiresAsDatetime()
    ->post('https://api.example.com/calculate', [
        'operation' => 'sum',
        'values' => [1, 2, 3]
    ]);

// ===========================================
// Exemplo 8: Usando formato ISO 8601
// ===========================================
// Resposta esperada:
// {
//     "resource_id": "res_123",
//     "expires": "2025-12-17T13:02:14Z"
// }

$response = HttpService::cacheUsingExpires('expires')
    ->expiresAsDatetime() // Suporta ISO 8601 automaticamente
    ->get('https://api.example.com/resource/123');

// ===========================================
// Exemplo 9: Cache com múltiplas requisições
// ===========================================

// A primeira requisição vai buscar do servidor e cachear
$response1 = HttpService::cacheUsingExpires('expirationTime')
    ->expiresAsDatetime()
    ->get('https://api.example.com/auth/token');

// A segunda requisição vai retornar do cache
$response2 = HttpService::cacheUsingExpires('expirationTime')
    ->expiresAsDatetime()
    ->get('https://api.example.com/auth/token');

// ===========================================
// Exemplo 10: Limpando o cache
// ===========================================

HttpService::clearCache(); // Limpa todo o cache de requisições

// ===========================================
// Comparação: Cache fixo vs Cache dinâmico
// ===========================================

// Cache FIXO (TTL sempre 3600 segundos)
$response = HttpService::withCache(3600)
    ->get('https://api.example.com/data');

// Cache DINÂMICO (TTL baseado no campo da resposta)
$response = HttpService::cacheUsingExpires('expirationTime')
    ->expiresAsDatetime()
    ->get('https://api.example.com/data');

// ===========================================
// Notas importantes:
// ===========================================

/*
 * 1. Os métodos expiresAsDatetime(), expiresAsSeconds() e expiresAsMinutes()
 *    definem como interpretar o valor do campo de expiração
 * 
 * 2. Por padrão, se você usar apenas cacheUsingExpires(), assume datetime
 * 
 * 3. Se o campo não existir ou for inválido, usa o cacheTtl padrão ou o fallbackTtl
 * 
 * 4. Formatos de data/hora suportados:
 *    - Y-m-d H:i:s (2025-12-17 13:02:14)
 *    - ISO 8601 (2025-12-17T13:02:14Z)
 *    - Qualquer formato aceito pelo construtor DateTime do PHP
 * 
 * 5. Notação de ponto para campos aninhados:
 *    - 'field' => busca $response['field']
 *    - 'data.auth.expires' => busca $response['data']['auth']['expires']
 * 
 * 6. O TTL mínimo é sempre 1 segundo, mesmo que a data já tenha passado
 */
