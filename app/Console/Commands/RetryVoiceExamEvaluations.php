<?php

namespace App\Console\Commands;

use App\Jobs\EvaluateExamTranscript;
use App\Models\ExamTranscript;
use Illuminate\Console\Command;

class RetryVoiceExamEvaluations extends Command
{
    protected $signature = 'voice-exam:retry
                            {--status=failed : Which transcripts to retry (failed, unmatched, pending)}
                            {--limit=100 : Maximum number to process}';

    protected $description = 'Re-run AI evaluation for voice tests that did not complete';

    public function handle(): int
    {
        $status = (string) $this->option('status');

        $transcripts = ExamTranscript::query()
            ->where('status', $status)
            ->limit((int) $this->option('limit'))
            ->get();

        if ($transcripts->isEmpty()) {
            $this->info("No transcripts with status [{$status}] to retry.");

            return self::SUCCESS;
        }

        $this->info("Retrying {$transcripts->count()} transcript(s) with status [{$status}]…");

        $outcomes = [];

        foreach ($transcripts as $transcript) {
            $transcript->update([
                'status' => ExamTranscript::STATUS_PENDING,
                'failure_reason' => null,
            ]);

            dispatch_sync(new EvaluateExamTranscript($transcript));

            $result = $transcript->refresh()->status;
            $outcomes[$result] = ($outcomes[$result] ?? 0) + 1;

            $this->line(sprintf(
                '  #%d %s%s',
                $transcript->id,
                $result,
                $transcript->failure_reason ? ' — '.$transcript->failure_reason : ''
            ));
        }

        $this->newLine();

        foreach ($outcomes as $outcome => $count) {
            $this->line("{$outcome}: {$count}");
        }

        return self::SUCCESS;
    }
}
