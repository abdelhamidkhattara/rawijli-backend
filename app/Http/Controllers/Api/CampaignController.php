<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CampaignRequest;
use App\Models\Campaign;
use App\Services\CampaignService;
use App\Services\WorkspaceAccess;
use App\Support\ApiMapper;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function store(CampaignRequest $request, CampaignService $campaigns)
    {
        $campaign = $campaigns->create($request->user(), $request->validated());
        return response()->json(['data' => ApiMapper::camel($campaign->toArray())], 201);
    }

    public function update(CampaignRequest $request, Campaign $campaign, CampaignService $campaigns)
    {
        return response()->json(['data' => ApiMapper::camel($campaigns->update($request->user(), $campaign, $request->validated())->toArray())]);
    }

    public function status(Request $request, Campaign $campaign, CampaignService $campaigns)
    {
        $data = $request->validate(['status' => 'required|in:active,paused,archived']);
        return response()->json(['data' => ApiMapper::camel($campaigns->changeStatus($request->user(), $campaign, $data['status'])->toArray())]);
    }

    public function duplicate(Request $request, Campaign $campaign, CampaignService $campaigns)
    {
        $campaign->load('adSets.ads');
        $data = ['workspaceId' => $campaign->workspace_id, 'connectionId' => $campaign->connection_id, 'name' => $campaign->name.' (Copy)', 'platform' => $campaign->platform, 'objective' => $campaign->objective, 'dailyBudget' => $campaign->daily_budget, 'lifetimeBudget' => $campaign->lifetime_budget, 'startsAt' => $campaign->starts_at, 'endsAt' => $campaign->ends_at, 'publish' => false, 'adSets' => $campaign->adSets->map(fn ($s) => ['name' => $s->name, 'targeting' => $s->targeting, 'dailyBudget' => $s->daily_budget, 'ads' => $s->ads->map(fn ($a) => ['name' => $a->name, 'format' => $a->format, 'headline' => $a->headline, 'primaryText' => $a->primary_text, 'destinationUrl' => $a->destination_url, 'creativeId' => $a->creative_id])->all()])->all()];
        return response()->json(['data' => ApiMapper::camel($campaigns->create($request->user(), $data)->toArray())], 201);
    }

    public function budget(Request $request, Campaign $campaign, CampaignService $campaigns)
    {
        $data = $request->validate(['dailyBudget' => 'required|numeric|min:1|max:10000000', 'reason' => 'required|string|max:2000']);
        $approval = $campaigns->budgetRequest($request->user(), $campaign, (float) $data['dailyBudget'], $data['reason']);
        return response()->json(['data' => ApiMapper::camel($approval->toArray())], 201);
    }
}
