<?php

namespace App\Providers\Sandbox;

use App\Contracts\ImageGenerator;
use App\Contracts\MarketingAI;
use App\Models\Campaign;
use App\Models\FreelancerProfile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

class SandboxMarketingAI implements MarketingAI
{
    public function __construct(private ImageGenerator $images) {}

    public function respond(Workspace $workspace, User $user, string $message): array
    {
        $campaigns = Campaign::where('workspace_id', $workspace->id)->where('status', 'active')->get()
            ->map(fn ($campaign) => $campaign->setAttribute('computed_roas', $campaign->spend > 0 ? $campaign->revenue / $campaign->spend : 0));
        $worst = $campaigns->sortBy('computed_roas')->first();
        $best = $campaigns->sortByDesc('computed_roas')->first();
        $q = Str::lower($message);
        $cards = [];
        $plan = null;
        $creative = null;

        if (Str::contains($q, ['wast', 'kill', 'pause', 'audit', 'leak'])) {
            if (! $worst) {
                return ['content' => 'There are no active campaigns to audit yet. Connect an account or create a campaign first.', 'cards' => []];
            }
            $roas = round($worst->computed_roas, 2);
            $bestName = $best && $best->id !== $worst->id ? $best->name : 'a controlled retargeting test';
            $content = "{$worst->name} is the weakest active campaign: $".number_format($worst->spend)." spend at {$roas}x ROAS and ".$this->ctr($worst)."% CTR. Reduce or pause it, then validate a smaller test before moving budget into {$bestName}.";
            $cards[] = ['kind' => 'campaign', 'title' => $worst->name, 'body' => "ROAS {$roas}x · Spend $".number_format($worst->spend)." · CTR ".$this->ctr($worst).'%', 'meta' => ['campaignId' => $worst->id, 'action' => 'Review budget'], 'href' => '/app/ads?campaign='.$worst->id];
            $this->addFreelancerCard($cards, $worst->platform, 'media_buying', 'A media buyer can rebuild this campaign inside your approval workflow.');
        } elseif (Str::contains($q, ['plan', '2-week', 'two week', 'calendar', 'schedule'])) {
            $content = 'I prepared a 14-day sandbox plan using eight staggered drafts. Review each item before scheduling; nothing has been published.';
            $channels = ['instagram', 'tiktok', 'facebook', 'instagram'];
            $plan = collect(range(0, 7))->map(fn ($i) => [
                'date' => now()->addDays($i * 2)->toDateString(),
                'time' => $i % 2 ? '18:00' : '10:00',
                'channel' => $channels[$i % 4],
                'kind' => $i % 3 === 0 ? 'ad' : 'organic',
                'title' => $i % 3 === 0 ? 'Returning visitor offer' : $workspace->name.' content beat '.($i + 1),
                'caption' => 'A useful product proof point, one clear call to action, and no unsupported claims.',
            ])->values()->all();
            $cards[] = ['kind' => 'plan', 'title' => '14-day draft plan', 'body' => '8 reviewable drafts across Instagram, Facebook, and TikTok.'];
        } elseif (Str::contains($q, ['image', 'creative', 'caption', 'copy', 'design'])) {
            $headline = 'A clearer reason to choose '.$workspace->name;
            $image = $this->images->generate($workspace->id, $headline, '4:5');
            $creative = ['title' => 'Sandbox concept · '.$workspace->name, 'caption' => 'Show the customer problem, demonstrate the product, and finish with one specific next step.', 'headline' => $headline, 'platform' => 'instagram', 'aspect_ratio' => '4:5', 'kind' => 'generated', 'path' => $image['path'], 'hue' => $image['hue']];
            $content = 'I created a sandbox SVG concept and editable caption. It is stored in your creative library; it is not a claim of external AI image generation.';
            $cards[] = ['kind' => 'creative', 'title' => $creative['title'], 'body' => $creative['caption']];
            $this->addFreelancerCard($cards, 'meta', 'graphic_design', 'A designer can turn this concept into a production-ready creative system.');
        } elseif (Str::contains($q, ['freelancer', 'hire', 'editor', 'designer', 'buyer'])) {
            $platform = $worst?->platform ?? 'meta';
            $skill = Str::contains($q, ['editor', 'designer']) ? 'video_editing' : 'media_buying';
            $content = 'I ranked approved freelancers by the requested skill, platform fit, rating, and completed work. Review their packages before hiring.';
            $this->addFreelancerCard($cards, $platform, $skill, 'Best available match for this workspace need.');
        } else {
            $spend = (float) $campaigns->sum('spend');
            $revenue = (float) $campaigns->sum('revenue');
            $clicks = (int) $campaigns->sum('clicks');
            $impressions = (int) $campaigns->sum('impressions');
            $blended = $spend > 0 ? round($revenue / $spend, 2) : 0;
            $content = $campaigns->isEmpty()
                ? 'Your workspace has no active campaign metrics yet. Connect a sandbox account or create a campaign, then ask for an audit or plan.'
                : "Across active campaigns: $".number_format($spend)." spend, $".number_format($revenue)." attributed revenue, and {$blended}x blended ROAS. Ask for a waste audit, creative, plan, or freelancer match.";
            $cards[] = ['kind' => 'metric', 'title' => 'Blended ROAS', 'body' => $blended.'x', 'meta' => ['spend' => '$'.number_format($spend), 'revenue' => '$'.number_format($revenue), 'ctr' => ($impressions ? round($clicks / $impressions * 100, 2) : 0).'%']];
        }

        return array_filter(['content' => $content, 'cards' => $cards, 'plan' => $plan, 'creative' => $creative], fn ($v) => $v !== null);
    }

    public function jobBrief(Workspace $workspace): array
    {
        $worst = Campaign::where('workspace_id', $workspace->id)->where('status', 'active')->get()
            ->sortBy(fn ($c) => $c->spend > 0 ? $c->revenue / $c->spend : 0)->first();
        if (! $worst) {
            return ['title' => 'Marketing account audit for '.$workspace->name, 'description' => 'Review the workspace, establish a measurement baseline, and propose a prioritized two-week testing plan. All campaign changes require owner approval.', 'skills' => ['media_buying'], 'platforms' => ['meta', 'tiktok'], 'budget' => 900];
        }
        $roas = $worst->spend > 0 ? round($worst->revenue / $worst->spend, 2) : 0;
        return ['title' => 'Improve '.$worst->platform.' performance for '.$workspace->name, 'description' => "Audit {$worst->name}, currently at {$roas}x ROAS on $".number_format($worst->spend)." spend. Document wasted spend, propose campaign and creative tests, and submit all changes through the owner approval queue.", 'skills' => ['media_buying'], 'platforms' => [$worst->platform], 'budget' => max(500, round($worst->daily_budget * 14))];
    }

    private function addFreelancerCard(array &$cards, string $platform, string $skill, string $reason): void
    {
        $freelancer = FreelancerProfile::where('vetting', 'approved')->where('public_visible', true)->get()
            ->filter(fn ($f) => in_array($skill, $f->skills ?? []) && in_array($platform, $f->platforms ?? []))
            ->sortByDesc(fn ($f) => [$f->rating, $f->completed_jobs])->first();
        if ($freelancer) {
            $cards[] = ['kind' => 'freelancer', 'title' => $freelancer->user->name, 'body' => $reason.' '.$freelancer->headline, 'freelancerId' => $freelancer->id, 'href' => '/app/marketplace/'.$freelancer->id];
        }
    }

    private function ctr(Campaign $campaign): float
    {
        return $campaign->impressions ? round($campaign->clicks / $campaign->impressions * 100, 2) : 0;
    }
}
