<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Services\ApprovalService;
use App\Support\ApiMapper;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function decide(Request $request, Approval $approval, ApprovalService $approvals)
    {
        $data = $request->validate(['decision' => 'required|in:approved,rejected']);
        return response()->json(['data' => ApiMapper::camel($approvals->decide($request->user(), $approval, $data['decision'])->toArray())]);
    }
}
