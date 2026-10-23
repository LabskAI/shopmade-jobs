<?php

namespace Shopmade\Jobs\Mcp;

use App\Core\Mcp\McpContext;
use App\Core\Mcp\Tools\Tool;
use App\Core\Mcp\Tools\ToolResult;
use App\Enums\ConnectorScope;
use Illuminate\Support\Carbon;
use Shopmade\Jobs\Enums\JobStatus;
use Shopmade\Jobs\Models\Job;

class JobsListTool extends Tool
{
    public function name(): string
    {
        return 'jobs_list';
    }

    public function title(): string
    {
        return 'List jobs';
    }

    public function description(): string
    {
        return 'Jobs on the schedule, soonest first, with customer, visit window, status and crew. Filter by status and by a date range on the scheduled start.';
    }

    public function scope(): ConnectorScope
    {
        return ConnectorScope::Operator;
    }

    public function inputSchema(): array
    {
        return $this->objectSchema([
            'status' => ['type' => 'string', 'enum' => array_map(fn (JobStatus $status): string => $status->value, JobStatus::cases())],
            'from' => ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$', 'description' => 'Earliest scheduled day, YYYY-MM-DD.'],
            'to' => ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$', 'description' => 'Latest scheduled day, YYYY-MM-DD.'],
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25],
        ]);
    }

    public function handle(array $arguments, McpContext $context): ToolResult
    {
        $jobs = Job::query()
            ->with(['customer', 'assignee'])
            ->when($arguments['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($arguments['from'] ?? null, fn ($query, string $from) => $query->where('scheduled_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($arguments['to'] ?? null, fn ($query, string $to) => $query->where('scheduled_at', '<=', Carbon::parse($to)->endOfDay()))
            ->orderByRaw('scheduled_at is null, scheduled_at asc')
            ->limit($arguments['limit'] ?? 25)
            ->get();

        return new ToolResult([
            'jobs' => $jobs->map(fn (Job $job): array => [
                'id' => $job->id,
                'customer' => $job->customer->name,
                'address' => $job->customer->address,
                'scheduled_at' => $job->scheduled_at?->toIso8601String(),
                'scheduled_end_at' => $job->scheduled_end_at?->toIso8601String(),
                'window' => $job->windowLabel(),
                'status' => $job->status->value,
                'assignee' => $job->assignee?->name,
                'url' => route('jobs.show', $job),
            ])->all(),
        ]);
    }
}
