<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DteMessageResource\Pages;
use App\Models\DteMessage;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\Action;
use Filament\Forms;

class DteMessageResource extends Resource
{
    protected static ?string $model = DteMessage::class;
    protected static ?string $navigationIcon = 'heroicon-o-inbox';
    protected static ?string $navigationLabel = 'Mensagens DTE';
    protected static ?string $modelLabel = 'Mensagem DTE';
    protected static ?string $pluralModelLabel = 'Mensagens DTE';
    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mailbox.empresa.razao_social')
                    ->label('Empresa')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('assunto')
                    ->label('Assunto')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('lida_sefaz')
                    ->label('Lida SEFAZ')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->lida_sefaz ? 'Sim' : 'Não')
                    ->colors(fn ($record) => [
                        'success' => $record->lida_sefaz,
                        'warning' => !$record->lida_sefaz,
                    ]),
                TextColumn::make('status_interno')
                    ->label('Status interno')
                    ->badge()
                    ->colors([
                        'warning' => 'novo',
                        'info' => 'em_andamento',
                        'success' => 'concluido',
                        'gray' => 'ignorado',
                    ]),
                TextColumn::make('data_envio')
                    ->label('Envio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('disponivel_ate')
                    ->label('Disponível até')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('message_uid')
                    ->label('UID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable()
                    ->limit(20),
            ])
            ->filters([
                SelectFilter::make('lida_sefaz')
                    ->label('Lida SEFAZ')
                    ->options([
                        1 => 'Sim',
                        0 => 'Não',
                    ]),
                SelectFilter::make('status_interno')
                    ->label('Status interno')
                    ->options([
                        'novo' => 'Novo',
                        'em_andamento' => 'Em andamento',
                        'concluido' => 'Concluído',
                        'ignorado' => 'Ignorado',
                    ]),
                SelectFilter::make('mailbox_id')
                    ->label('Empresa')
                    ->relationship('mailbox.empresa', 'razao_social')
                    ->searchable()
                    ->preload(),
                Filter::make('criticas')
                    ->label('Requer atenção')
                    ->query(fn ($query) => $query->where('requere_atencao', true)),
            ])
            ->actions([
                Action::make('atualizar_status')
                    ->label('Atualizar')
                    ->icon('heroicon-o-pencil-square')
                    ->form([
                        Forms\Components\Select::make('status_interno')
                            ->label('Status interno')
                            ->options([
                                'novo' => 'Novo',
                                'em_andamento' => 'Em andamento',
                                'concluido' => 'Concluído',
                                'ignorado' => 'Ignorado',
                            ])
                            ->required(),
                        Forms\Components\Select::make('responsavel_id')
                            ->label('Responsável')
                            ->options(User::where('ativo', true)->pluck('name', 'id'))
                            ->searchable()
                            ->preload(),
                        Forms\Components\Textarea::make('observacao')
                            ->label('Observação')
                            ->rows(3),
                    ])
                    ->action(function (DteMessage $record, array $data) {
                        $record->status_interno = $data['status_interno'];
                        if (!empty($data['responsavel_id'])) {
                            $record->responsavel_id = $data['responsavel_id'];
                        }

                        // Ajusta flag de atenção
                        $record->requere_atencao = !in_array($record->status_interno, ['concluido', 'ignorado']);
                        $record->ultima_interacao_em = now();
                        $record->save();

                        // Registrar evento
                        \App\Models\DteMessageEvent::create([
                            'dte_message_id' => $record->id,
                            'user_id' => auth()->id(),
                            'tipo_evento' => 'atualizado',
                            'descricao' => 'Status/Responsável atualizado via painel',
                            'payload' => [
                                'status_interno' => $record->status_interno,
                                'responsavel_id' => $record->responsavel_id,
                                'observacao' => $data['observacao'] ?? null,
                            ],
                            'registrado_em' => now(),
                        ]);
                    })
                    ->requiresConfirmation(),
            ])
            ->defaultSort('data_envio', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDteMessages::route('/'),
            'view' => Pages\ViewDteMessage::route('/{record}'),
        ];
    }
}
