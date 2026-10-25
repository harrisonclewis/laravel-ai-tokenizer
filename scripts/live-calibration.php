<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';

$key = getenv('OPENROUTER_API_KEY');

if ($key === false || trim($key) === '') {
    $keyFile = getenv('TOKENIZER_LIVE_KEY_FILE');

    if ($keyFile !== false && $keyFile !== '') {
        if (! is_readable($keyFile)) {
            fwrite(STDERR, "OpenRouter key file is not readable: {$keyFile}\n");
            exit(2);
        }

        $key = trim((string) file_get_contents($keyFile));
    }
}

if ($key === false || trim($key) === '') {
    fwrite(STDERR, "Set OPENROUTER_API_KEY or TOKENIZER_LIVE_KEY_FILE before running live calibration.\n");
    exit(2);
}

$report = getenv('TOKENIZER_LIVE_REPORT') ?: sys_get_temp_dir().'/laravel-ai-tokenizer-'.date('Ymd-His').'-'.getmypid().'.jsonl';

if (file_put_contents($report, '') === false) {
    fwrite(STDERR, "Could not create calibration report: {$report}\n");
    exit(2);
}

$model = getenv('TOKENIZER_LIVE_MODEL') ?: 'openai/gpt-4o-mini';
$process = new Process(
    [PHP_BINARY, __DIR__.'/../vendor/bin/pest', '--group=live', ...array_slice($argv, 1)],
    dirname(__DIR__),
    [
        'OPENROUTER_API_KEY' => $key,
        'TOKENIZER_RUN_LIVE_TESTS' => 'true',
        'TOKENIZER_LIVE_MODEL' => $model,
        'TOKENIZER_LIVE_REPORT' => $report,
    ],
);
$process->setTimeout(300);

fwrite(STDOUT, "Calibrating OpenRouter model {$model}\n");

$process->run(static function (string $type, string $buffer): void {
    fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
});

fwrite(STDOUT, "Calibration report: {$report}\n");

exit($process->getExitCode() ?? 1);
