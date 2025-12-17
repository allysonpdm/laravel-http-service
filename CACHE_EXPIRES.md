# Cache com Expiração Dinâmica - Documentação Técnica

## Visão Geral

Sistema de cache inteligente que utiliza informações de expiração presentes nas próprias respostas das APIs para determinar automaticamente o tempo de vida (TTL) do cache.

## Arquitetura

### Componentes Adicionados

1. **Propriedades de Controle**
   - `$cacheExpiresField`: Campo da resposta contendo informação de expiração
   - `$cacheExpiresMask`: Tipo de formato do campo (datetime, seconds, minutes)

2. **Métodos Públicos**
   - `cacheUsingExpires(string $field, ?int $fallbackTtl = null)`: Ativa cache dinâmico
   - `expiresAsDatetime()`: Define máscara datetime
   - `expiresAsSeconds()`: Define máscara seconds
   - `expiresAsMinutes()`: Define máscara minutes

3. **Métodos Protegidos**
   - `calculateTtlFromResponse(Response $response)`: Calcula TTL da resposta
   - `getNestedValue(array $array, string $key)`: Extrai valores aninhados
   - `calculateSecondsUntil(string $datetime)`: Converte data em segundos

## Fluxo de Funcionamento

```
1. Usuário chama cacheUsingExpires('expirationTime')
   ↓
2. HttpService marca cacheStrategy como 'always'
   ↓
3. Requisição é executada normalmente
   ↓
4. Antes de cachear, verifica se $cacheExpiresField está definido
   ↓
5. Se sim, chama calculateTtlFromResponse()
   ↓
6. Extrai o valor do campo usando notação de ponto
   ↓
7. Converte o valor para segundos baseado na máscara
   ↓
8. Cacheia com o TTL calculado
   ↓
9. Próximas requisições idênticas retornam do cache
```

## Máscaras de Tempo

### datetime (padrão)
```php
// Resposta: {"expirationTime": "2025-12-17 13:02:14"}
HttpService::cacheUsingExpires('expirationTime')
    ->expiresAsDatetime()
    ->get($url);

// Cálculo: expirationTime - now() = TTL em segundos
```

### seconds
```php
// Resposta: {"expires_in": 3600}
HttpService::cacheUsingExpires('expires_in')
    ->expiresAsSeconds()
    ->get($url);

// Cálculo: TTL = 3600 segundos
```

### minutes
```php
// Resposta: {"ttl": 30}
HttpService::cacheUsingExpires('ttl')
    ->expiresAsMinutes()
    ->get($url);

// Cálculo: TTL = 30 * 60 = 1800 segundos
```

## Notação de Ponto

Sistema para acessar campos aninhados em estruturas JSON:

```php
// Estrutura JSON
{
    "data": {
        "auth": {
            "token": "abc123",
            "expires": "2025-12-17 15:00:00"
        }
    }
}

// Acesso usando notação de ponto
HttpService::cacheUsingExpires('data.auth.expires')
```

**Implementação:**
```php
protected function getNestedValue(array $array, string $key)
{
    $keys = explode('.', $key);
    
    foreach ($keys as $k) {
        if (!is_array($array) || !isset($array[$k])) {
            return null;
        }
        $array = $array[$k];
    }
    
    return $array;
}
```

## Fallback TTL

Quando o campo de expiração não existe ou é inválido:

```php
HttpService::cacheUsingExpires('expirationTime', 7200)
    ->expiresAsDatetime()
    ->get($url);

// Se 'expirationTime' não existir → usa 7200 segundos
// Se 'expirationTime' existir → calcula TTL dinâmico
```

## Casos de Uso

### 1. API de Autenticação
```php
// Token com expiração específica
$token = HttpService::cacheUsingExpires('expirationTime')
    ->expiresAsDatetime()
    ->post('https://api.example.com/auth/token', $credentials);

// Cache válido até a expiração do token
```

### 2. API com Rate Limiting
```php
// API retorna quando pode fazer próxima chamada
$data = HttpService::cacheUsingExpires('rate_limit.reset')
    ->expiresAsDatetime()
    ->get('https://api.github.com/user');

// Evita exceder rate limit
```

### 3. Sessões Temporárias
```php
// Sessão com duração em minutos
$session = HttpService::cacheUsingExpires('session.ttl')
    ->expiresAsMinutes()
    ->post('https://api.example.com/session/create');
```

### 4. Cache de Recursos Estáticos
```php
// API retorna TTL em segundos
$config = HttpService::cacheUsingExpires('cache_ttl')
    ->expiresAsSeconds()
    ->get('https://api.example.com/config');
```

## Vantagens

1. **TTL Preciso**: Usa exatamente o tempo definido pela API
2. **Economia de Recursos**: Evita requisições desnecessárias
3. **Flexível**: Suporta múltiplos formatos de tempo
4. **Resiliente**: Fallback quando campo não existe
5. **Intuitivo**: Notação de ponto para campos aninhados

## Limitações

1. **Formato de Data**: Deve ser compatível com construtor DateTime do PHP
2. **TTL Mínimo**: Sempre 1 segundo, mesmo para datas passadas
3. **Cache Global**: clearCache() limpa todo o cache (não por prefixo)
4. **Precisão**: Depende da precisão do timestamp fornecido pela API

## Testes

Cobertura completa em `tests/Unit/Services/CacheExpiresTest.php`:

- ✓ Cache com datetime
- ✓ Cache com campos aninhados
- ✓ Cache com seconds
- ✓ Cache com minutes
- ✓ Fallback TTL
- ✓ ISO 8601
- ✓ Datas passadas (TTL mínimo)
- ✓ Encadeamento com outros métodos
- ✓ Máscara padrão
- ✓ Datetime inválido
- ✓ Extração de valores aninhados
- ✓ Cálculo de segundos

## Compatibilidade

- PHP 8.2+
- Laravel 12.x
- Requer Cache configurado no Laravel
- Funciona com qualquer driver de cache (file, redis, memcached, etc.)

## Exemplos Práticos

Ver arquivo completo: [examples/cache-expires-examples.php](examples/cache-expires-examples.php)

## Performance

**Primeira Requisição**: Tempo normal + ~1ms (cálculo do TTL)
**Requisições Subsequentes**: Cache hit (instantâneo)

**Impacto em Memória**: Mínimo, apenas armazena:
- Status code
- Headers
- Body da resposta

## Considerações de Segurança

1. Sempre valide se o campo de expiração contém dados confiáveis
2. Use fallback TTL razoável para APIs externas
3. Considere limpar cache periodicamente
4. Não armazene dados sensíveis em cache público

## Migração de Cache Fixo para Dinâmico

**Antes:**
```php
HttpService::withCache(3600)->get($url);
```

**Depois:**
```php
HttpService::cacheUsingExpires('expirationTime', 3600)
    ->expiresAsDatetime()
    ->get($url);
```

## FAQ

**Q: E se a API não retornar campo de expiração?**  
A: Use o fallback TTL: `cacheUsingExpires('field', 3600)`

**Q: Posso usar com POST?**  
A: Sim! Funciona com qualquer método HTTP.

**Q: Como limpar apenas um cache específico?**  
A: Atualmente, apenas `clearCache()` que limpa tudo.

**Q: Funciona com respostas não-JSON?**  
A: Não, requer resposta JSON para extrair o campo.

**Q: Posso combinar com cache condicional?**  
A: Sim, mas `cacheUsingExpires()` já ativa 'always'.
