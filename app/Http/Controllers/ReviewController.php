<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Services\LevelionApiService;
use App\Support\PortalCityOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    private const STATUS_LABELS = [
        'new' => 'Новая',
        'resolved' => 'Решена',
        'not_resolved' => 'Не решена',
    ];

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Review::query()
            ->forPortalUser($user)
            ->with(['order', 'city']);

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $cities = PortalCityOptions::cityNamesForReviewFilters($user, $query);
        $reviews = $query->orderByDesc('id')->paginate(25)->withQueryString();

        return view('reviews.index', [
            'reviews' => $reviews,
            'cities' => $cities,
            'statusLabels' => self::STATUS_LABELS,
            'dateFrom' => $request->date_from,
            'dateTo' => $request->date_to,
        ]);
    }

    public function create(Request $request)
    {
        $user = auth()->user();

        $orders = Order::query()
            ->forPortalUser($user)
            ->orderByDesc('id')
            ->get(['id']);

        $preselectedOrderId = (int) ($request->query('order_id') ?: old('order_id', 0));

        $api = app(LevelionApiService::class);

        if ($api->isConfigured()) {
            $api->syncCitiesFromLevelion();
        }

        $citiesQuery = City::query()
            ->whereNotNull('levelion_city_id')
            ->orderBy('name');

        PortalCityOptions::applyOrderFormCityScope($citiesQuery, $user);

        $cities = $citiesQuery->get(['id', 'name']);

        return view('reviews.create', compact('orders', 'cities', 'preselectedOrderId'));
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'review_type' => ['nullable', Rule::in(['kc', 'branch'])],
            'order_id' => ['nullable', 'integer', 'min:1', 'exists:orders,id'],
            'review_url' => ['required', 'url', 'starts_with:https://', 'max:2000'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'comment' => [
                'required',
                'string',
                'max:5000',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (preg_match_all('/\p{L}/u', (string) $value) < 10) {
                        $fail('Комментарий должен содержать не менее 10 букв.');
                    }
                },
            ],
            'photo' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:10240'],
        ], [
            'order_id.exists' => 'Заказ не найден',
            'review_url.required' => 'Укажите ссылку на отзыв',
            'review_url.url' => 'Некорректный формат ссылки',
            'review_url.starts_with' => 'Ссылка должна начинаться с https://',
            'review_url.max' => 'Ссылка слишком длинная',
            'comment.required' => 'Введите комментарий к претензии.',
            'photo.mimes' => 'Фото должно быть в формате PNG, JPG или JPEG.',
            'photo.max' => 'Фото не должно быть больше 10 МБ.',
        ]);

        $order = null;

        if (! empty($validated['order_id'])) {
            $order = Order::query()
                ->forPortalUser($user)
                ->where('id', $validated['order_id'])
                ->firstOrFail();
        }

        if ($user->hasRestrictedCityAccess()
            && ! empty($validated['city_id'])
            && ! $user->allowedCities()->where('cities.id', (int) $validated['city_id'])->exists()) {
            return back()
                ->withInput()
                ->withErrors(['city_id' => 'Город недоступен для этого пользователя.']);
        }

        $photos = [];

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $photos[] = [
                'path' => $file->store('review-photos', 'local'),
                'disk' => 'local',
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ];
        }

        $review = Review::create([
            'user_id' => $user->id,
            'order_id' => $order?->id,
            'status' => 'new',
            'review_type' => $validated['review_type'] ?? null,
            'review_url' => $validated['review_url'],
            'comment' => $validated['comment'] ?? null,
            'result' => null,
            'refund' => false,
            'photos' => $photos,
            'city_id' => $validated['city_id'] ?? $order?->city_id,
        ]);

        return redirect()
            ->route('reviews.show', $review)
            ->with('success', 'Отзыв отправлен.');
    }

    public function show(Request $request, Review $review)
    {
        $user = $request->user();

        $allowed = Review::query()
            ->forPortalUser($user)
            ->whereKey($review->id)
            ->exists();

        abort_unless($allowed, 403);

        $review->load(['order', 'city', 'user']);

        return view('reviews.show', [
            'review' => $review,
            'statusLabels' => self::STATUS_LABELS,
        ]);
    }
}
