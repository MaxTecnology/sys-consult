<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CertificadoResource\Pages;
use App\Models\Certificado;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CertificadoResource extends Resource
{
    protected static ?string $model = Certificado::class;
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationLabel = 'Certificados';
    protected static ?string $modelLabel = 'Certificado';
    protected static ?string $pluralModelLabel = 'Certificados';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Certificado')
                    ->schema([
                        Forms\Components\TextInput::make('nome')
                            ->label('Nome Identificador')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Certificado Principal 2025'),

                        Forms\Components\DatePicker::make('validade')
                            ->label('Data de Validade')
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            ->after('today')
                            ->helperText('Data de vencimento do certificado digital'),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'ativo' => 'Ativo',
                                'inativo' => 'Inativo',
                            ])
                            ->default('ativo')
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Configurações da API InfoSimples')
                    ->schema([
                        Forms\Components\Textarea::make('pkcs12_cert_encrypted')
                            ->label('Certificado PKCS12 Criptografado')
                            ->required(fn (?Certificado $record) => $record === null)
                            ->dehydrated(fn (?string $state, ?Certificado $record) => $record === null || filled($state))
                            ->rows(4)
                            ->placeholder('Cole aqui o certificado .pfx criptografado em Base64')
                            ->helperText('Certificado digital em formato .pfx convertido para Base64. Deixe em branco ao editar para manter o valor atual.')
                            ->hint(fn (?Certificado $record) => $record && $record->pkcs12_cert_encrypted ? 'Valor já configurado' : 'Nenhum valor configurado')
                            ->hintColor(fn (?Certificado $record) => $record && $record->pkcs12_cert_encrypted ? 'success' : 'warning'),

                        Forms\Components\TextInput::make('pkcs12_pass_encrypted')
                            ->label('Senha do Certificado Criptografada')
                            ->required(fn (?Certificado $record) => $record === null)
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state, ?Certificado $record) => $record === null || filled($state))
                            ->placeholder('Senha criptografada do certificado')
                            ->helperText('Senha do certificado digital. Deixe em branco ao editar para manter o valor atual.')
                            ->hint(fn (?Certificado $record) => $record && $record->pkcs12_pass_encrypted ? 'Valor já configurado' : 'Nenhum valor configurado')
                            ->hintColor(fn (?Certificado $record) => $record && $record->pkcs12_pass_encrypted ? 'success' : 'warning'),

                        Forms\Components\TextInput::make('token_api')
                            ->label('Token da API InfoSimples')
                            ->required(fn (?Certificado $record) => $record === null)
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state, ?Certificado $record) => $record === null || filled($state))
                            ->placeholder('Token de acesso da InfoSimples')
                            ->helperText('Token fornecido pela InfoSimples. Deixe em branco ao editar para manter o valor atual.')
                            ->hint(fn (?Certificado $record) => $record && $record->token_api ? 'Valor já configurado' : 'Nenhum valor configurado')
                            ->hintColor(fn (?Certificado $record) => $record && $record->token_api ? 'success' : 'warning'),

                        Forms\Components\TextInput::make('chave_criptografia')
                            ->label('Chave de Criptografia')
                            ->required(fn (?Certificado $record) => $record === null)
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state, ?Certificado $record) => $record === null || filled($state))
                            ->placeholder('Chave usada para criptografar/descriptografar')
                            ->helperText('Chave AES256 usada para criptografar os dados sensíveis. Em edição, deixe vazio para manter.')
                            ->hint(fn (?Certificado $record) => $record && $record->chave_criptografia ? 'Valor já configurado' : 'Nenhum valor configurado')
                            ->hintColor(fn (?Certificado $record) => $record && $record->chave_criptografia ? 'success' : 'warning'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Observações')
                    ->schema([
                        Forms\Components\Textarea::make('observacoes')
                            ->label('Observações')
                            ->rows(3)
                            ->placeholder('Observações sobre este certificado...')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('ativo')
                    ->label('Ativo')
                    ->getStateUsing(fn ($record) => $record->ativo ? 'Sim' : 'Não')
                    ->colors(fn ($record) => [
                        'success' => $record->ativo,
                        'danger' => !$record->ativo,
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'ativo',
                        'danger' => 'inativo',
                    ]),

                Tables\Columns\TextColumn::make('validade')
                    ->label('Validade')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($record) => $record->vencido ? 'danger' :
                        ($record->dias_para_vencer && $record->dias_para_vencer <= 30 ? 'warning' : 'success')),

                Tables\Columns\TextColumn::make('dias_para_vencer')
                    ->label('Dias p/ Vencer')
                    ->getStateUsing(fn ($record) => $record->dias_para_vencer ?? 'N/A')
                    ->color(fn ($record) => $record->vencido ? 'danger' :
                        ($record->dias_para_vencer && $record->dias_para_vencer <= 30 ? 'warning' : 'success')),

                Tables\Columns\TextColumn::make('consultas_api_count')
                    ->label('Total Consultas')
                    ->counts('consultasApi')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'ativo' => 'Ativo',
                        'inativo' => 'Inativo',
                    ]),

                Tables\Filters\Filter::make('vencidos')
                    ->label('Vencidos')
                    ->query(fn ($query) => $query->where('validade', '<', now())),

                Tables\Filters\Filter::make('vencendo_30_dias')
                    ->label('Vencendo em 30 dias')
                    ->query(fn ($query) => $query->whereBetween('validade', [now(), now()->addDays(30)])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCertificados::route('/'),
            'create' => Pages\CreateCertificado::route('/create'),
            'edit' => Pages\EditCertificado::route('/{record}/edit'),
        ];
    }
}
