<?php

namespace HoudaSlassi\Vantage;

use HoudaSlassi\Vantage\Models\VantageJob;
use HoudaSlassi\Vantage\Support\QueueDepthChecker;
use HoudaSlassi\Vantage\Support\VantageLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Vantage
{
    /**
     * Get the queue depth for all or specific queues.
     */
    public function queueDepth(?string $queue = null): Collection
    {
        return app(QueueDepthChecker::class)->check($queue);
    }

    /**
     * Get jobs with a specific status.
     */
    public function jobsByStatus(string $status, int $limit = 50): Collection
    {
        return VantageJob::where('status', $status)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get failed jobs.
     */
    public function failedJobs(int $limit = 50): Collection
    {
        return $this->jobsByStatus('failed', $limit);
    }

    /**
     * Get processing jobs.
     */
    public function processingJobs(int $limit = 50): Collection
    {
        return $this->jobsByStatus('processing', $limit);
    }

    /**
     * Get jobs by tag.
     */
    public function jobsByTag(string $tag, int $limit = 50): Collection
    {
        return VantageJob::withTag($tag)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get statistics for the dashboard.
     */
    public function statistics(?string $startDate = null): array
    {
        $query = VantageJob::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        $stats = $query->select(
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN status = "processed" THEN 1 ELSE 0 END) as processed'),
            DB::raw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed'),
            DB::raw('SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) as processing')
        )->first();

        $successRate = $stats->total > 0
            ? round(($stats->processed / ($stats->processed + $stats->failed)) * 100, 2)
            : 0;

        return [
            'total' => $stats->total ?? 0,
            'processed' => $stats->processed ?? 0,
            'failed' => $stats->failed ?? 0,
            'processing' => $stats->processing ?? 0,
            'success_rate' => $successRate,
        ];
    }

    /**
     * Retry a failed job.
     */
    public function retryJob(int $jobId): bool
    {
        $job = VantageJob::find($jobId);

        if (! $job || $job->status !== 'failed') {
            return false;
        }

        // Re-dispatch the job
        if ($job->payload && isset($job->payload['data']['command'])) {
            $command = unserialize($job->payload['data']['command']);
            dispatch($command)->onQueue($job->queue);

            return true;
        }

        return false;
    }

    /**
     * Clean up stuck processing jobs.
     */
    public function cleanupStuckJobs(int $hoursOld = 24): int
    {
        return VantageJob::where('status', 'processing')
            ->where('started_at', '<', now()->subHours($hoursOld))
            ->update(['status' => 'failed', 'exception_class' => 'Timeout']);
    }

    /**
     * Prune old jobs.
     */
    public function pruneOldJobs(int $daysOld = 30): int
    {
        return VantageJob::where('created_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    /**
     * Get the VantageLogger instance.
     */
    public function logger(): VantageLogger
    {
        return app(VantageLogger::class);
    }

    /**
     * Enable Vantage.
     */
    public function enable(): void
    {
        config(['vantage.enabled' => true]);
    }

    /**
     * Disable Vantage.
     */
    public function disable(): void
    {
        config(['vantage.enabled' => false]);
    }

    /**
     * Check if Vantage is enabled.
     */
    public function enabled(): bool
    {
        return config('vantage.enabled', true);
    }
}
