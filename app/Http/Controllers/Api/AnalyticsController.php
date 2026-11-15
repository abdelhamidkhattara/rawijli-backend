<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\AnalyticsService;
use App\Services\WorkspaceAccess;
use App\Support\ApiMapper;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function index(Request $request, Workspace $workspace, WorkspaceAccess $access, AnalyticsService $analytics)
    {
        $access->workspace($request->user(), $workspace->id, 'view_analytics');
        $filters = $this->filters($request);
        return response()->json(['data' => ApiMapper::camel($analytics->get($workspace, $filters))]);
    }

    public function export(Request $request, Workspace $workspace, WorkspaceAccess $access, AnalyticsService $analytics): StreamedResponse
    {
        $access->workspace($request->user(), $workspace->id, 'view_analytics');
        $result = $analytics->get($workspace, $this->filters($request));
        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Campaign', 'Platform', 'Status', 'Objective', 'Spend', 'Revenue', 'ROAS', 'CTR', 'CPC', 'CPM', 'Impressions', 'Clicks', 'Conversions']);
            foreach ($result['campaigns'] as $c) fputcsv($out, [$c['name'], $c['platform'], $c['status'], $c['objective'], $c['spend'], $c['revenue'], $c['roas'], $c['ctr'], $c['cpc'], $c['cpm'], $c['impressions'], $c['clicks'], $c['conversions']]);
            fclose($out);
        }, 'rawijli-analytics-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }

    private function filters(Request $request): array
    {
        return $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'platform' => 'nullable|in:all,meta,tiktok', 'status' => 'nullable|string|max:40', 'objective' => 'nullable|string|max:80', 'connection_id' => 'nullable|uuid', 'q' => 'nullable|string|max:160']);
    }
}
