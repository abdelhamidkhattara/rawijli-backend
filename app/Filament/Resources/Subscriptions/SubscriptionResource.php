<?php

namespace App\Filament\Resources\Subscriptions;

use App\Filament\Resources\Subscriptions\Pages\ManageSubscriptions;
use App\Models\Subscription;
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

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('workspace_id')->relationship('workspace', 'name')->required(),
                Select::make('plan')->options(['starter' => 'Starter', 'growth' => 'Growth', 'scale' => 'Scale'])->required(),
                Select::make('status')->options(['trialing' => 'Trialing', 'active' => 'Active', 'cancelled' => 'Cancelled'])->required()->default('trialing'),
                TextInput::make('amount')->required()->numeric(),
                DateTimePicker::make('current_period_ends_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workspace.name')->label('Workspace')->searchable(),
                TextColumn::make('plan')->badge(),
                TextColumn::make('status')->badge()->colors(['trialing' => 'warning', 'active' => 'success', 'cancelled' => 'danger']),
                TextColumn::make('amount')->money('USD')->sortable(),
                TextColumn::make('current_period_ends_at')->dateTime()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('plan')->options(['starter' => 'Starter', 'growth' => 'Growth', 'scale' => 'Scale']),
                SelectFilter::make('status')->options(['trialing' => 'Trialing', 'active' => 'Active', 'cancelled' => 'Cancelled']),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->visible(fn (Subscription $record) => $record->status !== 'cancelled')
                    ->action(function (Subscription $record, AuditTrail $audit) {
                        $before = ['status' => $record->status];
                        $record->update(['status' => 'cancelled']);
                        $audit->record(auth()->user(), 'subscription.cancelled', Subscription::class, $record->id, $record->workspace_id, $before, ['status' => 'cancelled']);
                        FilamentNotification::make()->success()->title('Subscription cancelled')->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubscriptions::route('/'),
        ];
    }
}
