# SYS-CONSULT – Visão Geral

## Propósito do Sistema
SYS-CONSULT é um painel interno (Laravel 12 + Filament 3) usado para centralizar consultas fiscais feitas na API da InfoSimples. O sistema permite cadastrar empresas e certificados digitais, disparar consultas manuais (ex.: Caixa Postal SEFAZ/AL) e orquestrar automações recorrentes que rodam através de jobs em fila e do scheduler.

## Pilha e Infraestrutura
- **Backend:** PHP 8.2, Laravel 12, Eloquent, Filament 3.
- **Frontend build:** Vite + Tailwind (usado pelos componentes Filament).
- **Fila e tempo real:** Redis + Horizon (container `horizon` em `docker-compose.yml`).
- **Banco:** MySQL 8 (container `mysql`).
- **Scheduler:** container dedicado executando `php artisan schedule:work` para acionar `automacao:executar` a cada 5 minutos.
- **Outros serviços:** Laravel Sail para padronizar ambiente; integração HTTP com InfoSimples (`config/services.php`).

## Domínio e Entidades
| Tabela / Model | Descrição Resumida |
| -------------- | ------------------ |
| `empresas` (`App\Models\Empresa`) | Dados cadastrais, contatos e status da empresa consultada. |
| `certificados` (`App\Models\Certificado`) | Certificados digitais (PKCS12 criptografado, token InfoSimples, validade e observações). |
| `consultas_api` (`App\Models\ConsultaApi`) | Log de cada consulta (payload, headers, comprovantes, tempo e preço). |
| `automacao_tipos` (`App\Models\AutomacaoTipo`) | Catálogo de tipos de consulta, com frequências padrão, custo estimado e flags de automação. |
| `empresa_automacao` (`App\Models\EmpresaAutomacao`) | Configuração por empresa/tipo, contendo frequência personalizada, certificado e controle de status. |
| `automacao_execucoes` (`App\Models\AutomacaoExecucao`) | Histórico granular das execuções de automação, incluindo métricas e erros. |

## Fluxo de Consulta Manual
1. Usuário acessa o painel (`/admin`) e usa o recurso **Consultas API** (`app/Filament/Resources/ConsultaApiResource.php`).
2. O formulário exige empresa ativa + certificado válido e valida se a IE está preenchida.
3. O `InfoSimplesService` (`app/Services/InfoSimplesService.php`) envia POST para `/sefaz/al/dec/caixa-postal`, usando o certificado armazenado e o token InfoSimples.
4. A resposta é persistida em `consultas_api` com metadados (preço, response_code, comprovantes) e a empresa tem `ultima_consulta_api` atualizada.
5. A view `resources/views/filament/consulta-detalhes.blade.php` exibe mensagens lidas/não lidas e links dos recibos.

## Fluxo de Automação
1. O scheduler chama `php artisan automacao:executar` (comando em `app/Console/Commands/AutomacaoCommand.php`).
2. O comando lista `EmpresaAutomacao::prontas()` (status ativo e `proxima_execucao <= now()`), respeitando filtros por tipo ou empresa.
3. Se houver execuções, despacha `ProcessarAutomacaoJob` com os filtros solicitados.
4. `ProcessarAutomacaoJob` (coordenador) carrega novamente as automações, aplica espaçamento (`intervalo_empresas_segundos`) e cria jobs específicos por tipo:
   - `ProcessarCaixaPostalJob` → implementado (consulta InfoSimples real).
   - `ProcessarSituacaoCadastralJob` → ainda mockado, apenas gera `ConsultaApi` fictícia.
5. Cada job específico herda de `BaseConsultaJob`, que cuida de rate limit, validações (empresa ativa, certificado válido), log de execução em `automacao_execucoes` e tratamento de erros.
6. Ao finalizar com sucesso, `EmpresaAutomacao::marcarComoExecutada()` recalcula `proxima_execucao`; falhas incrementam `tentativas_consecutivas_erro` e podem pausar a automação automaticamente.

## Recursos no Painel Filament
- **Empresas:** CRUD completo com filtros por UF/status e ação “Nova Consulta”.
- **Certificados:** gestão de certificados, alertas visuais para vencidos (usa `dias_para_vencer`).
- **Automações:** visualização das próximas execuções, gatilhos “Executar agora”, “Executar todas prontas”, pausar/reativar e bulk actions (`app/Filament/Resources/EmpresaAutomacaoResource.php`).
- **Consultas API:** listagem rica com filtros, detalhes em modal, atalho para comprovantes e ação em massa de nova consulta manual.
- **Dashboard:** widget `ConsultasStatsWidget` mostra contagem diária, gasto e quantidade de certificados/empresas.

## Comandos Artisan Importantes
| Comando | Função |
| ------- | ------ |
| `php artisan automacao:executar [--tipo=] [--empresa=] [--dry-run]` | Dispara varredura de automações prontas (usado pelo scheduler). |
| `php artisan automacao:limpar-logs --dias=30` | Remove registros antigos de `automacao_execucoes`. |
| `php artisan certificados:verificar-vencimento --dias=30` | Lista certificados que vencem em breve e pausa automações afetadas. |
| `php artisan automacao:status` | Mostra contagens (ativas/prontas/com erro). |

> **Observação:** o schedule em `routes/console.php` também define `automacao:reativar-pausadas`, mas o comando correspondente ainda não foi implementado.

## Configuração Local (Sail)
```bash
cp .env.example .env
composer install
npm install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan db:seed --class=AutomacaoTiposSeeder
./vendor/bin/sail npm run dev
```
- Inicie `./vendor/bin/sail artisan horizon` se não estiver usando o container dedicado.
- Para disparar automações manualmente execute `./vendor/bin/sail artisan automacao:executar --dry-run`.

## Integração com InfoSimples
- Endpoint configurável via `INFOSIMPLES_BASE_URL` (`config/services.php`).
- As credenciais sensíveis (certificado PKCS12 em Base64, senha e token) são armazenadas por certificado, criptografadas antes de entrar no banco. Cada consulta envia:
  - `pkcs12_cert`, `pkcs12_pass` e `token` → lidos de `certificados`.
  - `ie` → lida da empresa.
- Timeout padrão: 300 s na API, 320 s no cliente HTTP.

## Pontos de Atenção & Próximos Passos
1. **Scheduler aponta para comando inexistente:** `routes/console.php` agenda `automacao:reativar-pausadas`, porém `app/Console/Commands/ReativarAutomacoesPausadasCommand.php` ainda usa assinatura `app:reativar-...` e não possui implementação. Ajustar assinatura + lógica de reativação automática.
2. **Duplicação de serviço:** `app/Services/ConsultaEmpresaService.php` declara novamente `class InfoSimplesService`. Remover arquivo duplicado ou renomear corretamente para evitar autoload conflitante.
3. **Job mockado:** `app/Jobs/ProcessarSituacaoCadastralJob.php` ainda grava consultas artificiais e usa `Log` sem import. Implementar integração real ou proteger instância para não gerar dados inconsistentes.
4. **Comando de verificação parcial:** `VerificarVencimentoCertificadosCommand` já pausa automações de certificados vencidos, mas não notifica ninguém. Integrar e-mail/Slack usando `config('mail.admin_email')` ou canal do Slack.
5. **Testes automatizados inexistentes:** não há testes em `tests/`. Priorizar cobertura para comandos, jobs e serviços de integração (mockando InfoSimples) antes de evoluir novas features.
6. **Segurança de segredos:** hoje os campos criptografados são recebidos como texto puro. Documentar processo de criptografia (ex.: usar `php artisan key:generate` + helper) e garantir que nunca se suba segredos reais no seed.
7. **Visibilidade de falhas:** avaliar criação de notificações Filament/Horizon ou alertas externos quando `AutomacaoExecucao` marcar `temErro` ou `requer_atencao`.

## Sugestões de Evolução
- Implementar dashboard secundário com gráficos (consultas por tipo, custo por período) usando dados de `AutomacaoExecucao::estatisticasHoje()`.
- Criar policies/roles se o painel tiver múltiplos usuários.
- Adicionar health check para filas (ex.: card exibindo se Horizon está rodando).
- Automatizar importação de novos tipos de consulta via seeds ou UI.

---
Última revisão: 2025-11-13 02:36 UTC.
