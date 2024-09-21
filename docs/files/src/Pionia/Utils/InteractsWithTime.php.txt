<?php

namespace Pionia\Utils;

use Carbon\CarbonInterval;
use DateInterval;
use DateTimeInterface;
use Exception;
use Pionia\Collections\Carbon;

trait InteractsWithTime
{
    /**
     * Get the number of seconds until the given DateTime.
     *
     * @param DateInterval|DateTimeInterface|int $delay
     * @return int
     */
    protected function secondsUntil(DateInterval|DateTimeInterface|int $delay): int
    {
        $delay = $this->parseDateInterval($delay);

        return $delay instanceof DateTimeInterface
            ? max(0, $delay->getTimestamp() - $this->currentTime())
            : (int) $delay;
    }

    /**
     * Get the "available at" UNIX timestamp.
     *
     * @param DateInterval|DateTimeInterface|int $delay
     * @return int
     */
    protected function availableAt(DateInterval|DateTimeInterface|int $delay = 0): int
    {
        $delay = $this->parseDateInterval($delay);

        return $delay instanceof DateTimeInterface
            ? $delay->getTimestamp()
            : Carbon::now()->addRealSeconds($delay)->getTimestamp();
    }

    /**
     * If the given value is an interval, convert it to a DateTime instance.
     *
     * @param DateInterval|DateTimeInterface|int $delay
     * @return DateInterval|DateTimeInterface|int
     */
    protected function parseDateInterval(DateInterval|DateTimeInterface|int $delay): DateInterval|DateTimeInterface|int
    {
        if ($delay instanceof DateInterval) {
            $delay = Carbon::now()->add($delay);
        }

        return $delay;
    }

    /**
     * Get the current system time as a UNIX timestamp.
     *
     * @return int
     */
    protected function currentTime(): int
    {
        return Carbon::now()->getTimestamp();
    }

    /**
     * Given a start time, format the total run time for human readability.
     *
     * @param float $startTime
     * @param float|null $endTime
     * @return string
     * @throws Exception
     */
    protected function runTimeForHumans(float $startTime, float $endTime = null): string
    {
        $endTime ??= microtime(true);

        $runTime = ($endTime - $startTime) * 1000;

        return $runTime > 1000
            ? CarbonInterval::milliseconds($runTime)->cascade()->forHumans(short: true)
            : number_format($runTime, 2).'ms';
    }
}
