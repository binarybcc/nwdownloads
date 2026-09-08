<?php

/**
 * Alert throttling with recovery notification.
 *
 * A scraper that fails every hour should not send an email every hour. This
 * tracks the current failure signature in a small JSON state file and reports:
 *
 *   - the first time a failure appears,
 *   - again only when the failure changes shape, or after a quiet period,
 *   - once when the failure clears.
 *
 * The 2026-09-03 portal outage sent roughly 430 identical emails over five
 * days; under this policy it would have sent one alert and one recovery.
 *
 * @package CirculationDashboard
 */

namespace CirculationDashboard;

class AlertThrottle
{
    private string $stateFile;
    private int $repeatInterval;

    /**
     * @param string $stateFile Path to the JSON state file
     * @param int $repeatInterval Seconds before an unchanged failure is re-reported
     */
    public function __construct(string $stateFile, int $repeatInterval = 21600)
    {
        $this->stateFile = $stateFile;
        $this->repeatInterval = $repeatInterval;
    }

    /**
     * Decide what to send for the current run.
     *
     * @param string[] $failures Failure descriptions for this run; empty means healthy
     * @param int|null $now Unix timestamp, or null for the current time
     * @return array{action: string, suppressed: int, since: int|null}
     *   action is 'alert' (report failures), 'recovery' (report the all-clear),
     *   or 'none' (stay quiet).
     */
    public function evaluate(array $failures, ?int $now = null): array
    {
        $now = $now ?? time();
        $state = $this->readState();

        if ($failures === []) {
            // Only announce recovery if we had actually reported a problem.
            if (($state['signature'] ?? null) !== null) {
                $this->writeState(null);
                return [
                    'action' => 'recovery',
                    'suppressed' => (int) ($state['suppressed'] ?? 0),
                    'since' => isset($state['first_seen']) ? (int) $state['first_seen'] : null,
                ];
            }
            return ['action' => 'none', 'suppressed' => 0, 'since' => null];
        }

        $signature = $this->signature($failures);
        $isNewProblem = ($state['signature'] ?? null) !== $signature;
        $lastSent = (int) ($state['last_sent'] ?? 0);
        $isStale = ($now - $lastSent) >= $this->repeatInterval;

        if ($isNewProblem || $isStale) {
            $this->writeState([
                'signature' => $signature,
                'first_seen' => $isNewProblem ? $now : (int) ($state['first_seen'] ?? $now),
                'last_sent' => $now,
                'suppressed' => 0,
            ]);
            return [
                'action' => 'alert',
                'suppressed' => $isNewProblem ? 0 : (int) ($state['suppressed'] ?? 0),
                'since' => $isNewProblem ? $now : (int) ($state['first_seen'] ?? $now),
            ];
        }

        // Same problem, reported recently — stay quiet but count the run.
        $state['suppressed'] = (int) ($state['suppressed'] ?? 0) + 1;
        $this->writeState($state);
        return ['action' => 'none', 'suppressed' => (int) $state['suppressed'], 'since' => (int) ($state['first_seen'] ?? $now)];
    }

    /**
     * Stable fingerprint of a set of failures, so repeats are recognised
     * regardless of the order the scrape happened to visit them in.
     *
     * @param string[] $failures
     * @return string
     */
    private function signature(array $failures): string
    {
        sort($failures);
        return hash('sha256', implode("\n", $failures));
    }

    /**
     * @return array<string, mixed>
     */
    private function readState(): array
    {
        if (!is_readable($this->stateFile)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($this->stateFile), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed>|null $state Null clears the stored failure
     */
    private function writeState(?array $state): void
    {
        if ($state === null) {
            @unlink($this->stateFile);
            return;
        }
        @file_put_contents($this->stateFile, json_encode($state, JSON_PRETTY_PRINT));
    }
}
