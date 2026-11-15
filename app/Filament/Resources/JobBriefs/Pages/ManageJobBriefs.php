<?php

namespace App\Filament\Resources\JobBriefs\Pages;

use App\Filament\Resources\JobBriefs\JobBriefResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageJobBriefs extends ManageRecords
{
    protected static string $resource = JobBriefResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
