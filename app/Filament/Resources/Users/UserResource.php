<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Services\AuditTrail;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('email')->label('Email address')->email()->required()->unique(ignoreRecord: true),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
                Select::make('role')->options(['owner' => 'Owner', 'freelancer' => 'Freelancer', 'admin' => 'Admin'])->required()->default('owner'),
                Select::make('status')->options(['active' => 'Active', 'pending' => 'Pending', 'suspended' => 'Suspended'])->required()->default('active'),
                TextInput::make('title')->maxLength(160),
                TextInput::make('company')->maxLength(160),
                Select::make('plan')->options(['starter' => 'Starter', 'growth' => 'Growth', 'scale' => 'Scale']),
                TextInput::make('ai_credits_used')->required()->numeric()->default(0),
                TextInput::make('ai_credits_limit')->required()->numeric()->default(200),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->label('Email address')->searchable(),
                TextColumn::make('role')->badge()->colors(['owner' => 'primary', 'freelancer' => 'success', 'admin' => 'danger']),
                TextColumn::make('status')->badge()->colors(['active' => 'success', 'pending' => 'warning', 'suspended' => 'danger']),
                TextColumn::make('plan')->badge()->placeholder('—'),
                TextColumn::make('company')->searchable()->toggleable(),
                TextColumn::make('ai_credits_used')->label('AI used')->numeric(),
                TextColumn::make('ai_credits_limit')->label('AI limit')->numeric(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')->options(['owner' => 'Owner', 'freelancer' => 'Freelancer', 'admin' => 'Admin']),
                SelectFilter::make('status')->options(['active' => 'Active', 'pending' => 'Pending', 'suspended' => 'Suspended']),
                SelectFilter::make('plan')->options(['starter' => 'Starter', 'growth' => 'Growth', 'scale' => 'Scale']),
            ])
            ->recordActions([
                Action::make('suspend')
                    ->color('danger')
                    ->icon('heroicon-o-no-symbol')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => $record->status !== 'suspended')
                    ->action(function (User $record, AuditTrail $audit) {
                        $before = ['status' => $record->status];
                        $record->update(['status' => 'suspended']);
                        $audit->record(auth()->user(), 'user.suspended', User::class, $record->id, null, $before, ['status' => 'suspended']);
                        FilamentNotification::make()->success()->title('User suspended')->send();
                    }),
                Action::make('restore')
                    ->color('success')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => $record->status === 'suspended')
                    ->action(function (User $record, AuditTrail $audit) {
                        $before = ['status' => $record->status];
                        $record->update(['status' => 'active']);
                        $audit->record(auth()->user(), 'user.restored', User::class, $record->id, null, $before, ['status' => 'active']);
                        FilamentNotification::make()->success()->title('User restored')->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
