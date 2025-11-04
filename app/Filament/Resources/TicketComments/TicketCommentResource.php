<?php

namespace App\Filament\Resources\TicketComments;

use Filament\Schemas\Schema;
use Filament\Forms\Components\RichEditor;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\TicketComments\Pages\ListTicketComments;
use App\Filament\Resources\TicketComments\Pages\CreateTicketComment;
use App\Filament\Resources\TicketComments\Pages\EditTicketComment;
use App\Filament\Resources\TicketCommentResource\Pages;
use App\Models\TicketComment;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TicketCommentResource extends Resource
{
    protected static ?string $model = TicketComment::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                RichEditor::make('comment')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ticket.name')
                    ->label(__('resources.ticket_comments.columns.ticket'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('resources.ticket_comments.columns.user'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('resources.ticket_comments.columns.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('resources.ticket_comments.columns.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTicketComments::route('/'),
            'create' => CreateTicketComment::route('/create'),
            'edit' => EditTicketComment::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
