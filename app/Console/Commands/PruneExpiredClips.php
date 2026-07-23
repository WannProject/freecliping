<?php

namespace App\Console\Commands;

use App\Enums\ClipAnalysisStatus;
use App\Enums\ClipStatus;
use App\Models\Clip;
use App\Models\ClipAnalysis;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

#[Signature('clips:prune')]
#[Description('Delete expired output files and prune stale clip or analysis records.')]
class PruneExpiredClips extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subHours((int) config('freekliping.prune_after_hours', 24));
        $analysisCutoff = now()->subHours((int) config('freekliping.analysis_retention_hours', 24));

        $filesRemoved = $this->deleteExpiredOutputFiles();
        $recordsPruned = $this->pruneStaleRecords($cutoff);
        $analysesPruned = $this->pruneStaleAnalyses($analysisCutoff);

        Log::info('pruned expired clips', [
            'analyses_pruned' => $analysesPruned,
            'files_removed' => $filesRemoved,
            'records_pruned' => $recordsPruned,
        ]);

        $this->info("Removed {$filesRemoved} expired output file(s), pruned {$recordsPruned} stale clip record(s), and pruned {$analysesPruned} stale analysis record(s).");

        return self::SUCCESS;
    }

    private function deleteExpiredOutputFiles(): int
    {
        $expired = Clip::query()
            ->where('status', ClipStatus::Completed)
            ->whereNotNull('output_path')
            ->where('output_expires_at', '<', now())
            ->get();

        foreach ($expired as $clip) {
            if (is_string($clip->output_disk) && Storage::disk($clip->output_disk)->exists($clip->output_path)) {
                Storage::disk($clip->output_disk)->delete($clip->output_path);
            }

            $clip->forceFill([
                'output_path' => null,
                'output_disk' => null,
            ])->save();
        }

        return $expired->count();
    }

    private function pruneStaleRecords($cutoff): int
    {
        return Clip::query()
            ->where(function ($query) use ($cutoff): void {
                $query->where(function ($query) use ($cutoff): void {
                    $query->where('status', ClipStatus::Completed)
                        ->where('output_expires_at', '<', $cutoff);
                })->orWhere(function ($query) use ($cutoff): void {
                    $query->where('status', ClipStatus::Failed)
                        ->where('updated_at', '<', $cutoff);
                });
            })
            ->delete();
    }

    private function pruneStaleAnalyses($cutoff): int
    {
        return ClipAnalysis::query()
            ->whereNotIn('status', [
                ClipAnalysisStatus::Queued->value,
                ClipAnalysisStatus::Processing->value,
            ])
            ->where('updated_at', '<', $cutoff)
            ->delete();
    }
}
