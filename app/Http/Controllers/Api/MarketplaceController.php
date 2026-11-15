<?php

namespace App\Http\Controllers\Api;

use App\Contracts\MarketingAI;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobRequest;
use App\Models\Invitation;
use App\Models\JobBrief;
use App\Models\Milestone;
use App\Models\Proposal;
use App\Services\MarketplaceService;
use App\Services\WorkspaceAccess;
use App\Support\ApiMapper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplaceController extends Controller
{
    public function aiBrief(Request $request, WorkspaceAccess $access, MarketingAI $ai)
    {
        $data = $request->validate(['workspaceId' => 'required|uuid']);
        $workspace = $access->ownedWorkspace($request->user(), $data['workspaceId']);
        return response()->json(['data' => ApiMapper::camel($ai->jobBrief($workspace))]);
    }

    public function storeJob(JobRequest $request, MarketplaceService $marketplace)
    {
        return response()->json(['data' => ApiMapper::camel($marketplace->postJob($request->user(), $request->validated())->toArray())], 201);
    }

    public function updateJob(JobRequest $request, JobBrief $job, MarketplaceService $marketplace)
    {
        return response()->json(['data' => ApiMapper::camel($marketplace->postJob($request->user(), array_merge($request->validated(), ['workspaceId' => $job->workspace_id]), $job->id)->toArray())]);
    }

    public function jobStatus(Request $request, JobBrief $job, MarketplaceService $marketplace)
    {
        $data = $request->validate(['status' => 'required|in:open,closed']);
        return response()->json(['data' => ApiMapper::camel($marketplace->jobStatus($request->user(), $job, $data['status'])->toArray())]);
    }

    public function propose(Request $request, JobBrief $job, MarketplaceService $marketplace)
    {
        $data = $request->validate(['cover' => 'required|string|max:10000', 'bid' => 'required|numeric|min:1', 'days' => 'required|integer|min:1|max:365', 'attachments' => 'nullable|array']);
        return response()->json(['data' => ApiMapper::camel($marketplace->propose($request->user(), $job, $data)->toArray())], 201);
    }

    public function proposalStatus(Request $request, Proposal $proposal, MarketplaceService $marketplace)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['shortlisted', 'declined', 'withdrawn'])]]);
        return response()->json(['data' => ApiMapper::camel($marketplace->proposalStatus($request->user(), $proposal, $data['status'])->toArray())]);
    }

    public function hire(Request $request, MarketplaceService $marketplace)
    {
        $data = $request->validate(['workspaceId' => 'required|uuid', 'jobId' => 'nullable|uuid', 'freelancerId' => 'required|uuid', 'title' => 'required|string|max:190', 'amount' => 'required|numeric|min:1', 'terms' => 'nullable|string|max:10000', 'milestones' => 'required|array|min:1', 'milestones.*.title' => 'required|string|max:190', 'milestones.*.amount' => 'required|numeric|min:0.01', 'milestones.*.dueDate' => 'nullable|date']);
        abort_if(abs(collect($data['milestones'])->sum('amount') - (float) $data['amount']) > 0.01, 422, 'Milestone amounts must equal the contract amount.');
        return response()->json(['data' => ApiMapper::camel($marketplace->hire($request->user(), $data)->toArray())], 201);
    }

    public function invite(Request $request, WorkspaceAccess $access)
    {
        $data = $request->validate(['workspaceId' => 'required|uuid', 'freelancerId' => 'required|uuid', 'jobId' => 'nullable|uuid', 'reason' => 'required|string|max:3000']);
        $workspace = $access->ownedWorkspace($request->user(), $data['workspaceId']);
        if (! empty($data['jobId'])) abort_if(! JobBrief::where('workspace_id', $workspace->id)->whereKey($data['jobId'])->exists(), 422);
        $invite = Invitation::create(['workspace_id' => $workspace->id, 'freelancer_id' => $data['freelancerId'], 'job_id' => $data['jobId'] ?? null, 'reason' => $data['reason'], 'status' => 'open']);
        return response()->json(['data' => ApiMapper::camel($invite->toArray())], 201);
    }

    public function respondInvitation(Request $request, Invitation $invitation, MarketplaceService $marketplace)
    {
        $data = $request->validate(['accept' => 'required|boolean']);
        return response()->json(['data' => ApiMapper::camel($marketplace->respondInvitation($request->user(), $invitation, $data['accept'])->toArray())]);
    }

    public function milestone(Request $request, Milestone $milestone, MarketplaceService $marketplace)
    {
        $data = $request->validate(['action' => 'required|in:submit,approve,pay']);
        return response()->json(['data' => ApiMapper::camel($marketplace->milestone($request->user(), $milestone, $data['action'])->toArray())]);
    }
}
