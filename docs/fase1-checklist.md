# Checklist de Testes – Fase 1 (Mensagens DTE)

Use esta lista toda vez que quiser validar se os ajustes da Fase 1 continuam íntegros antes de seguir para a próxima fase.

## Pré-requisitos
- Banco de dados atualizado (`php artisan migrate`).
   [OK]
   - Jobs automáticos ligados ou execução manual via `php artisan automacao:executar --dry-run` para validar fila.
   [
      sail artisan automacao:executar --dry-run
      Iniciando verificação de automações...
      ✅ Nenhuma automação pronta para execução
   ]
- Certificado e empresa com inscrições válidas para chamar a API da InfoSimples.
   [Cadastrados]

## 1. Consulta manual via Filament
1. Acesse o painel `/admin` > **Consultas API**.
2. Clique em “Nova Consulta” e selecione empresa + certificado.
3. Após confirmar:
   - Verifique se a notificação mostra o código da API e os contadores “Mensagens importadas/atualizadas”.
   [
      Consulta retornou erro
      Código: 604 - A consulta não foi validada antes de pesquisar a fonte de origem.
   ]
   - Navegue até **Consultas API** > registro recém-criado > botão “Ver Detalhes” e confirme que o JSON foi salvo normalmente.
   [
      Empresa: SANTANA E CIA HOME CENTER LTDA MATRIZ
      IE: 242709141
      Certificado: G2A
      Data/Hora: 13/11/2025 10:21:36
      Status: Erro
      Código: 604
      Preço: R$ 0,00
      Tempo de Resposta: -390ms
      Erros
      Não foi possível decriptar os parâmetros. Reveja sua integração.
      Dados Técnicos
      Price: 0.0
      Product: Consultas
      Service: sefaz/al/dec/caixa-postal
      Billable:
      Remote ip: 177.131.224.12
      Signature: /fsDUz/ZjNrumm213fkha9OsZMP0uoM4gr/bmUkcJg4gZ1Zxq14wwMO1fRlbmiMrYheXdoFCPQx7acYBWVFdtxSqZxVca0pF22aMFA==
      Parameters:
      Token name: A.M. Contabilidade Ltda
      Api version: v2
      Client name: A.M. Contabilidade Ltda
      Requested at: 2025-11-13T10:21:35.794-03:00
      Api version full: 2.2.32-20251112184328
      Elapsed time in milliseconds:
   ]
4. Vá até **Empresas** > ação “Nova Consulta” e repita. Certifique-se de que a mensagem de sucesso também exibe os contadores.

## 2. Execução automática (fila)
1. Garanta que `php artisan automacao:executar --dry-run` lista pelo menos uma automação pronta.
2. Execute `php artisan automacao:executar` (sem `--dry-run`) ou deixe o scheduler/Horizon rodar.
3. No Horizon (ou logs), confirme que `ProcessarCaixaPostalJob` rodou sem erros.
4. Verifique em `automacao_execucoes` (Filament ou banco) se as métricas `dte_mensagens_*` foram preenchidas.

## 3. Sincronização DTE
1. Rode `php artisan dte:backfill-messages --limit=10` para garantir que novas consultas históricas podem ser importadas sem erro.
2. Confirme que há registros em:
   - `dte_mailboxes` (uma por empresa).
   - `dte_messages` (mensagens deduplicadas, com hashes únicos).
   - `dte_message_events` (evento “importado” criado).

## 4. Dashboard e alertas
1. Acesse `/admin` > Dashboard.
2. O widget “Mensagens não lidas” deve refletir o total atual (compare com `dte_messages` onde `lida_sefaz = 0`).
3. “Mensagens críticas” deve mostrar ≥0 se houver `requere_atencao` ligado + status interno “novo”.
4. “Certificados vencendo” e “Automações com erro” devem coincidir com o que aparecem nos recursos correspondentes.

## 5. Fluxos negativos / erros
1. Simule consulta manual com empresa sem inscrição estadual -> deve aparecer notificação de erro (sem tentar a API).
2. Caso a InfoSimples retorne erro, verifique se a mensagem aparece e a sincronização não cria registros.
3. Execute o comando `automacao:reativar-pausadas --dry-run` para garantir que as automações voltam ao fluxo quando aptas.

## Observações
- Sempre que encontrar divergências, anote o ID da consulta/empresa, copie a mensagem de log/notificação e inclua no feedback.
- Se o ambiente local não tiver acesso à InfoSimples, mocke a resposta atualizando `consultas_api.resposta_data` manualmente e rode `dte:backfill-messages` para validar os passos 3 e 4.

---
Última revisão: 2025-11-13 04:45 UTC.
