# Plano de Execução – SYS-CONSULT

> Este plano consolida as frentes prioritárias para profissionalizar o monitoramento da Caixa Postal DTE SEFAZ/AL. Cada item possui status em formato de checklist para acompanhamento. Ao concluir uma etapa, marque o checkbox correspondente e registre a data/autor ao lado.

## Como usar este plano
- Atualize este arquivo sempre que avançarmos, marcando `[x]` e adicionando breve nota (`- Concluído em AAAA-MM-DD por ...`).
- Quando surgir um novo requisito, inclua-o na seção adequada com o rótulo `**Novo**` até que seja priorizado.
- Use o arquivo `docs/TODO_SYS_CONSULT.md` para detalhes técnicos aprofundados; aqui focamos no *quê* e *quando*.

---

## Fase 0 – Fundamentos e Governança
- [x] **F0.1 – Validar diagnóstico inicial**  - Concluído em 2025-11-13 por Codex.  
  Revisar `docs/TODO_SYS_CONSULT.md` com o time e ajustar prioridades compartilhadas. *(Dependência: nenhuma)*
- [ ] **F0.2 – Definir responsáveis por frente**  
  Nomear owner para Segurança, DTE, Observabilidade, UX e Testes. *(Dep.: F0.1)*
- [ ] **F0.3 – Pipeline mínimo de CI/CD**  
  Garantir lint/test automatizados em PRs (mesmo que rodem testes vazios inicialmente). *(Dep.: F0.1)*

## Fase 1 – Modelagem DTE & Persistência das Mensagens
- [ ] **F1.1 – Migrations e Models DTE**  
  Criar tabelas `dte_mailboxes`, `dte_messages`, `dte_message_events` com relacionamentos, índices e chaves de deduplicação. *(Dep.: F0.1)*
- [ ] **F1.2 – Ajuste do `ProcessarCaixaPostalJob`**  
  Mapear resposta InfoSimples → mensagens DTE, deduplicar por hash e persistir eventos de leitura. *(Dep.: F1.1)*
- [ ] **F1.3 – Backfill histórico**  
  Script/command para converter `consultas_api.resposta_data` já armazenados em registros `dte_messages`. *(Dep.: F1.2)*
- [ ] **F1.4 – Alertas de mensagens críticas**  
  Definir critérios (não lidas, prazo, remetentes) e disparar notificações (Filament badge + e-mail/Slack). *(Dep.: F1.2)*

## Fase 2 – Segurança de Certificados e Segredos
- [ ] **F2.1 – Criptografia nativa**  
  Aplicar casts `encrypted`/`encrypted:array` nos campos sensíveis de `Certificado` e garantir migração compatível. *(Dep.: F0.1)*
- [ ] **F2.2 – Guia `SECURITY_CERTIFICADOS`**  
  Documentar processo seguro de carga, rotação e descarte de PKCS12 (novo arquivo em `docs/`). *(Dep.: F2.1)*
- [ ] **F2.3 – Sanitização de logs**  
  Criar helper para mascarar segredos em logs/notifications e revisar jobs/commands. *(Dep.: F2.1)*
- [ ] **F2.4 – Validação de certificados antes da automação**  
  Bloquear jobs quando certificado estiver inválido/expirado e registrar motivo nos logs. *(Dep.: F2.1)*

## Fase 3 – Observabilidade, Auditoria e Alertas
- [ ] **F3.1 – User Activity Log**  
  Tabela/model `user_activity_logs` + middleware/traits para registrar ações críticas no painel. *(Dep.: F0.2)*
- [ ] **F3.2 – Logs técnicos enriquecidos**  
  Expandir `AutomacaoExecucao` ou criar `api_call_logs` com request_id, headers relevantes e metadados. *(Dep.: F1.2, F2.3)*
- [ ] **F3.3 – Notificações operacionais**  
  Integrar e-mail/Slack para eventos: certificado vencendo, automação pausada, job falhou. *(Dep.: F3.2)*
- [ ] **F3.4 – Monitoramento da fila**  
  Widgets ou página mostrando status do Horizon, jobs pendentes, taxa de erro. *(Dep.: F3.2)*

## Fase 4 – UX Filament focada em Operações DTE
- [ ] **F4.1 – Resource de Mensagens DTE**  
  Criar recurso/menu dedicado com filtros (empresa, status interno, SEFAZ lido/não lido, SLA). *(Dep.: F1.2)*
- [ ] **F4.2 – Workflows internos**  
  Permitir marcar mensagem como “em andamento/concluída”, atribuir responsável e registrar notas (gera eventos em `dte_message_events`). *(Dep.: F4.1)*
- [ ] **F4.3 – Dashboard operacional**  
  Novos widgets exibindo KPIs: mensagens críticas, empresas sem consultas recentes, certificados próximos do vencimento. *(Dep.: F4.1, F2.4)*
- [ ] **F4.4 – UX de automações**  
  Mostrar contadores de mensagens pendentes e alertas diretamente na listagem de `EmpresaAutomacao`. *(Dep.: F4.1)*

## Fase 5 – Jobs, Scheduler e Testes
- [x] **F5.1 – Command `automacao:reativar-pausadas`**  - Concluído em 2025-11-13 por Codex.  
  Corrigir assinatura, implementar lógica de reativação condicionada e registrar auditoria. *(Dep.: F0.1)*
- [ ] **F5.2 – Revisão de jobs específicos**  
  Garantir idempotência e tratamento de erros em `ProcessarCaixaPostalJob` e `ProcessarSituacaoCadastralJob` (remover mock em produção). *(Dep.: F1.2)*
- [ ] **F5.3 – Estratégia de testes automatizados**  
  Criar suites para commands/jobs/services com mocks InfoSimples + fixtures DTE. *(Dep.: F1.x, F2.x)*
- [ ] **F5.4 – Documentar cronograma de execução**  
  Gráfico/cron para orquestrar jobs de hora em hora, limites de rate limit e dependências. *(Dep.: F5.2)*

---
**Última atualização:** 2025-11-13 03:20 UTC.
