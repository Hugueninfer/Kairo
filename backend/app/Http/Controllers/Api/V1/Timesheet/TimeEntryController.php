<?php

namespace App\Http\Controllers\Api\V1\Timesheet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Timesheet\ReviewTimeEntriesRequest;
use App\Http\Requests\Timesheet\TimeEntryRequest;
use App\Http\Requests\Timesheet\TimerRequest;
use App\Http\Requests\Timesheet\TimesheetQueryRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Timesheet\TimeEntryService;
use App\Services\Timesheet\TimesheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimeEntryController extends Controller
{
    public function __construct(
        private readonly TimeEntryService $entries,
        private readonly TimesheetService $timesheet,
    ) {}

    public function index(TimesheetQueryRequest $request): AnonymousResourceCollection
    {
        return TimeEntryResource::collection($this->timesheet->entries($request->toFilters()));
    }

    public function week(TimesheetQueryRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->timesheet->week($request->week(), $request->userId())]);
    }

    public function export(TimesheetQueryRequest $request): StreamedResponse
    {
        $rows = $this->timesheet->csvRows($request->toFilters());
        $name = "horas-{$request->string('from')}-a-{$request->string('to')}.csv";

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // BOM para o Excel abrir os acentos certos.
            fwrite($out, "\xEF\xBB\xBF");

            foreach ($rows as $row) {
                fputcsv($out, $row, ';', '"', '');
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(TimeEntryRequest $request): JsonResponse
    {
        return TimeEntryResource::make($this->entries->create($this->user($request), $request->toData()))
            ->response()
            ->setStatusCode(201);
    }

    public function update(TimeEntryRequest $request, TimeEntry $entry): TimeEntryResource
    {
        return TimeEntryResource::make($this->entries->update($this->user($request), $entry, $request->toData()));
    }

    public function destroy(Request $request, TimeEntry $entry): Response
    {
        $this->entries->delete($this->user($request), $entry);

        return response()->noContent();
    }

    public function timer(Request $request): JsonResponse
    {
        $running = $this->entries->current($this->user($request));

        return response()->json(['data' => $running === null ? null : TimeEntryResource::make($running)]);
    }

    public function start(TimerRequest $request): JsonResponse
    {
        return TimeEntryResource::make($this->entries->start($this->user($request), $request->toData()))
            ->response()
            ->setStatusCode(201);
    }

    public function stop(Request $request): TimeEntryResource
    {
        return TimeEntryResource::make($this->entries->stop($this->user($request))->load(['project:id,ulid,name', 'task:id,ulid,title']));
    }

    public function review(ReviewTimeEntriesRequest $request): JsonResponse
    {
        $updated = $this->entries->review($this->user($request), $request->ids(), $request->status());

        return response()->json(['data' => ['updated' => $updated]]);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
