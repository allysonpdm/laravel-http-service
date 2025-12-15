# Instruções de Publicação

## Pacote: HTTP Service Laravel

### Estrutura Completa Criada

O pacote foi criado com sucesso e contém:

#### Estrutura de Diretórios

```
HttpService/
├── .gitignore
├── CHANGELOG.md
├── composer.json
├── INSTALLATION.md
├── LICENSE
├── README.md
│
├── config/
│   └── http-service.php
│
├── database/
│   └── migrations/
│       ├── create_http_request_logs_table.php
│       └── create_rate_limit_controls_table.php
│
├── examples/
│   └── usage-examples.php
│
└── src/
    ├── HttpServiceProvider.php
    │
    ├── Console/
    │   └── Commands/
    │       ├── CleanExpiredBlocksCommand.php
    │       ├── CleanOldLogsCommand.php
    │       ├── InstallCommand.php
    │       ├── ListBlockedDomainsCommand.php
    │       └── UnblockDomainCommand.php
    │
    ├── Exceptions/
    │   └── RateLimitException.php
    │
    ├── Facades/
    │   └── HttpService.php
    │
    ├── Models/
    │   ├── HttpRequestLog.php
    │   └── RateLimitControl.php
    │
    └── Services/
        └── HttpService.php
```

---

## Como Publicar no Packagist

### 1. Inicializar Repositório Git

```bash
cd HttpService
git init
git add .
git commit -m "Initial commit - HTTP Service Laravel Package v1.0.0"
```

### 2. Criar Repositório no GitHub

1. Acesse: https://github.com/new
2. Nome do repositório: `http-service` ou `laravel-http-service`
3. Descrição: "Laravel HTTP Service with automatic logging and rate limiting control"
4. Público ou Privado (precisa ser público para Packagist gratuito)
5. Não inicialize com README (já temos)

### 3. Conectar ao GitHub

```bash
git remote add origin https://github.com/allysonpdm/laravel-http-service.git
git branch -M main
git push -u origin main
```

### 4. Criar Tag de Versão

```bash
git tag -a v1.0.0 -m "Release v1.0.0 - Initial release"
git push origin v1.0.0
```

### 5. Publicar no Packagist

1. Acesse: https://packagist.org/
2. Faça login (ou crie uma conta)
3. Clique em "Submit"
4. Cole a URL do repositório: `https://github.com/SEU_USUARIO/http-service`
5. Clique em "Check"
6. Se tudo estiver ok, clique em "Submit"

### 6. Configurar Auto-Update (Recomendado)

No GitHub:
1. Vá em Settings → Webhooks
2. Clique em "Add webhook"
3. Payload URL: `https://packagist.org/api/github?username=SEU_USUARIO_PACKAGIST`
4. Content type: `application/json`
5. Secret: (encontre em https://packagist.org/profile/)
6. Events: "Just the push event"
7. Active: ✓

---

## Instalação em Projetos Laravel

Após publicar, os usuários poderão instalar com:

```bash
composer require allysonpdm/laravel-http-service
```

### Configuração Inicial

```bash
php artisan http-service:install
php artisan migrate
```

---

## Atualizações Futuras

### Para publicar uma nova versão:

1. Faça suas alterações
2. Atualize o CHANGELOG.md
3. Commit as mudanças:
   ```bash
   git add .
   git commit -m "feat: nova funcionalidade X"
   git push
   ```

4. Crie nova tag:
   ```bash
   git tag -a v1.1.0 -m "Release v1.1.0 - Descrição das mudanças"
   git push origin v1.1.0
   ```

5. O Packagist atualizará automaticamente (se webhook configurado)

---

## Checklist Pré-Publicação

- [x] composer.json com informações corretas
- [x] README.md completo
- [x] LICENSE (MIT)
- [x] CHANGELOG.md
- [x] .gitignore configurado
- [x] Código documentado
- [x] Migrations criadas
- [x] Models implementados
- [x] Service Provider registrado
- [x] Comandos Artisan funcionais
- [x] Facade criada
- [x] Exemplos de uso
- [x] Guia de instalação

---

## Testar Localmente Antes de Publicar

### Método 1: Composer Local

No projeto Laravel que vai testar:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "HttpService"
        }
    ],
    "require": {
        "allysonpdm/laravel-http-service": "@dev"
    }
}
```

```bash
composer update allysonpdm/laravel-http-service
```

### Método 2: GitHub Direto

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/SEU_USUARIO/http-service"
        }
    ],
    "require": {
        "allysonpdm/laravel-http-service": "dev-main"
    }
}
```

---

## Features Implementadas

**Core**
- HTTP Service com logging automático
- Controle de rate limiting (429)
- Suporte a todos os métodos HTTP (GET, POST, PUT, PATCH, DELETE)
- Headers customizados
- Timeout configurável

**Models**
- HttpRequestLog (logs de requisições)
- RateLimitControl (controle de bloqueios)

**Migrations**
- Tabela http_request_logs
- Tabela rate_limit_controls
- Índices otimizados

**Comandos Artisan**
- `http-service:install` - Instala config e migrations
- `http-service:list-blocks` - Lista domínios bloqueados
- `http-service:unblock` - Desbloqueia domínio
- `http-service:clean-blocks` - Limpa bloqueios expirados
- `http-service:clean-logs` - Limpa logs antigos

**Configuração**
- Arquivo de config completo
- Suporte a variáveis de ambiente
- Todas as opções configuráveis

**Documentação**
- README completo
- Guia de instalação detalhado
- Exemplos de uso
- Changelog
- Licença MIT

---

## Recursos Adicionais

### Badges para README

Após publicar, adicione ao README.md:

```markdown
[![Latest Version](https://img.shields.io/packagist/v/allysonpdm/laravel-http-service.svg)](https://packagist.org/packages/allysonpdm/laravel-http-service)
[![Total Downloads](https://img.shields.io/packagist/dt/allysonpdm/laravel-http-service.svg)](https://packagist.org/packages/allysonpdm/laravel-http-service)
[![License](https://img.shields.io/packagist/l/allysonpdm/laravel-http-service.svg)](https://packagist.org/packages/allysonpdm/laravel-http-service)
```

### Issues e Pull Requests

Configure templates no GitHub:
- `.github/ISSUE_TEMPLATE/bug_report.md`
- `.github/ISSUE_TEMPLATE/feature_request.md`
- `.github/PULL_REQUEST_TEMPLATE.md`

---

## Pronto!

Seu pacote está completo e pronto para ser publicado! 

**Próximos passos:**
1. Criar repositório GitHub
2. Fazer push do código
3. Criar tag v1.0.0
4. Publicar no Packagist
5. Compartilhar com a comunidade!
