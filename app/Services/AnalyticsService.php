<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\MetricPoint;
use App\Models\Workspace;
use Carbon\Carbon;

class AnalyticsService
{
    public function get(Workspace $workspace, array $filters): array
    {
        $to = isset($filters['to']) ? Carbon::parse($filters['to']) : now();
        $from = isset($filters['from']) ? Carbon::parse($filters['from']) : $to->copy()->subDays(13);
        $days = $from->diffInDays($to) + 1;
        $previousFrom = $from->copy()->subDays($days);
        $previousTo = $from->copy()->subDay();

        $campaignQuery = Campaign::where('workspace_id', $workspace->id);
        foreach (['platform', 'status', 'objective', 'connection_id'] as $key) {
            if (! empty($filters[$key]) && $filters[$key] !== 'all') $campaignQuery->where($key, $filters[$key]);
        }
        if (! empty($filters['q'])) $campaignQuery->where('name', 'like', '%'.addcslashes($filters['q'], '%_').'%');
        $campaigns = $campaignQuery->get();
        $ids = $campaigns->pluck('id');
        $points = MetricPoint::whereIn('campaign_id', $ids)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->get();
        $previous = MetricPoint::whereIn('campaign_id', $ids)->whereBetween('date', [$previousFrom->toDateString(), $previousTo->toDateString()])->get();

        $rows = $campaigns->map(function ($campaign) use ($points) {
            $metrics = $this->totals($points->where('campaign_id', $campaign->id));
            $row = $campaign->toArray();
            foreach (['spend', 'revenue', 'impressions', 'clicks', 'conversions'] as $key) $row[$key] = $metrics[$key];
            return array_merge($row, $this->derived($metrics), ['createdBy' => $campaign->created_by_role, 'createdById' => $campaign->created_by_id]);
        })->values();
        $totals = $this->totals($points);
        $previousTotals = $this->totals($previous);
        $series = $points->groupBy(fn ($p) => $p->date->toDateString())->map(function ($group, $date) {
            return array_merge(['date' => $date], $this->totals($group));
        })->sortBy('date')->values();

        return [
            'campaigns' => $rows,
            'series' => $series,
            'kpis' => array_merge($totals, $this->derived($totals), ['previous' => array_merge($previousTotals, $this->derived($previousTotals))]),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'previousFrom' => $previousFrom->toDateString(), 'previousTo' => $previousTo->toDateString()],
        ];
    }

    public function totals($points): array
    {
        return [
            'spend' => (float) $points->sum('spend'), 'revenue' => (float) $points->sum('revenue'),
            'impressions' => (int) $points->sum('impressions'), 'clicks' => (int) $points->sum('clicks'),
            'conversions' => (int) $points->sum('conversions'),
        ];
    }

    public function derived(array $metrics): array
    {
        return [
            'roas' => $metrics['spend'] > 0 ? round($metrics['revenue'] / $metrics['spend'], 2) : 0,
            'ctr' => $metrics['impressions'] > 0 ? round($metrics['clicks'] / $metrics['impressions'] * 100, 2) : 0,
            'cpc' => $metrics['clicks'] > 0 ? round($metrics['spend'] / $metrics['clicks'], 2) : 0,
            'cpm' => $metrics['impressions'] > 0 ? round($metrics['spend'] / $metrics['impressions'] * 1000, 2) : 0,
        ];
    }
}
