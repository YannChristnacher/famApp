<?php

namespace App\Services\Admin;

use App\Support\SecretMasker;
use App\Support\TruncatedText;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Latest errors of the Laravel log for the admin control page. Reads only
 * the end of the most recently written laravel*.log file (single or daily
 * channel), keeps the entries of level error or worse, and only the message
 * of each: the context, the trace and the following lines are dropped, then
 * the secrets are masked and the message truncated. Never fails: a missing,
 * unreadable or rotated file gives an empty list; symbolic links are ignored.
 */
class ErrorLogReader
{
    private const ENTRY = '/^\[(?<date>\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:?\d{2}|Z)?)\]\s+[\w.-]+\.(?<level>ERROR|CRITICAL|ALERT|EMERGENCY):\s?(?<message>.*)$/';

    /** Monolog writes the context and extra data after the message: `{"…"}`, `["…"]` or `[]`. */
    private const CONTEXT_STARTS = [' {"', ' ["'];

    private const EMPTY_CONTEXT = '/(\s\[\])+\s*$/';

    /**
     * @return list<ErrorLogEntry>
     */
    public function latest(int $limit): array
    {
        try {
            $path = $this->latestLogFile();
            $lines = $path === null ? [] : $this->tailLines($path);
        } catch (Throwable) {
            // Deleted or rotated between two calls, unreadable: nothing to show.
            return [];
        }

        $entries = [];

        foreach (array_reverse($lines) as $line) {
            $entry = $this->parse($line);

            if ($entry !== null) {
                $entries[] = $entry;
            }

            if (count($entries) === $limit) {
                break;
            }
        }

        return $entries;
    }

    private function latestLogFile(): ?string
    {
        $files = glob(config()->string('famapp.control.log_directory').'/laravel*.log') ?: [];
        $files = array_filter($files, fn (string $file) => is_file($file) && ! is_link($file) && is_readable($file));

        if ($files === []) {
            return null;
        }

        usort($files, fn (string $a, string $b) => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

        return $files[0];
    }

    /**
     * Complete lines of the end of the file, at most log_tail_bytes.
     *
     * @return list<string>
     */
    private function tailLines(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        try {
            $size = fstat($handle)['size'] ?? 0;
            $length = min($size, config()->integer('famapp.control.log_tail_bytes'));

            if ($length <= 0) {
                return [];
            }

            fseek($handle, $size - $length);
            $content = (string) fread($handle, $length);
        } finally {
            fclose($handle);
        }

        $lines = preg_split('/\r?\n/', $content) ?: [];

        if ($length < $size) {
            // The first line was cut by the bounded read.
            array_shift($lines);
        }

        return $lines;
    }

    private function parse(string $line): ?ErrorLogEntry
    {
        if (preg_match(self::ENTRY, $line, $matches) !== 1) {
            return null;
        }

        return new ErrorLogEntry(
            level: strtolower($matches['level']),
            message: $this->summary($matches['message']),
            loggedAt: Carbon::parse($matches['date']),
        );
    }

    private function summary(string $message): string
    {
        foreach (self::CONTEXT_STARTS as $contextStart) {
            $position = strpos($message, $contextStart);

            if ($position !== false) {
                $message = substr($message, 0, $position);
            }
        }

        $message = trim((string) preg_replace(self::EMPTY_CONTEXT, '', $message));

        return TruncatedText::of(
            SecretMasker::mask($message),
            config()->integer('famapp.control.message_max_length'),
        );
    }
}
