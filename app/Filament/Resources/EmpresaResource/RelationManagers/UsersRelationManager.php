<?php

namespace App\Filament\Resources\EmpresaResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';
    protected static ?string $title = 'Usuários vinculados';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('users')
                    ->label('Usuário')
                    ->relationship('users', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('role')
                    ->label('Perfil')
                    ->options([
                        'owner' => 'Owner',
                        'editor' => 'Editor',
                        'viewer' => 'Viewer',
                    ])
                    ->default('viewer')
                    ->required(),
            ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Usuário'),
                TextColumn::make('email')->label('E-mail'),
                BadgeColumn::make('pivot.role')
                    ->label('Perfil')
                    ->colors([
                        'success' => 'owner',
                        'warning' => 'editor',
                        'gray' => 'viewer',
                    ]),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Vincular')
                    ->form([
                        Forms\Components\Select::make('recordId')
                            ->label('Usuário')
                            ->relationship('users', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('role')
                            ->label('Perfil')
                            ->options([
                                'owner' => 'Owner',
                                'editor' => 'Editor',
                                'viewer' => 'Viewer',
                            ])
                            ->default('viewer')
                            ->required(),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Alterar Perfil')
                    ->form([
                        Forms\Components\Select::make('role')
                            ->label('Perfil')
                            ->options([
                                'owner' => 'Owner',
                                'editor' => 'Editor',
                                'viewer' => 'Viewer',
                            ])
                            ->required(),
                    ]),
                DetachAction::make()->label('Remover'),
            ]);
    }

}
