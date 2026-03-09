<?php

namespace App\Filament\Imports;

use App\Models\Empresa;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmpresaImporter extends Importer
{
    protected static ?string $model = Empresa::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('razao_social')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->label('Razão Social'),
            ImportColumn::make('nome_fantasia')
                ->rules(['max:255'])
                ->label('Nome Fantasia'),
            ImportColumn::make('cnpj')
                ->requiredMapping()
                ->rules(['required', 'max:20'])
                ->label('CNPJ')
                // Limpa mascara antes de validar duplicado no BD
                ->guess(['documento', 'cnpj', 'doc'])
                ->castStateUsing(function (string $state): ?string {
                    return preg_replace('/[^0-9]/', '', $state);
                }),
            ImportColumn::make('inscricao_estadual')
                ->rules(['max:30'])
                ->label('Inscrição Estadual'),
            ImportColumn::make('inscricao_municipal')
                ->rules(['max:30'])
                ->label('Inscrição Municipal'),
            ImportColumn::make('email')
                ->rules(['email', 'max:255'])
                ->label('E-mail'),
            ImportColumn::make('telefone')
                ->rules(['max:20'])
                ->castStateUsing(function (string $state): ?string {
                    return preg_replace('/[^0-9]/', '', $state);
                })
                ->label('Telefone'),
            ImportColumn::make('cep')
                ->rules(['max:10'])
                ->castStateUsing(function (string $state): ?string {
                    return preg_replace('/[^0-9]/', '', $state);
                })
                ->label('CEP'),
            ImportColumn::make('endereco')
                ->rules(['max:255'])
                ->label('Endereço/Rua'),
            ImportColumn::make('numero')
                ->rules(['max:20'])
                ->label('Número'),
            ImportColumn::make('complemento')
                ->rules(['max:255'])
                ->label('Complemento'),
            ImportColumn::make('bairro')
                ->rules(['max:100'])
                ->label('Bairro'),
            ImportColumn::make('cidade')
                ->rules(['max:100'])
                ->label('Cidade'),
            ImportColumn::make('uf')
                ->rules(['size:2'])
                ->castStateUsing(function (string $state): ?string {
                    return Str::upper($state);
                })
                ->label('UF (Estado)'),
        ];
    }

    public function resolveRecord(): ?Empresa
    {
        // Se a empresa ja existe pelo CNPJ, ele a atualiza ao inves de duplicar
        $cnpj = preg_replace('/[^0-9]/', '', $this->data['cnpj'] ?? '');

        if (!empty($cnpj)) {
            $empresa = Empresa::withoutGlobalScope(\App\Traits\EmpresaScope::class ?? '')->where('cnpj', $cnpj)->first();
            
            if ($empresa) {
                return $empresa;
            }
        }

        // Se n existir, retorna nova instancia vazia
        return new Empresa();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'A importação das suas empresas foi finalizada e ' . number_format($import->successful_rows) . ' ' . str('linha')->plural($import->successful_rows) . ' foram processadas com sucesso.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' Houve problema com ' . number_format($failedRowsCount) . ' ' . str('linha')->plural($failedRowsCount) . '. Verifique o arquivo de falhas enviado para correção!';
        }

        return $body;
    }

    protected function afterSave(): void
    {
        // Regra de Vínculo: 
        // Ligar a Empresa recém Importada ao Usuário que Fez o Upload (se aplicável/existir)
        $user = auth()->user();
        
        if ($user) {
            // Evita erro de constraint duplicate se ja tiver o vinculo
            DB::table('empresa_user')->updateOrInsert([
                'empresa_id' => $this->record->id,
                'user_id' => $user->id,
            ], [
                'role' => 'owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
