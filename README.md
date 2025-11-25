# SYS Consult – Painel Filament

Painel Laravel 12 + Filament para gestão de empresas, certificados, consultas InfoSimples (DTE/caixa postal SEFAZ/AL) e automações agendadas.

## Stack
- PHP 8.4, Laravel 12, Filament
- Docker / Sail (MySQL, Redis, Horizon, Scheduler)
- Mail via SMTP (configure no `.env`)

## Pré-requisitos
- Docker + Docker Compose
- `./vendor/bin/sail` disponível (ou `php artisan sail:install` já aplicado)

## Subindo local
```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail composer install
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed --class=AdminUserSeeder
```

Painel: `http://localhost/app`  
Login inicial (seed): `admin@example.com` / `password`

## Configuração obrigatória (.env)
- **DB**: já configurado para o MySQL do Sail.
- **Mail**: `MAIL_MAILER=smtp`, host/port/user/pass do provedor.
- **InfoSimples**: `INFOSIMPLES_TOKEN`, `INFOSIMPLES_URL` se aplicável.
- **Criptografia**: `APP_KEY` (gerado), chave AES usada nas cargas de certificado já configurada no app.
- **Alertas**: pode definir e-mails extras em `DTE_ALERT_EMAILS` (separados por vírgula).

## RBAC por empresa
- Papéis no pivot `empresa_user`: `owner` | `editor` | `viewer`.
- Admin ignora escopo e vê tudo.
- Vincule usuários às empresas:
```bash
./vendor/bin/sail artisan empresa:atribuir-usuario user@example.com --empresa=ID --role=owner
```

## Ações e comandos úteis
- Verificar/rodar automações prontas (respeita próxima execução):
```bash
./vendor/bin/sail artisan automacao:executar --force
```
- Alertar certificados a vencer (e-mail para empresas delegadas + admin):
```bash
./vendor/bin/sail artisan certificados:alerta-vencimento --dias=30
```
- Reset de filas/monitoramento: Horizon já sobe em container dedicado; veja status:
```bash
./vendor/bin/sail artisan horizon:status
```

## Notas de uso
- Navegação: `/app` (Filament). Menu “Consultas API” abre modal de detalhes ao clicar na linha.
- Mensagens DTE registram visualização (usuário, horário, request_id).
- Exclusões lógicas: usuários/empresas são inativados, não removidos.

## Testes
```bash
./vendor/bin/sail test
```

## Licença
MIT (base Laravel).
