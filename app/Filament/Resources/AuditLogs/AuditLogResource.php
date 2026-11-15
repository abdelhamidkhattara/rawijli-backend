<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('actor.name')->label('Actor')->placeholder('System'),
                TextColumn::make('workspace.name')->label('Workspace')->placeholder('—'),
                TextColumn::make('action')->searchable()->badge(),
                TextColumn::make('subject_type')->label('Subject')->formatStateUsing(fn ($state) => class_basename($state))->searchable(),
                TextColumn::make('subject_id')->label('Subject ID')->limit(12)->tooltip(fn ($state) => $state),
                TextColumn::make('ip_address')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('workspace_id')->relationship('workspace', 'name')->label('Workspace'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAuditLogs::route('/'),
        ];
    }
}
