# Plano de Melhoria – RBAC, Delegação de Empresas e Auditoria de Mensagens

## Objetivos
- Definir acesso por empresa (delegação): cada usuário só vê/atua nas empresas atribuídas.
- Auditar visualizações e mudanças de status em mensagens DTE (quem viu, quando, o que alterou).
- Tornar painéis e alertas sensíveis ao escopo do usuário (somente dados das empresas dele).
- Manter logs rastreáveis (request_id) e evitar exposição de segredos.

## Etapas Propostas

### Fase A – Modelagem de acesso
1. **Tabela pivot `empresa_user`**
   - Campos: `user_id`, `empresa_id`, `role` (owner|editor|viewer), timestamps.
   - Unique (`user_id`, `empresa_id`).
2. **Scope global por usuário logado**
   - Aplicar global scope nas consultas de `Empresa`, `EmpresaAutomacao`, `DteMailbox`, `DteMessage`, `ConsultaApi` para filtrar por empresas vinculadas (exceto para admins).
   - Admin (`role=admin` global) ignora scope.
3. **Seeds/ajustes**
   - Associar o admin às empresas existentes (opcional) para manter acesso full.

### Fase B – Permissões no Filament (RBAC)
1. **Policies/Gates por recurso**
   - Recurso “Mensagens DTE”: somente empresas delegadas ou admin.
   - Recurso “Automação”: idem.
   - Recurso “Consultas API”: idem.
2. **Menus condicionais**
   - Ocultar itens de navegação quando o usuário não tiver nenhuma empresa vinculada.
3. **Ações condicionais**
   - “Nova Consulta”, “Executar automação”, “Atualizar status” (mensagem DTE) só habilitadas se usuário tiver acesso à empresa.

### Fase C – Auditoria de mensagens
1. **Log de visualização**
   - Ao abrir mensagem DTE, criar `dte_message_events` com tipo `visualizado` (user_id, request_id, timestamp).
   - Atualizar `primeira_visualizacao_em`/`ultima_interacao_em` e, opcionalmente, status interno → `em_andamento`.
2. **Log de mudança de status/responsável**
   - Já registramos eventos de atualização; garantir que role/viewer não possa editar, só visualizar.
3. **Relatórios**
   - Filters: mensagens não lidas pela SEFAZ vs. não tratadas internamente; responsável; último usuário que visualizou.

### Fase D – Alertas e dashboard por escopo
1. **Alertas (e-mail)**
   - Mensagens críticas: enviar somente para usuários vinculados às empresas das mensagens (e admins em cópia, se desejado).
2. **Widgets/Dashboards**
   - DteOps/QueueHealth devem respeitar escopo: números só das empresas atribuídas.

### Fase E – Cleanup e UX
1. **Campos/labels**
   - Indicar claramente no painel quando o dado está filtrado pelo escopo do usuário.
2. **Fallbacks**
   - Usuário sem empresas: mostrar mensagem/CTA para solicitar acesso.

## Dependências e Checklist
- [ ] Criar migration `empresa_user` e model pivot.
- [ ] Global scopes por usuário (exceto admin) nas entidades chave.
- [ ] Policies/gates por recurso no Filament.
- [ ] Ajustar alertas para usar e-mails dos usuários delegados (em vez de todos). 
- [ ] Log de visualização em mensagens DTE.
- [ ] Ajustar widgets/estats para filtrar por escopo.

## Decisões
- Papéis por empresa: usar owner/editor/viewer na pivot (donos + escritório).
- Admin vê tudo e recebe cópia de alertas (ignora scopes).
- Notificações: enviar para usuários delegados das empresas envolvidas + admin; ajustar também certificados/falhas para seguir esse critério.

---
Última revisão: 2025-11-22.
