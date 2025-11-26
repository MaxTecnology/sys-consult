<?php

namespace App\Services;

use App\Models\AutomacaoExecucao;
use App\Models\ConsultaApi;
use App\Models\DteMailbox;
use App\Models\DteMessage;
use App\Models\DteMessageEvent;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class DteMessageSyncService
{
    public function syncFromConsulta(ConsultaApi $consulta, ?AutomacaoExecucao $execucao = null): array
    {
        $empresa = $consulta->empresa;

        if (!$empresa) {
            Log::warning('Consulta sem empresa não pode sincronizar mensagens DTE', [
                'consulta_id' => $consulta->id,
            ]);

            return [
                'processadas' => 0,
                'importadas' => 0,
                'atualizadas' => 0,
            ];
        }

        $mailbox = DteMailbox::firstOrCreate(
            [
                'empresa_id' => $empresa->id,
                'canal' => 'sefaz-al',
                'tipo' => 'caixa-postal',
            ],
            [
                'identificador_externo' => $empresa->inscricao_estadual,
                'configuracoes' => ['fonte' => 'infosimples'],
            ]
        );

        $mensagens = $this->extrairMensagens($consulta->resposta_data);

        if (empty($mensagens)) {
            $mailbox->update([
                'ultima_sincronizacao' => now(),
            ]);

            return [
                'processadas' => 0,
                'importadas' => 0,
                'atualizadas' => 0,
            ];
        }

        $importadas = 0;
        $atualizadas = 0;
        $ultimaMensagem = $mailbox->ultima_mensagem_recebida_em;

        foreach ($mensagens as $mensagem) {
            $hash = $this->gerarHash($mailbox->id, $mensagem);

            if (!$hash) {
                continue;
            }

            $payload = $this->transformarPayload($mensagem, $mailbox, $consulta, $execucao, $hash);

            $modelo = DteMessage::where('hash_unico', $hash)->first();

            if (!$modelo) {
                $modelo = DteMessage::create($payload);
                $importadas++;

                DteMessageEvent::create([
                    'dte_message_id' => $modelo->id,
                    'tipo_evento' => 'importado',
                    'descricao' => 'Mensagem importada automaticamente através da InfoSimples',
                    'payload' => [
                        'consulta_api_id' => $consulta->id,
                        'automacao_execucao_id' => $execucao?->id,
                    ],
                    'registrado_em' => now(),
                ]);
            } else {
                $modelo->fill($payload);

                if ($modelo->isDirty()) {
                    $modelo->save();
                    $atualizadas++;

                    DteMessageEvent::create([
                        'dte_message_id' => $modelo->id,
                        'tipo_evento' => 'atualizado',
                        'descricao' => 'Mensagem sincronizada novamente com novos dados da InfoSimples',
                        'payload' => [
                            'mudancas' => $modelo->getChanges(),
                            'consulta_api_id' => $consulta->id,
                            'automacao_execucao_id' => $execucao?->id,
                        ],
                        'registrado_em' => now(),
                    ]);
                }
            }

            if ($modelo->data_envio && (!$ultimaMensagem || $modelo->data_envio->greaterThan($ultimaMensagem))) {
                $ultimaMensagem = $modelo->data_envio;
            }
        }

        $mailbox->update([
            'ultima_sincronizacao' => now(),
            'ultima_mensagem_recebida_em' => $ultimaMensagem,
            'total_mensagens' => $mailbox->mensagens()->count(),
            'total_nao_lidas' => $mailbox->mensagens()->where('lida_sefaz', false)->count(),
        ]);

        return [
            'processadas' => count($mensagens),
            'importadas' => $importadas,
            'atualizadas' => $atualizadas,
            'mailbox_id' => $mailbox->id,
        ];
    }

    private function extrairMensagens($respostaData): array
    {
        // Se vier string JSON, tenta decodificar
        if (is_string($respostaData)) {
            $decoded = json_decode($respostaData, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $respostaData = $decoded;
            }
        }

        if (!is_array($respostaData)) {
            return [];
        }

        // Flatten recursivamente para suportar estruturas novas (mensagens|messages|data|items)
        $colecao = [];
        $pilha = [$respostaData];

        while (!empty($pilha)) {
            $item = array_pop($pilha);
            if (!is_array($item)) {
                continue;
            }

            // Se for uma lista numérica (array de itens), empilha cada um
            if (array_is_list($item)) {
                foreach (array_reverse($item) as $filho) {
                    $pilha[] = $filho;
                }
                continue;
            }

            // Se tiver um agrupador conhecido, empilha os filhos
            foreach (['mensagens', 'messages', 'data', 'items', 'itens'] as $key) {
                if (isset($item[$key]) && is_array($item[$key])) {
                    foreach (array_reverse($item[$key]) as $filho) {
                        $pilha[] = $filho;
                    }
                    // não retorna ainda, pois o próprio item pode ser uma mensagem
                }
            }

            // Heurística mínima para identificar uma mensagem
            if (isset($item['assunto']) || isset($item['remetente']) || isset($item['uid']) || isset($item['id']) || isset($item['codigo'])) {
                $colecao[] = $item;
            }
        }

        return $colecao;
    }

    private function gerarHash(int $mailboxId, array $mensagem): ?string
    {
        $uid = Arr::get($mensagem, 'uid') ?? Arr::get($mensagem, 'id') ?? Arr::get($mensagem, 'codigo');
        $assunto = Arr::get($mensagem, 'assunto');
        $dataEnvio = Arr::get($mensagem, 'data_envio');
        $conteudo = Arr::get($mensagem, 'conteudo_texto') ?? Arr::get($mensagem, 'conteudo');

        if (!$uid && !$assunto && !$dataEnvio && !$conteudo) {
            return null;
        }

        return hash('sha256', implode('|', [
            $mailboxId,
            $uid,
            $assunto,
            $dataEnvio,
            mb_substr($conteudo ?? '', 0, 120),
        ]));
    }

    private function transformarPayload(array $mensagem, DteMailbox $mailbox, ConsultaApi $consulta, ?AutomacaoExecucao $execucao, string $hash): array
    {
        $lida = (bool) Arr::get($mensagem, 'lida', false);

        return [
            'dte_mailbox_id' => $mailbox->id,
            'consulta_api_id' => $consulta->id,
            'automacao_execucao_id' => $execucao?->id,
            'hash_unico' => $hash,
            'message_uid' => Arr::get($mensagem, 'uid') ?? Arr::get($mensagem, 'id') ?? Arr::get($mensagem, 'codigo'),
            'remetente' => Arr::get($mensagem, 'remetente'),
            'assunto' => Arr::get($mensagem, 'assunto'),
            'protocolo' => Arr::get($mensagem, 'protocolo'),
            'categoria' => Arr::get($mensagem, 'categoria'),
            'numero_documento' => Arr::get($mensagem, 'numero_documento') ?? Arr::get($mensagem, 'numero_documento_receita'),
            'lida_sefaz' => $lida,
            'data_envio' => $this->parseDate(Arr::get($mensagem, 'data_envio') ?? Arr::get($mensagem, 'datahora_envio')),
            'data_leitura_sefaz' => $this->parseDate(Arr::get($mensagem, 'datahora_leitura')),
            'disponivel_ate' => $this->parseDate(Arr::get($mensagem, 'data_expiracao') ?? Arr::get($mensagem, 'disponivel_ate')),
            'status_interno' => $lida ? 'em_andamento' : 'novo',
            'resumo' => Arr::get($mensagem, 'resumo'),
            'conteudo_texto' => Arr::get($mensagem, 'conteudo_texto') ?? Arr::get($mensagem, 'conteudo'),
            'conteudo_html' => Arr::get($mensagem, 'conteudo_html'),
            'anexos' => Arr::get($mensagem, 'anexos'),
            'metadados' => $mensagem,
            'requere_atencao' => !$lida || (bool) Arr::get($mensagem, 'prioridade'),
            'ultima_interacao_em' => now(),
        ];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
