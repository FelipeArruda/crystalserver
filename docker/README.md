# Crystal Server no Portainer (Ubuntu 24.04)

Este guia prepara o OT Server para build e execucao via Portainer usando Docker.

## Arquivos deste fluxo
- `docker/docker-compose.portainer.yml`: stack para Portainer.
- `docker/.env.portainer.dist`: variaveis de ambiente de exemplo.
- `docker/Dockerfile.x86`: build da aplicacao em Ubuntu 24.04.

## Pre-requisitos
- Host Ubuntu 24.04 com Docker e Portainer instalados.
- Portas liberadas: `7171`, `7172`, `80` e `9090`.

## Passo a passo (Portainer)
1. No repositorio, copie o arquivo de ambiente:
   ```bash
   cd docker
   cp .env.portainer.dist .env
   ```
2. Edite `docker/.env` e ajuste no minimo:
   - `MYSQL_PASSWORD`
   - `MYSQL_ROOT_PASSWORD`
   - `SERVER_IP` (`auto` ou IP publico fixo)
3. Abra o Portainer.
4. Va em `Stacks` -> `Add stack`.
5. Selecione `Repository` (Git) e informe:
   - Repository URL: URL deste projeto
   - Compose path: `docker/docker-compose.portainer.yml`
6. Em `Environment variables`, confirme/override variaveis do `.env` se necessario.
7. Clique em `Deploy the stack`.

## O que acontece no primeiro deploy
- O servico `otserver` faz build da imagem com `docker/Dockerfile.x86`.
- O banco `mariadb` sobe primeiro.
- O `start.sh` do servidor cria/importa schema automaticamente se necessario.
- O processo do servidor roda com usuario nao-root dentro do container.

## Seguranca padrao desta stack
- A porta do banco (`3306`) nao e publicada no host por padrao.
- O MariaDB fica acessivel apenas pela rede interna do compose.

## Login server (opcional)
- O servico `login` esta marcado com profile `with-login`.
- A imagem `crystalserver/login-server:latest` pode nao estar publica no registry.
- Deploy padrao (sem login-server):
  ```bash
  docker compose -f docker/docker-compose.portainer.yml up -d --build
  ```
- Deploy com login-server (somente se voce tiver imagem valida):
  ```bash
  docker compose --profile with-login -f docker/docker-compose.portainer.yml up -d --build
  ```

## Atualizacao automatica do servidor
Quando houver novo commit:
1. `Stacks` -> `crystalserver` -> `Pull and redeploy` (ou `Update the stack` com rebuild).
2. O Portainer recompila e reinicia os containers mantendo o volume `db-volume`.

## Comandos uteis (host)
```bash
docker compose -f docker/docker-compose.portainer.yml logs -f otserver
docker compose -f docker/docker-compose.portainer.yml ps
docker compose -f docker/docker-compose.portainer.yml restart otserver
```
