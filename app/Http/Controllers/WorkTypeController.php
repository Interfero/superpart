<?php

namespace App\Http\Controllers;

use App\Models\WorkType;

class WorkTypeController extends Controller
{
    public function index()
    {
        $workTypes = WorkType::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(50);

        $workTypeStats = [
            'total' => WorkType::count(),
            'profile' => WorkType::where('is_profile', true)->count(),
            'non_profile' => WorkType::where('is_profile', false)->count(),
        ];

        return view('work-types.index', compact('workTypes', 'workTypeStats'));
    }
}
