<?php

/**
 * Raised when an export arrives for a week that already holds its authoritative snapshot
 *
 * The dashboard is a weekly-trend tool: each week should hold one snapshot, taken once
 * that week has finished. Newzware exports the All Subscriber Report six mornings a
 * week, and every one of those files maps to the same week, so without this the last
 * file of the week wins and the week silently describes a later reality.
 *
 * This is a normal, expected outcome for Tuesday-through-Saturday exports — not a
 * failure. Callers should skip the file rather than treating it as an error.
 *
 * Date: 2026-08-21
 */

namespace CirculationDashboard;

use Exception;

class WeekAlreadyClosedException extends Exception
{
}
