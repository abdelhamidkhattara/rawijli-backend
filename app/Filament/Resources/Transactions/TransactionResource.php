<?php

namespace App\Filament\Resources\Transactions;

use App\Filament\Resources\Transactions\Pages\ManageTransactions;
use App\Models\Transaction;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('workspace_id')->relationship('workspace', 'name')->required(),
                Select::make('contract_id')->relationship('contract', 'title'),
                TextInput::make('amount')->required()->numeric(),
                Select::make('type')->options(['subscription' => 'Subscription', 'escrow_hold' => 'Escrow hold', 'escrow_release' => 'Escrow release', 'refund' => 'Refund', 'marketplace_fee' => 'Marketplace fee'])->required(),
                Select::make('status')->options(['pending' => 'Pending', 'paid' => 'Paid', 'held' => 'Held', 'released' => 'Released', 'refunded' => 'Refunded', 'failed' => 'Failed'])->required(),
                TextInput::make('provider_reference')->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('workspace.name')->label('Workspace')->searchable(),
                TextColumn::make('contract.title')->label('Contract')->limit(35)->placeholder('—'),
                TextColumn::make('amount')->money('USD')->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')->badge()->colors(['paid' => 'success', 'held' => 'warning', 'released' => 'success', 'refunded' => 'danger', 'failed' => 'danger', 'pending' => 'gray']),
                TextColumn::make('provider_reference')->searchable()->limit(30)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(['subscription' => 'Subscription', 'escrow_hold' => 'Escrow hold', 'escrow_release' => 'Escrow release', 'refund' => 'Refund', 'marketplace_fee' => 'Marketplace fee']),
                SelectFilter::make('status')->options(['pending' => 'Pending', 'paid' => 'Paid', 'held' => 'Held', 'released' => 'Released', 'refunded' => 'Refunded', 'failed' => 'Failed']),
                SelectFilter::make('workspace_id')->relationship('workspace', 'name'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTransactions::route('/'),
        ];
    }
}
