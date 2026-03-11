# Crystal Server no Portainer (Ubuntu 24.04)

Este guia prepara o OT Server para build e execucao via Portainer usando Docker.

## Arquivos deste fluxo
- `docker/docker-compose.portainer.yml`: stack unica para Portainer.
- `docker/docker-compose.portainer.database.yml`: stack separada do MariaDB.
- `docker/docker-compose.portainer.otserver.yml`: stack separada do OTServer.
- `docker/docker-compose.portainer.myaac.yml`: stack separada do MyAAC.
- `docker/.env.portainer.dist`: variaveis de ambiente de exemplo.
- `docker/Dockerfile.x86`: build da aplicacao em Ubuntu 24.04.

## Pre-requisitos
- Host Ubuntu 24.04 com Docker e Portainer instalados.
- Portas liberadas: `7171`, `7172` e `80` (ou a porta definida em `MYAAC_HTTP_PORT`).

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
   - `MYAAC_INSTALL_ALLOWED_IPS` com o IP publico que podera abrir `/install`
3. Abra o Portainer.
4. Va em `Stacks` -> `Add stack`.
5. Selecione `Repository` (Git) e informe:
   - Repository URL: URL deste projeto
   - Compose path: `docker/docker-compose.portainer.yml`
6. Em `Environment variables`, confirme/override variaveis do `.env` se necessario.
7. Clique em `Deploy the stack`.

## Stacks separadas no Portainer
Se quiser subir cada servico em uma stack independente, use os tres arquivos abaixo com exatamente as mesmas variaveis de ambiente:

1. Stack do banco:
   - Compose path: `docker/docker-compose.portainer.database.yml`
2. Stack do servidor:
   - Compose path: `docker/docker-compose.portainer.otserver.yml`
3. Stack do site:
   - Compose path: `docker/docker-compose.portainer.myaac.yml`

Para essas stacks se enxergarem, todas precisam usar os mesmos valores de:
- `SHARED_NETWORK_NAME`
- `OT_DB_HOST`
- `DB_VOLUME_NAME`
- `SERVER_DATA_VOLUME_NAME`
- `MYAAC_VOLUME_NAME`

Ordem recomendada de deploy:
1. `database`
2. `otserver`
3. `myaac`

## O que acontece no primeiro deploy
- O servico `otserver` faz build da imagem local com `docker/Dockerfile.x86`.
- O banco `mariadb` sobe primeiro.
- O `start.sh` do servidor cria/importa schema automaticamente se necessario.
- O servico `myaac` baixa a release configurada em `MYAAC_VERSION` e publica a AAC em Apache/PHP.
- O `otserver` compartilha `config.lua` e datapack em um volume acessivel pelo MyAAC em `/var/www/server`.
- O processo do servidor roda com usuario nao-root dentro do container.

## Seguranca padrao desta stack
- A porta do banco (`3306`) nao e publicada no host por padrao.
- O MariaDB fica acessivel apenas pela rede Docker compartilhada entre as stacks.

## MyAAC
- Por padrao, a stack usa `MYAAC_REF=develop`, que acompanha a linha mais nova do MyAAC.
- Se quiser fixar uma release estavel 1.x, defina `MYAAC_VERSION` e deixe `MYAAC_REF` sem efeito.
- A stack expõe o AAC em `http://SEU_HOST:MYAAC_HTTP_PORT/`.
- Para liberar o instalador, defina `MYAAC_INSTALL_ALLOWED_IPS` com o IP publico do navegador que vai executar a instalacao. Use mais de um IP separado por virgula.
- Apos o deploy, abra `http://SEU_HOST:MYAAC_HTTP_PORT/install`.
- O container do AAC sobe com limite de upload PHP em `64M`, suficiente para plugins maiores.
- Na etapa de configuracao do MyAAC, use:
  - Database host: valor de `OT_DB_HOST` (padrao `database`)
  - Database name: valor de `MYSQL_DATABASE`
  - Database user: valor de `MYSQL_USER`
  - Database password: valor de `MYSQL_PASSWORD`
  - Server path: `/var/www/server`
- O estado do AAC fica no volume `myaac-data`, preservando `config.local.php`, cache, sessoes e imagens entre reinicios/redeploys.
- O path `/var/www/server` vem do volume compartilhado `SERVER_DATA_VOLUME_NAME`, preenchido pelo `otserver`.

## Atualizacao automatica do servidor
Quando houver novo commit:
1. `Stacks` -> `crystalserver` -> `Pull and redeploy` (ou `Update the stack` com rebuild).
2. O Portainer recompila e reinicia os containers mantendo o volume `db-volume`.

## Comandos uteis (host)
```bash
docker compose -f docker/docker-compose.portainer.yml logs -f otserver
docker compose -f docker/docker-compose.portainer.yml logs -f myaac
docker compose -f docker/docker-compose.portainer.yml ps
docker compose -f docker/docker-compose.portainer.yml restart otserver
```
