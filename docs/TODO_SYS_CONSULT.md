# SYS-CONSULT – Diagnóstico Inicial

> Documento criado para cumprir o passo 1 das instruções em `docs/instrucoes.md`.

## 1. Componentes Avaliados

| Item | Situação | Observações |
| ---- | -------- | ----------- |
| Model `Empresa` (`app/Models/Empresa.php`) | OK | Relacionamentos com `ConsultaApi` e `EmpresaAutomacao`; possui scopes e mutators para CNPJ. |
| Model `Certificado` (`app/Models/Certificado.php`) | GAP | Campos sensíveis são `text` simples, sem casts `encrypted`. Necessário hardening. |
| Model `ConsultaApi` (`app/Models/ConsultaApi.php`) | OK parcial | Loga resposta completa, mas não separa mensagens DTE nem anonimiza segredos. |
| Model `AutomacaoTipo` (`app/Models/AutomacaoTipo.php`) | OK | Catálogo completo com scopes utilitários. |
| Model `EmpresaAutomacao` (`app/Models/EmpresaAutomacao.php`) | OK parcial | Controla frequências, mas não referencia mensagens DTE; status `pausada` não é reativado automaticamente. |
| Model `AutomacaoExecucao` (`app/Models/AutomacaoExecucao.php`) | OK parcial | Registra execuções, porém não relaciona métricas com logs externos nem gera alertas. |
| Service `InfoSimplesService` (`app/Services/InfoSimplesService.php`) | OK | Implementa consulta e persistência. Contudo existe duplicata em `app/Services/ConsultaEmpresaService.php` que precisa ser removida/unificada. |
| Job `ProcessarAutomacaoJob` | OK parcial | Coordena filas e delays, mas não trata deduplicação de mensagens e não valida estado do certificado antes de cada job. |
| Job `ProcessarCaixaPostalJob` | GAP | Somente grava `ConsultaApi`; não persiste mensagens DTE nem deduplica. |
| Job `ProcessarSituacaoCadastralJob` | GAP | Mock em produção, falta integração real e depende de `Log` sem import. |
| Command `automacao:executar` | OK | Responsável pelo fluxo principal. |
| Command `automacao:limpar-logs` | OK | Remove execuções antigas. |
| Command `certificados:verificar-vencimento` | GAP | Apenas pausa automações; não envia alertas. |
| Command `automacao:status` | OK | Faz overview rápido. |
| Command `automacao:reativar-pausadas` | GAP crítico | Arquivo `app/Console/Commands/ReativarAutomacoesPausadasCommand.php` ainda possui assinatura placeholder (`app:reativar...`) e sem lógica. |
| Filament Resources (Empresas, Certificados, Automações, Consultas API) | OK parcial | CRUDs funcionam, mas não exibem mensagens DTE nem alertas claros de automação. |

## 2. Monitoramento Caixa Postal DTE SEFAZ/AL

### O que já existe
- Consulta via InfoSimples para Caixa Postal é chamada em `ProcessarCaixaPostalJob` e pelo recurso Filament de Consultas API.
- `resources/views/filament/consulta-detalhes.blade.php` exibe mensagens retornadas no JSON, mas somente dentro do modal – não há persistência dedicada.

### Lacunas identificadas
1. **Persistência estruturada inexistente:** não há migrations/models para caixas postais ou mensagens (ex.: `dte_messages`). Todo payload fica em JSON (`ConsultaApi.resposta_data`).
2. **Deduplicação inexistente:** jobs podem inserir vários registros idênticos se InfoSimples retornar o mesmo lote.
3. **Status interno de mensagens:** não há campos para marcar uma mensagem como “em tratamento” ou “concluída” pela equipe.
4. **Alertas de mensagens não lidas:** não há painel/relatórios destacando mensagens críticas.
5. **Trilha de interação:** não existe `dte_message_events` ou similar para auditar quem leu/tratou cada mensagem.

## 3. Segurança de Certificados

### Pontos OK
- Model `Certificado` centraliza `pkcs12_cert_encrypted`, `pkcs12_pass_encrypted`, `token_api` e `chave_criptografia`.
- Campos são `hidden`, evitando exibição acidental via JSON/Filament automaticamente.

### Pendências
1. **Criptografia em repouso:** migrations usam `text` puro, sem casts `encrypted`. Precisa usar `protected $casts = ['pkcs12_cert_encrypted' => 'encrypted', ...]` ou solução custom.
2. **Processo de criptografia não documentado:** é necessário `docs/SECURITY_CERTIFICADOS.md` descrevendo fluxo seguro (gera chave, criptografa antes de salvar, mascarar logs).
3. **Tratamento em logs:** Jobs e commands atuais fazem `Log::error` com mensagens completas; precisamos garantir que dados sensíveis sejam mascarados.

## 4. Observabilidade & Auditoria

### Pontos OK
- `AutomacaoExecucao` armazena métricas básicas (duração, custo, status).
- `ConsultaApi` guarda `response_code`, `tempo_resposta_ms`, `preco`.

### Pendências
1. **Logs de usuário inexistentes:** não há `UserActivityLog`. Ações no painel não ficam registradas.
2. **Logs técnicos ricos:** falta tabela específica (ou expansão de `AutomacaoExecucao`) para guardar request_id, headers relevantes, erros detalhados (mas mascarados).
3. **Alertas automáticos:** não há integração com email/Slack para falhas críticas (certificado vencido, automação pausada, consulta com erro).
4. **Teste/monitoramento:** nenhum teste automatizado garante comportamento dos comandos/jobs.

## 5. UX no Painel Filament

### Pontos OK
- Recursos básicos de cadastro funcionam e possuem filtros úteis.
- Modal de detalhes de consulta mostra estatísticas simples de mensagens.

### Pendências
1. **Listagem dedicada de mensagens DTE:** precisa de Resource ou página custom mostrando mensagens por empresa/status.
2. **Badges/alertas:** Automações listam status, mas não mostram contagem de mensagens não lidas ou erros recentes.
3. **Dashboard limitado:** widget atual mostra somente contagens de consultas, não mensagens críticas ou certificados perto do vencimento.
4. **Ações internas:** não há forma de marcar mensagem como tratada, atribuir responsável ou adicionar notas.

## 6. Prioridades Recomendadas (High-Level)
1. **Modelagem DTE:** criar migrations/models para caixas postais e mensagens + ajustar `ProcessarCaixaPostalJob` para popular as tabelas com deduplicação.
2. **Segurança certificada:** aplicar encriptação nativa + documentar processo + revisar logs.
3. **Command `automacao:reativar-pausadas`:** implementar lógica real coerente com o agendamento.
4. **Observabilidade:** adicionar `UserActivityLog`, métricas detalhadas e notificações (Slack/email) para falhas.
5. **UX Filament:** criar recursos para mensagens DTE, badges de alerta e dashboards operacionais.
6. **Testes automatizados:** adicionar testes para jobs/commands principais com mocks da InfoSimples.

---
Ultima atualização: 2025-11-13 03:00 UTC.
