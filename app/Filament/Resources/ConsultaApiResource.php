<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConsultaApiResource\Pages;
use App\Models\ConsultaApi;
use App\Models\Empresa;
use App\Models\Certificado;
use App\Services\DteMessageSyncService;
use App\Services\InfoSimplesService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class ConsultaApiResource extends Resource
{
    protected static ?string $model = ConsultaApi::class;
    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';
    protected static ?string $navigationLabel = 'Consultas API';
    protected static ?string $modelLabel = 'Consulta API';
    protected static ?string $pluralModelLabel = 'Consultas API';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Nova Consulta')
                    ->schema([
                        Forms\Components\Select::make('empresa_id')
                            ->label('Empresa')
                            ->relationship('empresa', 'razao_social')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('razao_social')
                                    ->required(),
                                Forms\Components\TextInput::make('cnpj')
                                    ->required(),
                                Forms\Components\TextInput::make('inscricao_estadual')
                                    ->required(),
                            ]),

                        Forms\Components\Select::make('certificado_id')
                            ->label('Certificado')
                            ->relationship('certificado', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome . ' (' . $record->status . ')'),

                        Forms\Components\Select::make('tipo_consulta')
                            ->label('Tipo de Consulta')
                            ->options([
                                'caixa-postal' => 'Caixa Postal SEFAZ/AL',
                                // Adicionar outros tipos conforme necessário
                            ])
                            ->default('caixa-postal')
                            ->required(),
                    ])
                    ->columns(3)
                    ->visible(fn ($livewire) => $livewire instanceof Pages\CreateConsultaApi),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('empresa.razao_social')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('empresa.inscricao_estadual')
                    ->label('IE')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('request_id')
                    ->label('Request ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('automacaoExecucao.id')
                    ->label('Execução #')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('certificado.nome')
                    ->label('Certificado')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\BadgeColumn::make('tipo_consulta')
                    ->label('Tipo')
                    ->colors([
                        'primary' => 'caixa-postal',
                    ]),

                Tables\Columns\BadgeColumn::make('sucesso')
                    ->label('Status')
                    ->getStateUsing(fn ($record) => $record->sucesso ? 'Sucesso' : 'Erro')
                    ->colors([
                        'success' => 'Sucesso',
                        'danger' => 'Erro',
                    ]),

                Tables\Columns\TextColumn::make('response_code')
                    ->label('Código')
                    ->sortable()
                    ->color(fn ($record) => $record->response_code == 200 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('preco')
                    ->label('Preço')
                    ->money('BRL')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tempo_resposta_ms')
                    ->label('Tempo (ms)')
                    ->sortable()
                    ->getStateUsing(fn ($record) => $record->tempo_resposta_ms ? number_format($record->tempo_resposta_ms) . 'ms' : '-'),

                Tables\Columns\TextColumn::make('consultado_em')
                    ->label('Consultado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordAction('ver_detalhes')
            ->recordUrl(null)
            ->filters([
                Tables\Filters\SelectFilter::make('empresa_id')
                    ->label('Empresa')
                    ->relationship('empresa', 'razao_social')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('certificado_id')
                    ->label('Certificado')
                    ->relationship('certificado', 'nome')
                    ->preload(),

                Tables\Filters\SelectFilter::make('tipo_consulta')
                    ->label('Tipo de Consulta')
                    ->options([
                        'caixa-postal' => 'Caixa Postal',
                    ]),

                Tables\Filters\Filter::make('sucesso')
                    ->label('Apenas Sucessos')
                    ->query(fn (Builder $query): Builder => $query->where('sucesso', true)),

                Tables\Filters\Filter::make('erros')
                    ->label('Apenas Erros')
                    ->query(fn (Builder $query): Builder => $query->where('sucesso', false)),

                Tables\Filters\Filter::make('hoje')
                    ->label('Hoje')
                    ->query(fn (Builder $query): Builder => $query->whereDate('consultado_em', today())),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_detalhes')
                    ->label('Ver Detalhes')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Detalhes da Consulta')
                    ->modalContent(fn (ConsultaApi $record) => view('filament.consulta-detalhes', compact('record')))
                    ->modalWidth('7xl')
                    ->color('info'),

                Tables\Actions\Action::make('ver_comprovante')
                    ->label('Comprovante')
                    ->icon('heroicon-o-document')
                    ->url(fn (ConsultaApi $record) => $record->site_receipts[0] ?? null)
                    ->openUrlInNewTab()
                    ->visible(fn (ConsultaApi $record) => !empty($record->site_receipts))
                    ->color('success'),

            ])
            ->headerActions([
                Tables\Actions\Action::make('nova_consulta')
                    ->label('Nova Consulta')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\Select::make('empresa_id')
                            ->label('Empresa')
                            ->options(fn () => Empresa::ativas()->pluck('razao_social', 'id'))
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('certificado_id')
                            ->label('Certificado')
                            ->options(Certificado::ativos()->pluck('nome', 'id'))
                            ->required(),

                        Forms\Components\Select::make('tipo_consulta')
                            ->label('Tipo de Consulta')
                            ->options([
                                'caixa-postal' => 'Caixa Postal SEFAZ/AL',
                            ])
                            ->default('caixa-postal')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $empresa = Empresa::find($data['empresa_id']);
                        $certificado = Certificado::find($data['certificado_id']);

                        $user = auth()->user();
                        if (!$user?->hasEmpresa($empresa->id)) {
                            Notification::make()
                                ->title('Sem permissão para esta empresa')
                                ->danger()
                                ->send();
                            return;
                        }

                        if (!$empresa->inscricao_estadual) {
                            Notification::make()
                                ->title('Empresa sem Inscrição Estadual')
                                ->body('A empresa selecionada não possui inscrição estadual cadastrada.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $service = new InfoSimplesService();
                        $syncService = app(DteMessageSyncService::class);

                        try {
                            $consulta = $service->consultarEmpresaCaixaPostal($empresa, $certificado);
                            $resultadoSync = $syncService->syncFromConsulta($consulta);
                            $importadas = $resultadoSync['importadas'] ?? 0;
                            $atualizadas = $resultadoSync['atualizadas'] ?? 0;

                            if ($consulta->sucesso) {
                                Notification::make()
                                    ->title('Consulta realizada com sucesso!')
                                    ->body("Código: {$consulta->response_code} - {$consulta->code_message} | Mensagens importadas: {$importadas} / atualizadas: {$atualizadas}")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Consulta retornou erro')
                                    ->body("Código: {$consulta->response_code} - {$consulta->code_message}")
                                    ->warning()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Erro ao realizar consulta')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->color('primary'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ])
            ->defaultSort('consultado_em', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConsultaApis::route('/'),
            'create' => Pages\CreateConsultaApi::route('/create'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('consultado_em', '>=', today())->count();
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        $fmt = fn ($state) => is_array($state)
            ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : (is_bool($state) ? ($state ? 'Sim' : 'Não') : (string) $state);

        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('Dados da Consulta')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('empresa.razao_social')->label('Empresa')->formatStateUsing($fmt),
                        \Filament\Infolists\Components\TextEntry::make('empresa.inscricao_estadual')->label('IE')->formatStateUsing($fmt),
                        \Filament\Infolists\Components\TextEntry::make('certificado.nome')->label('Certificado')->formatStateUsing($fmt),
                        \Filament\Infolists\Components\TextEntry::make('tipo_consulta')->label('Tipo')->formatStateUsing($fmt),
                        \Filament\Infolists\Components\TextEntry::make('sucesso')->label('Status')->badge()->color(fn ($state) => $state ? 'success' : 'danger')->formatStateUsing(fn ($state) => $state ? 'Sucesso' : 'Erro'),
                        \Filament\Infolists\Components\TextEntry::make('response_code')
                            ->label('Código')
                            ->formatStateUsing($fmt),
                        \Filament\Infolists\Components\TextEntry::make('code_message')
                            ->label('Mensagem')
                            ->formatStateUsing($fmt),
                        \Filament\Infolists\Components\TextEntry::make('preco')
                            ->label('Preço')
                            ->money('BRL')
                            ->formatStateUsing($fmt),
                        \Filament\Infolists\Components\TextEntry::make('tempo_resposta_ms')
                            ->label('Tempo (ms)')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : ($state ? number_format($state).'ms' : '-')),
                        \Filament\Infolists\Components\TextEntry::make('consultado_em')->label('Consultado em')->dateTime('d/m/Y H:i')->formatStateUsing($fmt),
                        \Filament\Infolists\Components\TextEntry::make('request_id')->label('Request ID')->copyable()->formatStateUsing($fmt),
                        \Filament\Infolists\Components\TextEntry::make('automacaoExecucao.id')->label('Execução #')->formatStateUsing($fmt),
                    ])->columns(3),
                \Filament\Infolists\Components\Section::make('Payload')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('parametro_consulta')
                            ->label('Parâmetro')
                            ->state(fn ($record) => $record?->parametro_consulta ? json_encode($record->parametro_consulta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                            ->columnSpanFull(),
                        \Filament\Infolists\Components\TextEntry::make('resposta_header')
                            ->label('Header')
                            ->state(fn ($record) => $record?->resposta_header ? json_encode($record->resposta_header, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                            ->columnSpanFull(),
                        \Filament\Infolists\Components\TextEntry::make('resposta_data')
                            ->label('Data')
                            ->state(fn ($record) => $record?->resposta_data ? json_encode($record->resposta_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                            ->columnSpanFull(),
                        \Filament\Infolists\Components\TextEntry::make('errors')
                            ->label('Errors')
                            ->state(fn ($record) => $record?->errors ? json_encode($record->errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '-')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        return $user && $user->isAdmin();
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user && $user->isAdmin();
    }
}
