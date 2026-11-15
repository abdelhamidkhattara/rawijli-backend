<?php

namespace App\Filament\Resources\Disputes;

use App\Contracts\PaymentProvider;
use App\Filament\Resources\Disputes\Pages\ManageDisputes;
use App\Models\Dispute;
use App\Models\Transaction;
use App\Services\AuditTrail;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DisputeResource extends Resource
{
    protected static ?string $model = Dispute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('contract_id')->relationship('contract', 'title')->required(),
                Select::make('opened_by')->relationship('opener', 'name')->required(),
                Textarea::make('reason')->required()->columnSpanFull(),
                Select::make('status')->options(['open' => 'Open', 'escalated' => 'Escalated', 'resolved' => 'Resolved'])->required()->default('open'),
                Textarea::make('resolution')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract.title')->label('Contract')->searchable()->limit(45),
                TextColumn::make('contract.workspace.name')->label('Workspace')->toggleable(),
                TextColumn::make('opener.name')->label('Opened by')->searchable(),
                TextColumn::make('reason')->limit(70)->wrap(),
                TextColumn::make('status')->badge()->colors(['open' => 'warning', 'escalated' => 'danger', 'resolved' => 'success']),
                TextColumn::make('resolution')->limit(60)->placeholder('—'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['open' => 'Open', 'escalated' => 'Escalated', 'resolved' => 'Resolved']),
            ])
            ->recordActions([
                Action::make('escalate')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->requiresConfirmation()
                    ->visible(fn (Dispute $record) => $record->status === 'open')
                    ->action(function (Dispute $record, AuditTrail $audit) {
                        $record->update(['status' => 'escalated']);
                        $record->events()->create(['actor_id' => auth()->id(), 'status' => 'escalated', 'comment' => 'Escalated for platform review.', 'created_at' => now()]);
                        $audit->record(auth()->user(), 'dispute.escalated', Dispute::class, $record->id, $record->contract?->workspace_id, ['status' => 'open'], ['status' => 'escalated']);
                        FilamentNotification::make()->warning()->title('Dispute escalated')->send();
                    }),
                Action::make('resolve')
                    ->color('success')
                    ->icon('heroicon-o-check-badge')
                    ->visible(fn (Dispute $record) => $record->status !== 'resolved')
                    ->form([
                        Textarea::make('resolution')->required()->label('Resolution notes'),
                        Select::make('escrow')->options(['release' => 'Release held escrow to freelancer', 'refund' => 'Refund held escrow to owner', 'none' => 'No escrow action'])->default('none')->required(),
                    ])
                    ->action(function (Dispute $record, array $data, AuditTrail $audit) {
                        $contract = $record->contract;
                        $hold = Transaction::where('contract_id', $contract->id)->where('type', 'escrow_hold')->where('status', 'held')->first();
                        $payment = app(PaymentProvider::class);
                        if ($data['escrow'] === 'release' && $hold) {
                            $result = $payment->release($hold->provider_reference);
                            Transaction::create(['workspace_id' => $contract->workspace_id, 'contract_id' => $contract->id, 'amount' => $hold->amount, 'type' => 'escrow_release', 'status' => 'released', 'provider_reference' => $result['reference'].'_dispute_'.$record->id, 'created_at' => now()]);
                            $contract->update(['status' => 'completed']);
                        } elseif ($data['escrow'] === 'refund' && $hold) {
                            $result = $payment->refund($hold->provider_reference);
                            Transaction::create(['workspace_id' => $contract->workspace_id, 'contract_id' => $contract->id, 'amount' => $hold->amount, 'type' => 'refund', 'status' => 'refunded', 'provider_reference' => $result['reference'].'_dispute_'.$record->id, 'created_at' => now()]);
                            $contract->update(['status' => 'cancelled']);
                            $contract->workspace->grants()->where('freelancer_id', $contract->freelancer_id)->update(['access_enabled' => false]);
                        }
                        $before = ['status' => $record->status];
                        $record->update(['status' => 'resolved', 'resolution' => $data['resolution']]);
                        $record->events()->create(['actor_id' => auth()->id(), 'status' => 'resolved', 'comment' => $data['resolution'].' ('.$data['escrow'].')', 'created_at' => now()]);
                        $audit->record(auth()->user(), 'dispute.resolved', Dispute::class, $record->id, $contract->workspace_id, $before, ['status' => 'resolved', 'escrow' => $data['escrow']]);
                        FilamentNotification::make()->success()->title('Dispute resolved')->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDisputes::route('/'),
        ];
    }
}
