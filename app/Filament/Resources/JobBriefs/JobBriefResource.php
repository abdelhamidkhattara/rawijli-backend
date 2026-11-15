<?php

namespace App\Filament\Resources\JobBriefs;

use App\Filament\Resources\JobBriefs\Pages\ManageJobBriefs;
use App\Models\JobBrief;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JobBriefResource extends Resource
{
    protected static ?string $model = JobBrief::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('workspace_id')->relationship('workspace', 'name')->required(),
                Select::make('owner_id')->relationship('owner', 'name')->required(),
                TextInput::make('title')->required()->maxLength(190),
                Textarea::make('description')->required()->columnSpanFull(),
                CheckboxList::make('skills')->options(['media_buying' => 'Media buying', 'graphic_design' => 'Graphic design', 'video_editing' => 'Video editing', 'copywriting' => 'Copywriting'])->columns(2),
                CheckboxList::make('platforms')->options(['meta' => 'Meta', 'tiktok' => 'TikTok'])->columns(2),
                TextInput::make('budget')->required()->numeric(),
                TextInput::make('duration_days')->required()->numeric()->default(7),
                Select::make('status')->options(['open' => 'Open', 'reviewing' => 'Reviewing', 'hired' => 'Hired', 'closed' => 'Closed'])->required()->default('open'),
                Toggle::make('ai_generated'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->limit(50),
                TextColumn::make('workspace.name')->label('Workspace')->searchable(),
                TextColumn::make('owner.name')->label('Owner')->searchable()->toggleable(),
                TextColumn::make('skills')->badge()->separator(','),
                TextColumn::make('platforms')->badge()->separator(','),
                TextColumn::make('budget')->money('USD')->sortable(),
                TextColumn::make('duration_days')->label('Days')->numeric()->sortable(),
                TextColumn::make('status')->badge()->colors(['open' => 'success', 'reviewing' => 'warning', 'hired' => 'primary', 'closed' => 'gray']),
                IconColumn::make('ai_generated')->label('AI')->boolean(),
                TextColumn::make('proposals_count')->counts('proposals')->label('Proposals'),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(['open' => 'Open', 'reviewing' => 'Reviewing', 'hired' => 'Hired', 'closed' => 'Closed']),
                SelectFilter::make('workspace_id')->relationship('workspace', 'name'),
            ])
            ->recordActions([
                Action::make('close')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (JobBrief $record) => in_array($record->status, ['open', 'reviewing']))
                    ->action(fn (JobBrief $record) => $record->update(['status' => 'closed'])),
                Action::make('reopen')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (JobBrief $record) => $record->status === 'closed')
                    ->action(fn (JobBrief $record) => $record->update(['status' => 'open'])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJobBriefs::route('/'),
        ];
    }
}
