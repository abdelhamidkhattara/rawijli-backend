<?php

namespace App\Filament\Resources\Grants;

use App\Filament\Resources\Grants\Pages\ManageGrants;
use App\Models\Grant;
use App\Services\AuditTrail;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GrantResource extends Resource
{
    protected static ?string $model = Grant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('workspace_id')->relationship('workspace', 'name')->required(),
                Select::make('freelancer_id')->relationship('freelancer', 'id')->getOptionLabelFromRecordUsing(fn ($record) => $record->user?->name ?? $record->id)->searchable()->required(),
                Select::make('contract_id')->relationship('contract', 'title')->required(),
                Toggle::make('access_enabled'),
                CheckboxList::make('permissions')->options(['view_analytics' => 'View analytics', 'upload_creatives' => 'Upload creatives', 'draft_campaigns' => 'Draft campaigns', 'adjust_budgets' => 'Adjust budgets', 'schedule_posts' => 'Schedule posts'])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workspace.name')->label('Workspace')->searchable(),
                TextColumn::make('freelancer.user.name')->label('Freelancer')->searchable(),
                TextColumn::make('contract.title')->label('Contract')->limit(35),
                TextColumn::make('contract.status')->label('Contract status')->badge(),
                IconColumn::make('access_enabled')->label('Access')->boolean(),
                TextColumn::make('permissions')->formatStateUsing(fn ($state) => implode(', ', array_keys(array_filter((array) $state))))->wrap()->limit(80),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('access_enabled')->options(['1' => 'Enabled', '0' => 'Revoked'])->label('Access'),
                SelectFilter::make('workspace_id')->relationship('workspace', 'name'),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->color('danger')
                    ->icon('heroicon-o-lock-closed')
                    ->requiresConfirmation()
                    ->visible(fn (Grant $record) => $record->access_enabled)
                    ->action(function (Grant $record, AuditTrail $audit) {
                        $before = $record->toArray();
                        $record->update(['access_enabled' => false, 'updated_by_id' => auth()->id()]);
                        $audit->record(auth()->user(), 'access.revoked', Grant::class, $record->id, $record->workspace_id, $before, $record->fresh()->toArray());
                        FilamentNotification::make()->success()->title('Access revoked')->send();
                    }),
                Action::make('restore')
                    ->color('success')
                    ->icon('heroicon-o-lock-open')
                    ->requiresConfirmation()
                    ->visible(fn (Grant $record) => ! $record->access_enabled && $record->contract?->status === 'active')
                    ->action(function (Grant $record, AuditTrail $audit) {
                        $before = $record->toArray();
                        $record->update(['access_enabled' => true, 'updated_by_id' => auth()->id()]);
                        $audit->record(auth()->user(), 'access.restored', Grant::class, $record->id, $record->workspace_id, $before, $record->fresh()->toArray());
                        FilamentNotification::make()->success()->title('Access restored')->send();
                    }),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGrants::route('/'),
        ];
    }
}
