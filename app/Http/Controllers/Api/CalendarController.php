<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalendarRequest;
use App\Models\CalendarItem;
use App\Services\CalendarService;
use App\Support\ApiMapper;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function store(CalendarRequest $request, CalendarService $calendar)
    {
        return response()->json(['data' => ApiMapper::camel($calendar->create($request->user(), $request->validated())->toArray())], 201);
    }

    public function update(CalendarRequest $request, CalendarItem $item, CalendarService $calendar)
    {
        return response()->json(['data' => ApiMapper::camel($calendar->update($request->user(), $item, $request->validated())->toArray())]);
    }

    public function cancel(Request $request, CalendarItem $item, CalendarService $calendar)
    {
        return response()->json(['data' => ApiMapper::camel($calendar->cancel($request->user(), $item)->toArray())]);
    }
}
