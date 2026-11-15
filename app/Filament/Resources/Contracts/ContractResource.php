<?php

namespace App\Filament\Resources\Contracts;

use App\Filament\Resources\Contracts\Pages\ManageContracts;
use App\Models\Contract;
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
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('workspace_id')->relationship('workspace', 'name')->required(),
                Select::make('freelancer_id')->relationship('freelancer', 'id')->getOptionLabelFromRecordUsing(fn ($record) => $record->user?->name ?? $record->id)->searchable()->required(),
                Select::make('job_id')->relationship('job', 'title'),
                TextInput::make('title')->required()->maxLength(190),
                TextInput::make('amount')->required()->numeric(),
                Select::make('status')->options(['active' => 'Active', 'completed' => 'Completed', 'disputed' => 'Disputed', 'cancelled' => 'Cancelled'])->required()->default('active'),
                DateTimePicker::make('started_at')->required(),
                Textarea::make('terms')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->limit(50),
                TextColumn::make('workspace.name')->label('Workspace')->searchable(),
                TextColumn::make('freelancer.user.name')->label('Freelancer')->searchable(),
                TextColumn::make('amount')->money('USD')->sortable(),
                TextColumn::make('status')->badge()->colors(['active' => 'success', 'completed' => 'primary', 'disputed' => 'warning', 'cancelled' => 'danger']),
                TextColumn::make('milestones_count')->counts('milestones')->label('Milestones'),
                TextColumn::make('started_at')->dateTime()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(['active' => 'Active', 'completed' => 'Completed', 'disputed' => 'Disputed', 'cancelled' => 'Cancelled']),
                SelectFilter::make('workspace_id')->relationship('workspace', 'name'),
            ])
            ->recordActions([
                Action::make('complete')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn (Contract $record) => $record->status !== 'completed')
                    ->action(function (Contract $record, AuditTrail $audit) {
                        $before = ['status' => $record->status];
                        $record->update(['status' => 'completed']);
                        $audit->record(auth()->user(), 'contract.completed', Contract::class, $record->id, $record->workspace_id, $before, ['status' => 'completed']);
                        FilamentNotification::make()->success()->title('Contract marked completed')->send();
                    }),
                Action::make('cancel')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->visible(fn (Contract $record) => in_array($record->status, ['active', 'disputed']))
                    ->action(function (Contract $record, AuditTrail $audit) {
                        $before = ['status' => $record->status];
                        $record->update(['status' => 'cancelled']);
                        $record->workspace->grants()->where('freelancer_id', $record->freelancer_id)->update(['access_enabled' => false]);
                        $audit->record(auth()->user(), 'contract.cancelled', Contract::class, $record->id, $record->workspace_id, $before, ['status' => 'cancelled']);
                        FilamentNotification::make()->danger()->title('Contract cancelled and access revoked')->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageContracts::route('/'),
        ];
    }
}
