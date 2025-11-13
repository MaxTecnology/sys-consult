Você é um desenvolvedor sênior de Laravel e arquiteto de software. 
Você tem acesso ao repositório de um sistema chamado **SYS-CONSULT**, descrito abaixo, e deve:

1. Analisar o código atual.
2. Verificar se o que está descrito na visão geral realmente existe e funciona como esperado.
3. Identificar lacunas para que o sistema seja uma solução profissional de monitoramento de Caixa Postal DTE SEFAZ/AL, com foco em segurança, robustez e rastreabilidade.
4. Implementar as melhorias necessárias (código, migrations, testes, ajustes de comandos/jobs, etc.) sem quebrar o que já está funcionando.

---

## CONTEXTO DO SISTEMA (SYS-CONSULT)

SYS-CONSULT é um painel interno (Laravel 12 + Filament 3) usado para centralizar consultas fiscais feitas na API da InfoSimples. O sistema permite cadastrar empresas e certificados digitais, disparar consultas manuais (ex.: Caixa Postal SEFAZ/AL) e orquestrar automações recorrentes que rodam através de jobs em fila e do scheduler.

### Stack e Infra

- Backend: PHP 8.2, Laravel 12, Eloquent, Filament 3.
- Frontend build: Vite + Tailwind (componentes Filament).
- Fila: Redis + Horizon.
- Banco: MySQL 8.
- Scheduler: container dedicado executando `php artisan schedule:work`, disparando `automacao:executar` a cada 5 minutos.
- Integração HTTP com InfoSimples (`config/services.php`).

### Entidades principais

- `Empresa` / tabela `empresas`
- `Certificado` / tabela `certificados`
- `ConsultaApi` / tabela `consultas_api`
- `AutomacaoTipo` / tabela `automacao_tipos`
- `EmpresaAutomacao` / tabela `empresa_automacao`
- `AutomacaoExecucao` / tabela `automacao_execucoes`

### Fluxo resumido

**Consulta manual:**
- Usuário acessa `/admin` (Filament).
- Usa recurso **Consultas API**.
- `InfoSimplesService` chama o endpoint `/sefaz/al/dec/caixa-postal` na InfoSimples com certificado + token + IE.
- Resposta é gravada em `consultas_api` (payload, metadados, comprovação de consulta).
- Há uma view Blade para detalhes de consulta com exibição de mensagens lidas/não lidas.

**Automação:**
- `php artisan automacao:executar` lista `EmpresaAutomacao::prontas()`.
- Despacha `ProcessarAutomacaoJob`, que coordena:
  - `ProcessarCaixaPostalJob` (consulta real de Caixa Postal).
  - `ProcessarSituacaoCadastralJob` (ainda mockado).
- `BaseConsultaJob` controla rate limit, validações, logging em `automacao_execucoes` e tratamento de erros.
- `EmpresaAutomacao::marcarComoExecutada()` atualiza `proxima_execucao` e trata erros.

### Pontos já identificados (pelo resumo)

- Existe um comando agendado `automacao:reativar-pausadas` referenciado em `routes/console.php`, mas a implementação ainda usa outra assinatura (`app:reativar-...`) e está incompleta.
- Há uma duplicação de `InfoSimplesService` em `app/Services/ConsultaEmpresaService.php`.
- `ProcessarSituacaoCadastralJob` está mockado, gerando dados fictícios.
- O comando de verificação de vencimento de certificados não notifica ninguém.
- Não há testes automatizados (`tests/` vazio ou quase vazio).
- Os campos criptografados são recebidos como texto puro, e o processo de criptografia não está documentado.
- Falta visibilidade melhor das falhas de automação (alertas).

---

## OBJETIVO DE NEGÓCIO

Transformar o SYS-CONSULT em uma aplicação **profissional** para monitorar a **Caixa Postal do DTE SEFAZ/AL** via InfoSimples, garantindo:

- Que nenhuma mensagem importante da Caixa Postal deixe de ser capturada e registrada.
- Que haja logs e trilha de auditoria completos:
  - de acesso dos usuários;
  - de chamadas à API;
  - de processamento de automações;
  - de interação do usuário com as mensagens (visualização, mudança de status, etc.).
- Que a automação seja resiliente, com tratamento de erros, retentativas controladas e alertas quando algo não funcionar.
- Que o manuseio de certificados digitais (PKCS12) seja seguro (criptografia em repouso, não vazar segredos em logs, etc.).
- Que o painel Filament ofereça uma visão clara das mensagens da Caixa Postal, especialmente:
  - mensagens **não tratadas** internamente;
  - mensagens **não lidas** (segundo a SEFAZ);
  - empresas com problemas de automação ou certificado vencido.

---

## O QUE VOCÊ DEVE FAZER NO CÓDIGO

### 1. Diagnóstico da base existente

1.1. Ler o código dos seguintes pontos e confirmar se estão coerentes com a descrição:
- Models: `Empresa`, `Certificado`, `ConsultaApi`, `AutomacaoTipo`, `EmpresaAutomacao`, `AutomacaoExecucao`.
- Services: `InfoSimplesService` (garantir que exista só uma implementação canônica).
- Jobs: `ProcessarAutomacaoJob`, `ProcessarCaixaPostalJob`, `ProcessarSituacaoCadastralJob`, `BaseConsultaJob`.
- Commands: `AutomacaoCommand` (`automacao:executar`), `automacao:limpar-logs`, `certificados:verificar-vencimento`, `automacao:status`, e o comando que deveria ser `automacao:reativar-pausadas`.
- Resources Filament principais (Empresas, Certificados, Automações, Consultas API, Dashboard).

1.2. Listar (em comentários e/ou em um arquivo `docs/TODO_SYS_CONSULT.md`) o que já está OK e o que precisa ser melhorado para:
- Monitoramento de Caixa Postal DTE SEFAZ/AL.
- Segurança de certificados.
- Observabilidade (logs, métricas).
- UX no painel (visibilidade de mensagens e problemas).

### 2. Persistência de mensagens da Caixa Postal (Domínio DTE)

2.1. Verificar se já existe algum modelo/tabela para armazenar **mensagens da Caixa Postal** (ex.: algo como `dte_messages`).

- Se **não existir**, criar:
  - Migration para tabelas específicas do domínio DTE, por exemplo:
    - `dte_mailboxes` (por empresa/origem).
    - `dte_messages` (mensagens individuais).
    - `dte_message_events` (auditoria interna de ações do usuário).
  - Models Eloquent correspondentes.
  - Relacionamentos com `Empresa` e/ou `EmpresaAutomacao`.
- Se **já existir**, revisar o design e ajustar se necessário para:
  - Conseguir identificar mensagens de forma única (hash ou ID externo).
  - Guardar:
    - assunto, remetente,
    - datas de envio e leitura SEFAZ,
    - flags de lida na SEFAZ,
    - conteúdo texto/HTML,
    - status interno (novo, em_andamento, concluido, etc.).

2.2. Ajustar o fluxo de consulta (especialmente `ProcessarCaixaPostalJob`) para:
- Mapear corretamente o JSON de resposta da InfoSimples para as tabelas de mensagens.
- Deduplicar mensagens usando um `hash_unico`.
- Marcar novas mensagens para notificação interna.
- Atualizar status de “lida pela SEFAZ” quando a API indicar `lida = true`.

### 3. Logs técnicos e auditoria

3.1. Revisar `ConsultaApi` e `AutomacaoExecucao`:

- Garantir que cada chamada à API InfoSimples:
  - seja registrada com:
    - payload (sem expor segredos),
    - HTTP status,
    - tempo de resposta,
    - custo retornado pela InfoSimples (se disponível),
    - correlação com empresa/automação.
- Se necessário, criar uma tabela mais específica para logs de API (ex.: `api_call_logs`) e ajustar o fluxo para usá-la.

3.2. Implementar uma trilha de auditoria de usuários, por exemplo:

- Criar tabela/model `UserActivityLog` registrando:
  - usuário,
  - ação (login, logout, visualizar mensagem, alterar status de mensagem, alterar certificado, etc.),
  - recurso afetado (tipo + id),
  - IP,
  - user agent,
  - timestamp.
- Integrar essa auditoria nos pontos sensíveis (Controllers/Filament Actions/Policies).

3.3. Garantir que os logs não gravem nunca:
- senha do PKCS12 em texto puro,
- conteúdo completo de certificados,
- tokens sensíveis.

Mas manter o suficiente para auditoria técnica (como masked values ou hashes).

### 4. Segurança de certificados

4.1. Analisar a Model e migrations de `Certificado`:

- Confirmar se o certificado PKCS12 está sendo guardado de forma segura (idealmente criptografado).
- Confirmar se a senha está criptografada (por ex. usando encriptação nativa do Laravel).

4.2. Melhorar o uso de criptografia:

- Se ainda não estiver, usar casts de encriptação do Laravel (`encrypted`, `encrypted:json` ou similares).
- Documentar o processo em um arquivo `docs/SECURITY_CERTIFICADOS.md` explicando:
  - quais campos são encriptados;
  - quais cuidados tomar no `.env` e nos ambientes (não commitar segredos, etc.).

### 5. Automações e scheduler

5.1. Ajustar o comando `automacao:reativar-pausadas`:

- Corrigir a assinatura do comando para bater com o agendamento em `routes/console.php`.
- Implementar a lógica para:
  - reativar automações pausadas que já não estejam em situação de erro crítico ou certificado vencido;
  - registrar em `AutomacaoExecucao` e/ou outro log que a reativação foi feita.

5.2. Revisar `ProcessarAutomacaoJob` e os Jobs específicos:

- Garantir que:
  - `ProcessarCaixaPostalJob` está idempotente (rodar várias vezes não gera duplicação de mensagens).
  - `ProcessarSituacaoCadastralJob` não gere dados fictícios em ambiente de produção.
    - Se for manter mock em dev/test, condicionar isso ao `APP_ENV`.
- Melhorar o tratamento de erros e retentativas:
  - usar properly `failed()` nos Jobs,
  - atualizar `EmpresaAutomacao` para pausar em caso de erro recorrente,
  - registrar a causa do erro de forma clara.

5.3. Frequência de consulta:

- Garantir que a automação permita configurar periodicidades adequadas (até de hora em hora).
- Deixar claro no código e eventualmente em documentação como evitar sobrecarregar a InfoSimples (respeito a rate limits).

### 6. Painel Filament (UX focada em DTE)

6.1. Criar/ajustar recursos no Filament para:

- Listar as mensagens de Caixa Postal por empresa, com:
  - filtros por status interno (novo, em andamento, concluído),
  - filtro por “não lidas na SEFAZ”,
  - filtro por nível de criticidade, se houver.
- Exibir detalhes da mensagem:
  - conteúdo HTML/texto,
  - metadados (data de envio, leitura, remetente).
- Permitir que o usuário:
  - registre que viu a mensagem (evento de auditoria),
  - altere o status interno (novo → em andamento → concluído),
  - adicione observações.

6.2. Dashboard:

- Incluir widgets que mostrem:
  - número de mensagens novas por dia/semana,
  - empresas com mais mensagens não tratadas,
  - empresas com automação com erro ou pausada,
  - certificados próximos do vencimento.

### 7. Segurança de acesso e permissões

7.1. Implementar ou revisar roles/policies:

- Pelo menos perfis como:
  - `admin_sistema` – gerencia tudo, inclusive certificados e configuração de automação.
  - `gestor_fiscal` – gerencia empresas, vê todas as mensagens, gerencia status.
  - `operador_fiscal` – pode apenas visualizar e alterar status de mensagens, mas não mexer em certificados/automação.

7.2. Integrar essas permissões no Filament (Resources, Actions, Widgets), garantindo que usuário sem permissão não veja nem execute ações indevidas.

### 8. Testes automatizados

8.1. Criar testes (PHPUnit/Pest) para:

- `InfoSimplesService` (mockando HTTP).
- `ProcessarCaixaPostalJob`:
  - cenário com novas mensagens,
  - cenário sem novas mensagens,
  - idempotência.
- `AutomacaoCommand`:
  - seleção de `EmpresaAutomacao::prontas()`,
  - despacho correto de jobs.
- Comando `certificados:verificar-vencimento`:
  - pausa automações de certificados vencidos.

8.2. Se possível, adicionar alguns Feature Tests para endpoints ou fluxos Filament críticos.

### 9. Documentação mínima

9.1. Criar/atualizar arquivos de documentação em `docs/` (pode criar se não existir), por exemplo:

- `docs/ARCHITECTURE.md` – visão geral de componentes, filas, scheduler.
- `docs/DTE_CAIXA_POSTAL.md` – explicando o fluxo específico de Caixa Postal, tabelas, jobs e principais riscos.
- `docs/SECURITY_CERTIFICADOS.md` – detalhes da segurança de certificados.
- `docs/AUTOMACOES.md` – como funcionam as automações, comandos, retentativas e reativação.

---

## ESTILO DE IMPLEMENTAÇÃO

- Não remover funcionalidades existentes; refatore com cuidado.
- Preferir código claro, com nomes expressivos e comentários apenas onde realmente ajudam.
- Manter padrões de estilo do projeto (PSR-12, convenções de namespace, etc.).
- Em migrations novas, preservar compatibilidade com dados existentes (sem quebrar produção).
- Ao final, gerar um resumo (em comentários ou em um arquivo `docs/CHANGELOG_SYS_CONSULT.md`) listando:
  - novas tabelas/models criados;
  - jobs/commands ajustados;
  - resources Filament criados/alterados;
  - principais mudanças de segurança;
  - testes adicionados.

Comece analisando a estrutura atual do projeto, identificando o que já existe em relação a tudo isso, e depois faça uma documentaçao do que precisamos melhorar e vamos melhorando de acordo com o cronograma de alteraçoes que vc montar dentro de /docs em um arquivo .md
