<?php

namespace App\Filament\Resources\FreelancerProfiles;

use App\Filament\Resources\FreelancerProfiles\Pages\ManageFreelancerProfiles;
use App\Models\FreelancerProfile;
use App\Services\AuditTrail;
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
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FreelancerProfileResource extends Resource
{
    protected static ?string $model = FreelancerProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')->relationship('user', 'name')->required(),
                TextInput::make('headline')->required()->maxLength(190),
                Textarea::make('bio')->columnSpanFull(),
                CheckboxList::make('skills')->options(['media_buying' => 'Media buying', 'graphic_design' => 'Graphic design', 'video_editing' => 'Video editing', 'copywriting' => 'Copywriting'])->columns(2),
                CheckboxList::make('platforms')->options(['meta' => 'Meta', 'tiktok' => 'TikTok'])->columns(2),
                TextInput::make('hourly_rate')->required()->numeric()->default(50),
                TextInput::make('rating')->required()->numeric()->default(0),
                TextInput::make('reviews')->required()->numeric()->default(0),
                TextInput::make('completed_jobs')->required()->numeric()->default(0),
                TextInput::make('location')->required()->default('Remote'),
                TextInput::make('availability')->required()->default('Available'),
                Toggle::make('verified'),
                Select::make('vetting')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])->required()->default('pending'),
                Toggle::make('public_visible'),
                Textarea::make('vetting_reason')->columnSpanFull(),
                TextInput::make('badges')->helperText('Comma-separated badges')->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)->dehydrateStateUsing(fn ($state) => array_values(array_filter(array_map('trim', explode(',', (string) $state))))),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Freelancer')->searchable()->sortable(),
                TextColumn::make('headline')->searchable()->limit(60),
                TextColumn::make('skills')->badge()->separator(','),
                TextColumn::make('platforms')->badge()->separator(','),
                TextColumn::make('hourly_rate')->money('USD')->sortable(),
                TextColumn::make('rating')->numeric()->sortable(),
                TextColumn::make('completed_jobs')->numeric()->sortable(),
                IconColumn::make('verified')->boolean(),
                TextColumn::make('vetting')->badge()->colors(['approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger']),
                IconColumn::make('public_visible')->boolean(),
                TextColumn::make('location')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('vetting')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected']),
                SelectFilter::make('platforms')->options(['meta' => 'Meta', 'tiktok' => 'TikTok']),
                SelectFilter::make('skills')->options(['media_buying' => 'Media buying', 'graphic_design' => 'Graphic design', 'video_editing' => 'Video editing', 'copywriting' => 'Copywriting']),
            ])
            ->recordActions([
                Action::make('approve')
                    ->color('success')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->visible(fn (FreelancerProfile $record) => $record->vetting !== 'approved')
                    ->action(function (FreelancerProfile $record, AuditTrail $audit) {
                        $before = ['vetting' => $record->vetting];
                        $record->update(['vetting' => 'approved', 'public_visible' => true, 'verified' => true, 'vetting_reason' => null]);
                        $record->user->update(['status' => 'active']);
                        $audit->record(auth()->user(), 'freelancer.approved', FreelancerProfile::class, $record->id, null, $before, ['vetting' => 'approved']);
                        FilamentNotification::make()->success()->title('Freelancer approved')->send();
                    }),
                Action::make('reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->form([Textarea::make('reason')->required()->label('Rejection reason')])
                    ->visible(fn (FreelancerProfile $record) => $record->vetting !== 'rejected')
                    ->action(function (FreelancerProfile $record, array $data, AuditTrail $audit) {
                        $before = ['vetting' => $record->vetting];
                        $record->update(['vetting' => 'rejected', 'public_visible' => false, 'vetting_reason' => $data['reason']]);
                        $record->user->update(['status' => 'pending']);
                        $audit->record(auth()->user(), 'freelancer.rejected', FreelancerProfile::class, $record->id, null, $before, ['vetting' => 'rejected', 'reason' => $data['reason']]);
                        FilamentNotification::make()->danger()->title('Freelancer rejected')->send();
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
            'index' => ManageFreelancerProfiles::route('/'),
        ];
    }
}
