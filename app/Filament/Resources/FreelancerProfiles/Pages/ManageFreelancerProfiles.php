<?php

namespace App\Filament\Resources\FreelancerProfiles\Pages;

use App\Filament\Resources\FreelancerProfiles\FreelancerProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFreelancerProfiles extends ManageRecords
{
    protected static string $resource = FreelancerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
