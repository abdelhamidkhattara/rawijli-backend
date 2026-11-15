<?php

namespace App\Filament\Resources\Connections;

use App\Filament\Resources\Connections\Pages\ManageConnections;
use App\Models\Connection;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConnectionResource extends Resource
{
    protected static ?string $model = Connection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('workspace_id')->relationship('workspace', 'name')->required(),
                Select::make('platform')->options(['meta' => 'Meta', 'tiktok' => 'TikTok'])->required(),
                Select::make('kind')->options(['page' => 'Facebook Page', 'profile' => 'Instagram Profile', 'ad_account' => 'Meta Ad Account', 'tiktok_account' => 'TikTok Account'])->required(),
                TextInput::make('name')->required(),
                TextInput::make('external_id')->required(),
                Select::make('status')->options(['connected' => 'Connected', 'needs_reauth' => 'Needs re-auth', 'disconnected' => 'Disconnected'])->required(),
                DateTimePicker::make('last_sync_at'),
                TextInput::make('spend_30d')->numeric()->default(0),
                Textarea::make('error')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workspace.name')->label('Workspace')->searchable(),
                TextColumn::make('platform')->badge(),
                TextColumn::make('kind')->badge(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('external_id')->toggleable(),
                TextColumn::make('status')->badge()->colors(['connected' => 'success', 'needs_reauth' => 'warning', 'disconnected' => 'danger']),
                TextColumn::make('last_sync_at')->dateTime()->sortable()->placeholder('Never'),
                TextColumn::make('spend_30d')->money('USD')->sortable(),
                TextColumn::make('error')->limit(40)->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('platform')->options(['meta' => 'Meta', 'tiktok' => 'TikTok']),
                SelectFilter::make('status')->options(['connected' => 'Connected', 'needs_reauth' => 'Needs re-auth', 'disconnected' => 'Disconnected']),
                SelectFilter::make('workspace_id')->relationship('workspace', 'name'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageConnections::route('/'),
        ];
    }
}
