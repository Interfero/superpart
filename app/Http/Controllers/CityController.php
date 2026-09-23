<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('access-cities-directory');

        $query = City::query();

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        if ($request->filled('city_id')) {
            $query->where('id', 'like', '%' . $request->input('city_id') . '%');
        }

        $available = $request->query('available', '1');
        if (! in_array($available, ['1', '0', 'all'], true)) {
            $available = '1';
        }

        if ($available === '1' || $available === '0') {
            $query->where('is_available', $available === '1');
        }

        $cities = $query->orderBy('name')->paginate(50)->withQueryString();

        return view('cities.index', compact('cities', 'available'));
    }
}
