<?php

namespace App\Providers\Live;

use App\Contracts\ImageGenerator;
use App\Contracts\MarketingAI;
use App\Models\Campaign;
use App\Models\FreelancerProfile;
use App\Models\User;
use App\Models\Workspace;
use App\Providers\Sandbox\SandboxMarketingAI;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeepSeekMarketingAI implements MarketingAI
{
    public function __construct(
        private SandboxMarketingAI $fallback,
        private ImageGenerator $images,
    ) {}

    public function respond(Workspace $workspace, User $user, string $message): array
    {
        if (! $this->configured()) {
            return $this->fallback->respond($workspace, $user, $message);
        }

        try {
            $decoded = $this->askJson($this->systemPrompt(), $this->workspacePrompt($workspace, $message));
            if (! is_array($decoded) || empty($decoded['content'])) {
                throw new \RuntimeException('DeepSeek returned an empty copilot response.');
            }

            $result = [
                'content' => (string) $decoded['content'],
                'cards' => $this->cards($decoded['cards'] ?? [], $workspace, $message),
            ];

            if (! empty($decoded['plan']) && is_array($decoded['plan'])) {
                $result['plan'] = $this->plan($decoded['plan']);
            }

            if (! empty($decoded['creative']) && is_array($decoded['creative'])) {
                $result['creative'] = $this->creative($decoded['creative'], $workspace);
            }

            if (! empty($result['cards']) && collect($result['cards'])->contains(fn ($c) => ($c['kind'] ?? null) === 'freelancer')) {
                $result['cards'] = collect($result['cards'])->reject(fn ($c) => ($c['kind'] ?? null) === 'freelancer')->values()->all();
            }
            $this->appendFreelancerCard($result['cards'], $message, $workspace);

            return array_filter($result, fn ($value) => $value !== null && $value !== []);
        } catch (\Throwable $e) {
            Log::warning('DeepSeek copilot fallback', ['error' => $e->getMessage()]);
            return $this->fallback->respond($workspace, $user, $message);
        }
    }

    public function jobBrief(Workspace $workspace): array
    {
        if (! $this->configured()) {
            return $this->fallback->jobBrief($workspace);
        }

        try {
            $decoded = $this->askJson(
                'You write concise freelance job briefs for a marketing workspace. Return strict JSON only.',
                $this->workspacePrompt($workspace, 'Write a job brief to fix the weakest performing campaigns. Return {"title","description","skills","platforms","budget"}. skills must be one or more of media_buying, graphic_design, video_editing, copywriting. platforms must be one or more of meta, tiktok. budget must be a number in USD.'),
            );
            if (! is_array($decoded) || empty($decoded['title']) || empty($decoded['description'])) {
                throw new \RuntimeException('DeepSeek returned an incomplete job brief.');
            }

            $skills = collect($decoded['skills'] ?? ['media_buying'])
                ->filter(fn ($s) => in_array($s, ['media_buying', 'graphic_design', 'video_editing', 'copywriting'], true))
                ->values()->all() ?: ['media_buying'];
            $platforms = collect($decoded['platforms'] ?? ['meta', 'tiktok'])
                ->filter(fn ($p) => in_array($p, ['meta', 'tiktok'], true))
                ->values()->all() ?: ['meta', 'tiktok'];

            return [
                'title' => (string) $decoded['title'],
                'description' => (string) $decoded['description'],
                'skills' => $skills,
                'platforms' => $platforms,
                'budget' => max(100, (float) ($decoded['budget'] ?? 900)),
            ];
        } catch (\Throwable $e) {
            Log::warning('DeepSeek job brief fallback', ['error' => $e->getMessage()]);
            return $this->fallback->jobBrief($workspace);
        }
    }

    private function configured(): bool
    {
        return filled(config('services.deepseek.key'));
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are Rawijli, a senior performance-marketing copilot embedded in a client workspace.
Ground every answer in the supplied facts. Never invent campaign ids, spend, or results.
Return STRICT JSON with this shape:
{"content":"short answer, max 120 words","cards":[{"kind":"metric|campaign|plan|creative","title":"...","body":"...","meta":{"label":"value"},"href":"/app/ads?campaign=..."}],"plan":[{"date":"YYYY-MM-DD","time":"HH:MM","channel":"instagram|facebook|tiktok","kind":"organic|ad","title":"...","caption":"..."}],"creative":{"title":"...","caption":"...","headline":"...","platform":"instagram|facebook|tiktok","aspect_ratio":"4:5|1:1|9:16"}}
Only include plan when the user asks for a calendar/plan. Only include creative when the user asks for a concept/copy.
Do not return markdown, comments, or extra keys.
PROMPT;
    }

    private function workspacePrompt(Workspace $workspace, string $message): string
    {
        $campaigns = Campaign::where('workspace_id', $workspace->id)
            ->orderByDesc('spend')
            ->limit(30)
            ->get()
            ->map(fn (Campaign $campaign) => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'platform' => $campaign->platform,
                'status' => $campaign->status,
                'objective' => $campaign->objective,
                'daily_budget' => (float) $campaign->daily_budget,
                'spend' => (float) $campaign->spend,
                'revenue' => (float) $campaign->revenue,
                'roas' => $campaign->spend > 0 ? round($campaign->revenue / $campaign->spend, 2) : 0,
                'ctr' => $campaign->impressions > 0 ? round($campaign->clicks / $campaign->impressions * 100, 2) : 0,
            ]);

        $facts = [
            'workspace' => ['name' => $workspace->name, 'industry' => $workspace->industry, 'currency' => $workspace->currency, 'timezone' => $workspace->timezone],
            'today' => now()->toDateString(),
            'campaigns' => $campaigns,
        ];

        return "Workspace facts:\n".json_encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n\nUser request:\n".$message;
    }

    private function askJson(string $system, string $user): array
    {
        $base = rtrim((string) config('services.deepseek.base_url', 'https://api.deepseek.com'), '/');
        $payload = [
            'model' => config('services.deepseek.model', 'deepseek-chat'),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => 0.3,
            'max_tokens' => 1200,
            'response_format' => ['type' => 'json_object'],
        ];

        $request = Http::withToken((string) config('services.deepseek.key'))
            ->acceptJson()
            ->timeout((int) config('services.deepseek.timeout', 45));

        $response = $request->post($base.'/chat/completions', $payload);
        if ($response->status() === 400) {
            unset($payload['response_format']);
            $response = $request->post($base.'/chat/completions', $payload);
        }
        $response->throw();

        $content = (string) data_get($response->json(), 'choices.0.message.content', '');
        $content = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $content) ?? $content);
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            $start = strpos($content, '{');
            $end = strrpos($content, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $decoded = json_decode(substr($content, $start, $end - $start + 1), true);
            }
        }

        if (! is_array($decoded)) {
            throw new \RuntimeException('DeepSeek did not return valid JSON.');
        }

        return $decoded;
    }

    private function cards(array $cards, Workspace $workspace, string $message): array
    {
        $campaigns = Campaign::where('workspace_id', $workspace->id)->get(['id', 'name', 'platform', 'status', 'spend', 'revenue']);
        $allowed = ['metric', 'campaign', 'plan', 'creative', 'freelancer'];

        return collect($cards)
            ->filter(fn ($card) => is_array($card) && in_array($card['kind'] ?? null, $allowed, true) && ! empty($card['title']))
            ->map(function (array $card) use ($campaigns) {
                $kind = $card['kind'];
                $title = (string) $card['title'];
                if ($kind === 'campaign') {
                    $campaign = $campaigns->firstWhere('name', $title) ?? $campaigns->firstWhere('id', $card['meta']['campaignId'] ?? null);
                    if ($campaign) {
                        $card['href'] = $card['href'] ?? '/app/ads?campaign='.$campaign->id;
                        $card['meta'] = array_merge((array) ($card['meta'] ?? []), ['campaignId' => $campaign->id]);
                    }
                }
                $card['body'] = (string) ($card['body'] ?? '');
                $card['meta'] = collect((array) ($card['meta'] ?? []))->mapWithKeys(fn ($v, $k) => [(string) $k => is_scalar($v) ? (string) $v : json_encode($v)])->all();
                unset($card['freelancerId']);
                return $card;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function plan(array $plan): array
    {
        $allowedChannels = ['instagram', 'facebook', 'tiktok'];

        return collect($plan)
            ->filter(fn ($item) => is_array($item) && ! empty($item['title']))
            ->take(30)
            ->map(function (array $item) use ($allowedChannels) {
                $channel = in_array($item['channel'] ?? null, $allowedChannels, true) ? $item['channel'] : 'instagram';
                $date = $this->safeDate($item['date'] ?? null);
                return [
                    'date' => $date,
                    'time' => preg_match('/^\d{2}:\d{2}$/', (string) ($item['time'] ?? '')) ? $item['time'] : '10:00',
                    'channel' => $channel,
                    'kind' => ($item['kind'] ?? null) === 'ad' ? 'ad' : 'organic',
                    'title' => (string) $item['title'],
                    'caption' => (string) ($item['caption'] ?? ''),
                ];
            })
            ->values()->all();
    }

    private function creative(array $creative, Workspace $workspace): array
    {
        $headline = (string) ($creative['headline'] ?? 'A clearer reason to choose '.$workspace->name);
        $aspect = in_array($creative['aspect_ratio'] ?? null, ['4:5', '1:1', '9:16'], true) ? $creative['aspect_ratio'] : '4:5';
        $platform = in_array($creative['platform'] ?? null, ['instagram', 'facebook', 'tiktok'], true) ? $creative['platform'] : 'instagram';
        $image = $this->images->generate($workspace->id, $headline, $aspect);

        return [
            'title' => (string) ($creative['title'] ?? 'AI concept · '.$workspace->name),
            'caption' => (string) ($creative['caption'] ?? ''),
            'headline' => $headline,
            'platform' => $platform,
            'aspect_ratio' => $aspect,
            'kind' => 'generated',
            'path' => $image['path'] ?? null,
            'hue' => (int) ($image['hue'] ?? 280),
        ];
    }

    private function appendFreelancerCard(array &$cards, string $message, Workspace $workspace): void
    {
        $q = Str::lower($message);
        if (! Str::contains($q, ['freelancer', 'hire', 'editor', 'designer', 'buyer', 'copywriter'])) {
            return;
        }

        $skill = match (true) {
            Str::contains($q, ['editor', 'video']) => 'video_editing',
            Str::contains($q, ['designer', 'design']) => 'graphic_design',
            Str::contains($q, ['copywriter', 'copy']) => 'copywriting',
            default => 'media_buying',
        };
        $platform = Str::contains($q, 'tiktok') ? 'tiktok' : 'meta';

        $freelancer = FreelancerProfile::where('vetting', 'approved')->where('public_visible', true)->get()
            ->filter(fn (FreelancerProfile $f) => in_array($skill, $f->skills ?? [], true) && in_array($platform, $f->platforms ?? [], true))
            ->sortByDesc(fn (FreelancerProfile $f) => [$f->rating, $f->completed_jobs])
            ->first();

        if (! $freelancer) {
            return;
        }

        $cards[] = [
            'kind' => 'freelancer',
            'title' => $freelancer->user->name,
            'body' => $freelancer->headline,
            'freelancerId' => $freelancer->id,
            'meta' => ['rating' => (string) $freelancer->rating, 'platform' => $platform],
            'href' => '/app/marketplace/'.$freelancer->id,
        ];
    }

    private function safeDate(?string $date): string
    {
        if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        return now()->addDay()->toDateString();
    }
}
