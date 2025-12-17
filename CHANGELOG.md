# Changelog

All notable changes to this project will be documented in this file.

## [1.1.0] - 2025-12-17

### Added
- **Cache com Expiração Dinâmica**: Novo método `cacheUsingExpires()` para cachear requisições baseado em campos da resposta
  - Suporte para campos aninhados usando notação de ponto (ex: `data.auth.expires`)
  - Múltiplas máscaras de tempo:
    - `expiresAsDatetime()` - Para campos com data/hora (Y-m-d H:i:s ou ISO 8601)
    - `expiresAsSeconds()` - Para campos com TTL em segundos
    - `expiresAsMinutes()` - Para campos com TTL em minutos
  - TTL de fallback quando o campo não existe na resposta
  - Cálculo automático de TTL baseado em timestamp de expiração
- **Novos Métodos Utilitários**:
  - `calculateTtlFromResponse()` - Calcula TTL dinâmico da resposta
  - `getNestedValue()` - Extrai valores de arrays aninhados
  - `calculateSecondsUntil()` - Converte data futura em segundos
- **Documentação**:
  - Seção completa sobre cache no README.md
  - Arquivo de exemplos: `examples/cache-expires-examples.php`
  - Testes unitários: `tests/Unit/Services/CacheExpiresTest.php`

### Improved
- Sistema de cache mais flexível com suporte a TTL dinâmico
- Documentação expandida com exemplos práticos de uso de cache

- HTTP request wrapper with automatic logging
- Rate limiting control (429 errors) with database storage
- Models for HttpRequestLog and RateLimitControl
- Migrations for database tables
- HttpService facade for easy usage
- ServiceProvider with auto-discovery
- Installation command: `http-service:install`
- Utility commands:
  - `http-service:list-blocks` - List blocked domains
  - `http-service:unblock` - Manually unblock a domain
  - `http-service:clean-blocks` - Clean expired blocks
- Configuration file with environment variable support
- Support for all HTTP methods (GET, POST, PUT, PATCH, DELETE)
- Customizable timeout per request
- Ability to disable logging/rate limiting per request
- Custom headers support
- Comprehensive documentation and examples
- Laravel 12 compatibility

### Features
- Automatic logging of all HTTP requests (URL, payload, response)
- Intelligent rate limiting with 429 error handling
- Domain-based blocking system
- Automatic retry-after header detection
- Query scopes for easy log filtering
- Clean expired blocks functionality
- Manual domain blocking/unblocking
- Configurable via config file and .env
- Artisan commands for management
- Exception handling for rate limits
- Response time tracking
