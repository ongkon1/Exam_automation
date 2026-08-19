<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Selectable reporting windows, in days. */
    protected const PERIODS = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'];

    /** Performance bands, highest first. Keys are the lower bound of each band. */
    protected const BANDS = [
        ['label' => 'Excellent', 'range' => '80-100%', 'min' => 80, 'colour' => '#22c55e'],
        ['label' => 'Good', 'range' => '60-79%', 'min' => 60, 'colour' => '#3b82f6'],
        ['label' => 'Average', 'range' => '40-59%', 'min' => 40, 'colour' => '#f59e0b'],
        ['label' => 'Needs Improvement', 'range' => '<40%', 'min' => 0, 'colour' => '#ef4444'],
    ];

    public function __invoke(Request $request): View
    {
        $period = $this->period($request);
        $class = $request->string('class')->trim()->toString();

        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays($period - 1)->startOfDay();
        $previousStart = $start->subDays($period);

        $averages = $this->studentAverages($class);

        return view('teacher.dashboard', [
            'periods' => self::PERIODS,
            'period' => $period,
            'periodLabel' => $start->format('M j').' – '.$end->format('M j, Y'),
            'class' => $class,
            'classes' => User::students()->whereNotNull('class_name')
                ->distinct()->orderBy('class_name')->pluck('class_name'),

            'students' => $this->studentTotals($start, $previousStart),
            'average' => $this->averageTotals($start, $previousStart),

            'bands' => $this->bands($averages),
            'gradedCount' => $averages->count(),
            'circumference' => round(2 * M_PI * 70, 2),

            'recentStudents' => User::students()
                ->when($class !== '', fn ($q) => $q->where('class_name', $class))
                ->with('latestResult')
                ->latest('id')
                ->take(5)
                ->get(),
        ]);
    }

    protected function period(Request $request): int
    {
        $period = $request->integer('period', 7);

        return array_key_exists($period, self::PERIODS) ? $period : 7;
    }

    /**
     * Total students, the change against the previous window, and a running sparkline.
     *
     * @return array<string, mixed>
     */
    protected function studentTotals(CarbonImmutable $start, CarbonImmutable $previousStart): array
    {
        $dates = User::students()->orderBy('created_at')->pluck('created_at');

        $current = $dates->filter(fn ($d) => $d >= $start)->count();
        $previous = $dates->filter(fn ($d) => $d >= $previousStart && $d < $start)->count();

        // Cumulative headcount at the end of each bucket.
        $series = $this->buckets($start, fn (CarbonImmutable $bucketEnd) => $dates->filter(
            fn ($d) => $d <= $bucketEnd
        )->count());

        return [
            'total' => $dates->count(),
            'trend' => $this->trend($current, $previous),
            'spark' => $this->sparkline($series),
        ];
    }

    /**
     * Mean score across all results, the change against the previous window, and a sparkline.
     *
     * @return array<string, mixed>
     */
    protected function averageTotals(CarbonImmutable $start, CarbonImmutable $previousStart): array
    {
        $rows = Result::query()
            ->where('created_at', '>=', $previousStart)
            ->get(['created_at', 'marks_obtained', 'full_marks']);

        $percentage = fn (Collection $set) => $set->isEmpty()
            ? null
            : round($set->avg(fn ($r) => $r->full_marks > 0 ? $r->marks_obtained / $r->full_marks * 100 : 0), 2);

        $current = $percentage($rows->filter(fn ($r) => $r->created_at >= $start));
        $previous = $percentage($rows->filter(fn ($r) => $r->created_at < $start));

        $last = 0.0;
        $series = $this->buckets($start, function (CarbonImmutable $bucketEnd, CarbonImmutable $bucketStart) use ($rows, $percentage, &$last) {
            $value = $percentage($rows->filter(
                fn ($r) => $r->created_at >= $bucketStart && $r->created_at <= $bucketEnd
            ));

            // Carry the previous value forward so a quiet day does not dip to zero.
            return $last = $value ?? $last;
        });

        return [
            'total' => Result::query()->count() === 0 ? null : $this->overallAverage(),
            'trend' => $this->trend($current ?? 0, $previous ?? 0),
            'spark' => $this->sparkline($series),
        ];
    }

    protected function overallAverage(): float
    {
        $rows = Result::query()->get(['marks_obtained', 'full_marks']);

        return round($rows->avg(fn ($r) => $r->full_marks > 0 ? $r->marks_obtained / $r->full_marks * 100 : 0), 1);
    }

    /**
     * Each student's mean score, keyed by student id.
     *
     * @return Collection<int, float>
     */
    protected function studentAverages(string $class): Collection
    {
        return Result::query()
            ->when($class !== '', fn ($q) => $q->whereIn(
                'student_id',
                User::students()->where('class_name', $class)->select('id')
            ))
            ->get(['student_id', 'marks_obtained', 'full_marks'])
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => round($rows->avg(
                fn ($r) => $r->full_marks > 0 ? $r->marks_obtained / $r->full_marks * 100 : 0
            ), 2));
    }

    /**
     * Bucket students into performance bands and pre-compute the donut geometry.
     *
     * @param  Collection<int, float>  $averages
     * @return array<int, array<string, mixed>>
     */
    protected function bands(Collection $averages): array
    {
        $total = max($averages->count(), 1);
        $circumference = 2 * M_PI * 70;
        $offset = 0.0;
        $bands = [];

        foreach (self::BANDS as $band) {
            $count = $averages->filter(fn (float $avg) => $avg >= $band['min'])->count();

            // Bands are evaluated highest-first, so remove anything already counted.
            foreach ($bands as $counted) {
                $count -= $counted['count'];
            }

            $share = $averages->count() === 0 ? 0.0 : round($count / $total * 100, 1);
            $length = $circumference * ($share / 100);

            $bands[] = $band + [
                'count' => $count,
                'share' => $share,
                'dash' => round($length, 2).' '.round($circumference - $length, 2),
                'offset' => round(-$offset, 2),
            ];

            $offset += $length;
        }

        return $bands;
    }

    /**
     * Percentage change between two windows, capped for display.
     *
     * @return array{value: float, up: bool, known: bool}
     */
    protected function trend(float $current, float $previous): array
    {
        if ($previous <= 0.0) {
            return ['value' => $current > 0 ? 100.0 : 0.0, 'up' => $current > 0, 'known' => $current > 0];
        }

        $change = ($current - $previous) / $previous * 100;

        return ['value' => round(abs($change)), 'up' => $change >= 0, 'known' => true];
    }

    /**
     * Split the window into equal buckets and resolve a value for each.
     *
     * @param  callable(CarbonImmutable, CarbonImmutable): (int|float)  $resolve
     * @return array<int, float>
     */
    protected function buckets(CarbonImmutable $start, callable $resolve, int $count = 10): array
    {
        $now = CarbonImmutable::now();
        $span = max($start->diffInSeconds($now), 1) / $count;
        $values = [];

        for ($i = 1; $i <= $count; $i++) {
            $bucketStart = $start->addSeconds((int) ($span * ($i - 1)));
            $bucketEnd = $start->addSeconds((int) ($span * $i));
            $values[] = (float) $resolve($bucketEnd, $bucketStart);
        }

        return $values;
    }

    /**
     * Turn a series into SVG polyline points plus a closed path for the area fill.
     *
     * @param  array<int, float>  $values
     * @return array{line: string, area: string}
     */
    protected function sparkline(array $values, float $width = 240, float $height = 58): array
    {
        if ($values === []) {
            $values = [0.0, 0.0];
        }

        $min = min($values);
        $range = (max($values) - $min) ?: 1;
        $step = count($values) > 1 ? $width / (count($values) - 1) : 0;
        $points = [];

        foreach (array_values($values) as $i => $value) {
            $x = round($i * $step, 2);
            $y = round($height - 6 - (($value - $min) / $range) * ($height - 14), 2);
            $points[] = $x.','.$y;
        }

        return [
            'line' => implode(' ', $points),
            'area' => implode(' ', $points).' '.$width.','.$height.' 0,'.$height,
        ];
    }
}
