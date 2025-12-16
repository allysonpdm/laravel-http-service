# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2025-12-15

### Added
- Initial release
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
