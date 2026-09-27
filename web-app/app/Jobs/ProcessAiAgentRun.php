<?php

namespace App\Jobs;

use App\Models\AiAgentRun;
use App\Services\AiAgentRuntime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class ProcessAiAgentRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 100;

    public function __construct(
        public int $runId,
        public string $encryptedQuestion,
    ) {}

    public function handle(AiAgentRuntime $runtime): void
    {
        $run = AiAgentRun::query()->with(['demand', 'requester'])->find($this->runId);
        if (! $run || $run->status !== 'queued') {
            return;
        }

        $run->update(['status' => 'running', 'started_at' => now()]);

        try {
            $result = $runtime->run($run->demand, $run->requester, Crypt::decryptString($this->encryptedQuestion), $run->agent);
            $run->update([
                'status' => 'completed',
                'answer' => $result['answer'],
                'tool_trace' => $result['tool_trace'],
                'input_tokens' => $result['input_tokens'],
                'output_tokens' => $result['output_tokens'],
                'provider_cost' => $result['provider_cost'],
                'cost_currency' => $result['provider_cost'] === null ? null : 'USD',
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            $run->update([
                'status' => 'failed',
                'error_message' => mb_substr($message !== '' ? $message : 'A execução falhou.', 0, 255),
                'completed_at' => now(),
            ]);
        } finally {
            unset($this->encryptedQuestion);
        }
    }

    public function failed(?Throwable $exception): void
    {
        AiAgentRun::query()->whereKey($this->runId)->whereIn('status', ['queued', 'running'])->update([
            'status' => 'failed',
            'error_message' => 'A execução foi interrompida antes de concluir.',
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
