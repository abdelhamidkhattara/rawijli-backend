<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Workspace;
use App\Services\AnalyticsService;
use App\Services\WorkspaceAccess;
use App\Support\ApiMapper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['data' => ApiMapper::camel(
            Report::where('user_id', $request->user()->id)->latest()->limit(50)->get()->toArray()
        )]);
    }

    public function store(Request $request, WorkspaceAccess $access, AnalyticsService $analytics)
    {
        $data = $request->validate([
            'workspaceId' => ['required', 'uuid'],
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
            'format' => ['nullable', Rule::in(['csv', 'pdf'])],
        ]);
        $workspace = $access->workspace($request->user(), $data['workspaceId'], 'view_analytics');
        $result = $analytics->get($workspace, ['from' => $data['dateFrom'], 'to' => $data['dateTo']]);
        $report = Report::create([
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->id,
            'date_from' => $data['dateFrom'],
            'date_to' => $data['dateTo'],
            'status' => 'ready',
            'payload' => ['format' => $data['format'] ?? 'csv', 'workspace' => $workspace->name, 'analytics' => $result],
        ]);
        return response()->json(['data' => ApiMapper::camel($report->toArray())], 201);
    }

    public function download(Request $request, Report $report)
    {
        abort_unless($report->user_id === $request->user()->id || $request->user()->isAdmin() || $report->workspace_id === $request->user()->workspace?->id, 403);
        $payload = $report->payload;
        $format = $payload['format'] ?? 'csv';
        $name = 'rawijli-report-'.$report->date_from->toDateString().'-'.$report->date_to->toDateString();

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.performance', ['report' => $report, 'payload' => $payload]);
            return $pdf->download($name.'.pdf');
        }

        return $this->csv($report, $payload, $name.'.csv');
    }

    private function csv(Report $report, array $payload, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($payload) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Campaign', 'Platform', 'Status', 'Objective', 'Spend', 'Revenue', 'ROAS', 'CTR', 'CPC', 'CPM', 'Impressions', 'Clicks', 'Conversions']);
            foreach ($payload['analytics']['campaigns'] as $c) {
                fputcsv($out, [$c['name'], $c['platform'], $c['status'], $c['objective'], $c['spend'], $c['revenue'], $c['roas'], $c['ctr'], $c['cpc'], $c['cpm'], $c['impressions'], $c['clicks'], $c['conversions']]);
            }
            fputcsv($out, []);
            $k = $payload['analytics']['kpis'];
            fputcsv($out, ['Totals', '', '', '', $k['spend'], $k['revenue'], $k['roas'], $k['ctr'], $k['cpc'], $k['cpm'], $k['impressions'], $k['clicks'], $k['conversions']]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
