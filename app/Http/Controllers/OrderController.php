<?php

namespace App\Http\Controllers;

use App\Exports\OrdersExport;
use App\Models\City;
use App\Models\Order;
use App\Models\PortalNotification;
use App\Models\ReferenceSource;
use App\Models\Source;
use App\Models\User;
use App\Services\LevelionApiService;
use App\Services\StreetSuggestionService;
use App\Support\LocalSourceReferenceMirror;
use App\Support\OrderSourceOptions;
use App\Support\MeetingTime;
use App\Support\OrderCrmIdSync;
use App\Support\OrderEquipment;
use App\Support\OrderSourceFilter;
use App\Support\PartnerOrdersList;
use App\Support\PersonClientValidation;
use App\Support\PortalCityOptions;
use App\Support\VisitDateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    private const STATUS_LABELS = [
        'all' => 'Все заявки',
        'in_work' => 'В работе',
        'in_work_sd' => 'В работе СД',
        'on_way' => 'В пути',
        'ready' => 'Готов',
        'clarification' => 'На уточнении',
        'not_processed' => 'Не оформлена',
        'waiting' => 'Ожидает',
        'waiting_payment' => 'Ожидает выплаты',
        'refusal' => 'Отказ',
        'refusal_non_profile' => 'Отказ Непрофиль',
        'cancelled' => 'Отмена',
        'warranty' => 'Гарантия',
    ];

    private const TYPE_LABELS = [
        'first_time' => 'Впервые',
        'warranty' => 'Гарантия',
        'repeat' => 'Повтор',
    ];

    private const CANCELLABLE_STATUSES = [
        'not_processed',
        'waiting',
        'clarification',
        'in_work',
        'in_work_sd',
        'on_way',
        'ready',
    ];

    public function create()
    {
        $user = auth()->user();
        $ownerId = $user->effectiveOwnerId();

        $api = app(LevelionApiService::class);
        $useCrmReferenceSources = $api->isConfigured();

        if ($useCrmReferenceSources) {
            $api->syncCitiesFromLevelionIfStale();
        }

        $cities = PortalCityOptions::citiesForOrderForm($user, $useCrmReferenceSources);

        if ($useCrmReferenceSources) {
            $sources = collect();
        } else {
            $sourcesQuery = Source::where('user_id', $ownerId)->orderBy('name');

            if ($user->isManager()) {
                $allowedSourceIds = $user->allowedSources()->get()->pluck('id');
                $sourcesQuery->whereIn('id', $allowedSourceIds);
            }

            $sources = $sourcesQuery->pluck('name', 'id');
        }

        $referenceSources = collect();

        if ($useCrmReferenceSources) {
            $referenceSources = OrderSourceOptions::referenceSourcesForUser($user);
        }

        $equipmentCatalog = OrderEquipment::catalogForOrderForm($api->getEquipmentTypesCached());

        $equipmentByServer = [];
        foreach (array_keys(Order::serverTypeLabels()) as $serverType) {
            $equipmentByServer[$serverType] = OrderEquipment::codesForServerType($serverType);
        }

        return view('orders.create', [
            'cities' => $cities,
            'sources' => $sources,
            'referenceSources' => $referenceSources,
            'useCrmReferenceSources' => $useCrmReferenceSources,
            'equipmentCatalog' => $equipmentCatalog,
            'equipmentByServer' => $equipmentByServer,
            'serverTypes' => Order::serverTypeLabels(),
            'levelionConfigured' => $api->isConfigured(),
            'defaultMeetingTime' => MeetingTime::roundUpToFiveMinutes(
                old('meeting_time', now()->addHour()->format('H:i'))
            ),
        ]);
    }

    public function cityTime(int $city)
    {
        $user = auth()->user();

        $city = City::query()->findOrFail($city);

        if ($user->hasRestrictedCityAccess()) {
            $ok = $user->allowedCities()->where('cities.id', $city->getKey())->exists();
            abort_unless($ok, 403);
        }

        $tz = VisitDateTime::cityTimezone($city);
        $cityNow = VisitDateTime::cityNow($city);

        return response()->json([
            'success' => true,
            'city_name' => $city->name,
            'time' => $cityNow->format('H:i'),
            'date' => $cityNow->format('d.m.Y'),
            'date_iso' => $cityNow->format('Y-m-d'),
            'timezone' => $tz,
        ]);
    }

    public function streetSuggestions(Request $request, int $city, StreetSuggestionService $streetSuggestions)
    {
        $user = auth()->user();

        $city = City::query()->findOrFail($city);

        if ($user->hasRestrictedCityAccess()) {
            $ok = $user->allowedCities()->where('cities.id', $city->getKey())->exists();
            abort_unless($ok, 403);
        }

        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'streets' => [],
            ]);
        }

        return response()->json([
            'success' => true,
            'streets' => $streetSuggestions->suggest($city->name, $query),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $userId = $user->id;
        $ownerId = $user->effectiveOwnerId();

        $equipmentCodes = OrderEquipment::codes();
        $api = app(LevelionApiService::class);
        $useCrmSources = $api->isConfigured();

        if ($useCrmSources) {
            $api->syncCitiesFromLevelionIfStale();
        }

        $rules = [
            'server_type' => ['required', 'string', Rule::in(array_keys(Order::serverTypeLabels()))],
            'city_id' => ['required', 'exists:cities,id'],
            'order_type' => ['required', 'in:new,repeat,warranty'],
            'client_name' => ['nullable', 'string', 'max:20', 'regex:/^[А-Яа-яЁёA-Za-z\s\-]+$/u'],
            'client_age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'without_call' => ['nullable', 'boolean'],
            'client_phone' => ['required', 'string', 'max:50', PersonClientValidation::PHONE_DIGITS_10],

            'street' => ['required', 'string', 'max:30', PersonClientValidation::ADDRESS_FRAGMENT],
            'house' => ['required', 'string', 'max:30', PersonClientValidation::ADDRESS_FRAGMENT],
            'flat' => ['required', 'string', 'max:10', PersonClientValidation::ADDRESS_FRAGMENT],

            'address_adds' => ['nullable', 'string', 'max:'.PersonClientValidation::ADDRESS_ADDS_MAX, PersonClientValidation::ADDRESS_FRAGMENT],
            'order_adds' => ['required', 'string', 'max:65535'],
            'order_date' => ['required', 'date_format:d.m.Y'],
            'meeting_time' => ['required', 'date_format:H:i'],
            'equipment_type' => ['required', 'string', Rule::in($equipmentCodes)],
            'review_required' => ['nullable', 'boolean'],
        ];

        if ($useCrmSources) {
            $rules['reference_source_id'] = ['nullable', 'integer', ReferenceSource::existsRuleVisibleToPartnerUser($ownerId)];
        } else {
            $rules['source_id'] = ['nullable', 'integer', Source::existsRuleForOwnerId($ownerId)];
        }

        $validated = $request->validate($rules, [
            'server_type.required' => 'Выберите сервер.',
            'client_phone.required' => 'Укажите телефон клиента.',
            'client_phone.regex' => 'Укажите телефон в формате +7 и 10 цифр, например +7 960 701 12 63.',
            'client_name.max' => 'Имя клиента не должно быть длиннее 20 символов.',
            'client_name.regex' => 'Имя клиента может содержать только буквы, пробел и дефис.',
            'city_id.required' => 'Выберите город клиента.',
            'city_id.exists' => 'Выберите город из списка.',
            'order_type.required' => 'Выберите тип заявки.',
            'order_type.in' => 'Выберите тип заявки из списка.',
            'equipment_type.required' => 'Выберите вид работ.',
            'equipment_type.in' => 'Выберите вид работ из списка.',
            'street.required' => 'Укажите улицу.',
            'street.max' => 'Улица не должна быть длиннее 30 символов.',
            'street.regex' => 'Улица содержит недопустимые символы.',
            'house.required' => 'Укажите дом.',
            'house.max' => 'Дом не должен быть длиннее 30 символов.',
            'house.regex' => 'Дом содержит недопустимые символы.',
            'flat.max' => 'Кв/офис не должен быть длиннее 10 символов.',
            'flat.required' => 'Укажите квартиру/офис или «частный дом», «встречу у подъезда» и т.п.',
            'flat.regex' => 'Квартира/офис содержит недопустимые символы.',
            'address_adds.regex' => 'Дополнение к адресу содержит недопустимые символы.',
            'order_adds.required' => 'Укажите комментарий к заявке.',
            'order_date.required' => 'Укажите дату визита.',
            'order_date.date_format' => 'Дата визита должна быть в формате дд.мм.гггг.',
            'meeting_time.required' => 'Укажите время визита.',
            'meeting_time.date_format' => 'Время визита должно быть в формате чч:мм.',
            'reference_source_id.exists' => 'Источник не включён в каталог SuperPart. Включите флаг в CRM или выберите другой источник.',
            'source_id.exists' => 'Выберите источник из списка.',
        ]);

        $validated['without_call'] = $request->boolean('without_call');
        $validated['meeting_time'] = MeetingTime::roundUpToFiveMinutes($validated['meeting_time']);

        if ($user->hasRestrictedCityAccess()) {
            if (! $user->allowedCities()->where('cities.id', $validated['city_id'])->exists()) {
                throw ValidationException::withMessages([
                    'city_id' => 'Город недоступен для этого пользователя.',
                ]);
            }
        }

        if ($useCrmSources) {
            if ($user->isManager() && ! empty($validated['reference_source_id'])) {
                if (! $user->allowedReferenceSources()->where('reference_sources.id', $validated['reference_source_id'])->exists()) {
                    throw ValidationException::withMessages([
                        'reference_source_id' => 'Источник недоступен для этого пользователя.',
                    ]);
                }
            }
        } elseif ($user->isManager() && ! empty($validated['source_id'])) {
            if (! $user->allowedSources()->where('sources.id', $validated['source_id'])->exists()) {
                throw ValidationException::withMessages([
                    'source_id' => 'Источник недоступен для этого пользователя.',
                ]);
            }
        }

        $city = City::query()->findOrFail($validated['city_id']);

        if ($city->levelion_city_id === null && $api->isConfigured()) {
            return back()
                ->withInput()
                ->withErrors([
                    'city_id' => 'Выберите город из справочника CRM.',
                ]);
        }

        $typeMap = [
            'new' => 'first_time',
            'repeat' => 'repeat',
            'warranty' => 'warranty',
        ];

        $type = $typeMap[$validated['order_type']];

        $orderCore = OrderEquipment::orderCoreFromEquipment($validated['equipment_type']);
        $isNonProfile = $orderCore === 'non_core';

        $cityTz = VisitDateTime::cityTimezone($city);
        $cityToday = VisitDateTime::cityNow($city)->startOfDay();

        try {
            $visitDate = Carbon::createFromFormat('d.m.Y', $validated['order_date'], $cityTz)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'order_date' => 'Дата визита должна быть в формате дд.мм.гггг.',
            ]);
        }

        if ($visitDate->lt($cityToday)) {
            throw ValidationException::withMessages([
                'order_date' => 'Дата визита не может быть в прошлом по времени города клиента.',
            ]);
        }

        try {
            $orderTime = VisitDateTime::parseVisit($city, $validated['order_date'], $validated['meeting_time']);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'order_date' => 'Некорректная дата или время визита.',
            ]);
        }

        if ($orderTime->lt(VisitDateTime::cityNow($city)->startOfMinute())) {
            throw ValidationException::withMessages([
                'meeting_time' => 'Дата и время визита не могут быть в прошлом по местному времени города клиента.',
            ]);
        }

        if ($useCrmSources && ! empty($validated['reference_source_id'])) {
            $referenceSource = ReferenceSource::query()->find($validated['reference_source_id']);

            if (! $referenceSource || ! OrderSourceOptions::referenceSourceVisibleToUser($referenceSource, $user)) {
                throw ValidationException::withMessages([
                    'reference_source_id' => 'Источник недоступен для регистрации заявки. Выберите другой источник.',
                ]);
            }
        }

        $allowedEquipment = OrderEquipment::codesForServerType($validated['server_type']);
        if (! in_array($validated['equipment_type'], $allowedEquipment, true)) {
            throw ValidationException::withMessages([
                'equipment_type' => 'Выбранный вид работ не подходит для выбранного сервера.',
            ]);
        }

        $now = Carbon::now();

        $street = trim((string) $validated['street']);
        $house = trim((string) $validated['house']);
        $flat = trim((string) ($validated['flat'] ?? ''));

        $addressLine = trim(implode(' ', array_filter([
            $street,
            $house !== '' ? 'д. '.$house : null,
            $flat !== '' ? 'кв/офис '.$flat : null,
        ])));

        $order = new Order([
            'user_id' => $userId,
            'city_id' => $validated['city_id'],
            'server_type' => $validated['server_type'],
            'status' => $validated['without_call'] ? 'waiting' : 'not_processed',
            'type' => $type,
            'source_id' => $useCrmSources ? null : ($validated['source_id'] ?? null),
            'reference_source_id' => $useCrmSources ? ($validated['reference_source_id'] ?? null) : null,
            'work_type_id' => null,
            'order_time' => $orderTime,
            'client_name' => $validated['client_name'] ?: 'Клиент',
            'client_phone' => $validated['client_phone'],
            'client_age' => $validated['client_age'] ?? null,
            'without_call' => $validated['without_call'],
            'is_non_profile' => $isNonProfile,
            'settlement' => null,
            'address' => $addressLine !== '' ? $addressLine : null,
            'street' => $street,
            'house' => $house,
            'flat' => $flat !== '' ? $flat : null,
            'address_adds' => $validated['address_adds'] ?? null,
            'order_adds' => $validated['order_adds'] ?? null,
            'employee_id' => null,
            'charge_amount' => null,
            'created_local' => $now,
            'closed_local' => null,
            'equipment_type' => $validated['equipment_type'],
            'order_core' => $orderCore,
            'sync_status' => 'pending',
            'levelion_order_id' => null,
        ]);

        if ($api->isConfigured() && $city->levelion_city_id !== null) {
            if ($order->reference_source_id) {
                $order->load('referenceSource');
                if ($order->referenceSource?->local_source_id && ! $order->referenceSource->canPushToCrm()) {
                    LocalSourceReferenceMirror::sync(
                        Source::query()->findOrFail($order->referenceSource->local_source_id)
                    );
                    $order->load('referenceSource');
                }
            }

            $push = $api->pushPartnerOrder($order, LevelionApiService::partnerOrderIdempotencyKey($order));

            if ($push['crm_id'] !== null) {
                $order = OrderCrmIdSync::insertAsCrmOrder($order, (int) $push['crm_id']);
            } else {
                $order->sync_status = 'error';
                $order->sync_last_error = $push['error'];
                $order = OrderCrmIdSync::persistParked($order);
            }

            if (($push['crm_id'] ?? null) === null
                && is_string($push['error'] ?? null)
                && str_contains((string) $push['error'], 'available_for_superpart')
                && $order->referenceSource) {
                $order->referenceSource->update(['available_for_superpart' => false]);
            }
        } elseif ($api->isConfigured()) {
            $order->sync_status = 'error';
            $order->sync_last_error = 'Город не синхронизирован с CRM.';
            $order = OrderCrmIdSync::persistParked($order);
        } else {
            $order->save();
        }

        $this->notifyOrderCreated($order, $user, $ownerId);

        $msg = $order->isParked()
            ? 'Заявка сохранена как черновик (номер CRM ещё не получен).'
            : 'Заявка успешно создана.';

        if ($order->sync_status === 'error') {
            $msg .= ' В CRM не отправлено.';

            $detail = $order->sync_last_error;

            if (is_string($detail) && $detail !== '') {
                $msg .= ' '.$detail;
            }
        }

        return redirect()
            ->route('orders.show', $order->id)
            ->with('status', $msg);
    }

    private function notifyOrderCreated(Order $order, User $creator, int $ownerId): void
    {
        try {
            $recipients = collect();

            $owner = User::query()->find($ownerId);

            if ($owner && $owner->isPartner() && (int) $owner->id !== (int) $creator->id) {
                $recipients->push($owner);
            }

            User::query()
                ->portalAdmins()
                ->get()
                ->each(fn (User $admin) => $recipients->push($admin));

            $recipients
                ->unique('id')
                ->each(function (User $recipient) use ($order, $creator) {
                    PortalNotification::create([
                        'user_id' => $recipient->id,
                        'type' => 'order_created',
                        'title' => 'Новая заявка',
                        'message' => 'Создана заявка #'.$order->id.' от пользователя '.$creator->displayFullName().'.',
                        'url' => route('orders.show', $order->id),
                    ]);
                });
        } catch (\Throwable) {
            //
        }
    }

    public function index(Request $request)
    {
        return view('orders.index', PartnerOrdersList::resolve($request, $request->user()));
    }

    public function unprocessed(Request $request)
    {
        $user = $request->user();

        $ordersQuery = Order::query()
            ->forPortalUser($user)
            ->where('status', 'not_processed')
            ->with(['city', 'source', 'referenceSource', 'employee', 'workType', 'user']);

        PartnerOrdersList::applyFilters($ordersQuery, $request);
        PartnerOrdersList::applySorting($ordersQuery, $request);

        $orders = $ordersQuery->paginate(25)->withQueryString();
        $filterOptions = PartnerOrdersList::filterOptions($user);

        return view('orders.unprocessed', compact('orders', 'filterOptions'));
    }

    public function show(int $id)
    {
        $user = auth()->user();

        $order = Order::query()
            ->forPortalUser($user)
            ->with(['city', 'source', 'referenceSource', 'employee', 'workType', 'user'])
            ->wherePublicId($id)
            ->firstOrFail();

        // FR-REC-05: карточка читает локальную проекцию; CRM sync — фоновый cursor/reconcile.

        return view('orders.partner-order-detail', [
            'order' => $order,
            'canComment' => ! in_array($order->status, ['cancelled', 'refusal', 'refusal_non_profile', 'warranty'], true),
            'canCancel' => in_array($order->status, self::CANCELLABLE_STATUSES, true),
            'canRetrySync' => $order->sync_status === 'error'
                && $order->levelion_order_id === null
                && app(LevelionApiService::class)->isConfigured(),
        ]);
    }

    public function addComment(Request $request, int $id)
    {
        $user = auth()->user();

        $order = Order::query()
            ->forPortalUser($user)
            ->wherePublicId($id)
            ->firstOrFail();

        if (in_array($order->status, ['cancelled', 'refusal', 'refusal_non_profile', 'warranty'], true)) {
            return back()->withErrors(['comment' => 'Нельзя добавить комментарий к закрытой заявке.']);
        }

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ], [
            'comment.required' => 'Введите текст комментария.',
        ]);

        $stamp = now()->format('d.m.Y H:i');
        $author = $user->displayFullName();
        $append = "[{$stamp}, {$author}] ".$validated['comment'];
        $order->order_adds = trim((string) $order->order_adds."\n\n".$append);
        $order->save();

        return back()->with('status', 'Комментарий добавлен.');
    }

    public function cancel(Request $request, int $id)
    {
        $user = auth()->user();

        $order = Order::query()
            ->forPortalUser($user)
            ->wherePublicId($id)
            ->firstOrFail();

        if (! in_array($order->status, self::CANCELLABLE_STATUSES, true)) {
            return back()->withErrors(['cancel' => 'Эту заявку нельзя отменить в текущем статусе.']);
        }

        $api = app(LevelionApiService::class);
        $crmId = (int) ($order->levelion_order_id ?: ($order->isParked() ? 0 : $order->id));
        if ($api->isConfigured() && $crmId > 0) {
            $crm = $api->cancelPartnerOrder($crmId);
            if (! $crm['ok']) {
                return back()->withErrors([
                    'cancel' => $crm['error'] ?: 'Не удалось отменить заявку в CRM.',
                ]);
            }
        }

        $order->status = 'cancelled';
        $order->save();

        return back()->with('status', 'Заявка отменена.');
    }

    public function retrySync(int $id)
    {
        $user = auth()->user();
        $api = app(LevelionApiService::class);

        if (! $api->isConfigured()) {
            return back()->withErrors(['sync' => 'Интеграция с CRM не настроена.']);
        }

        $order = Order::query()
            ->forPortalUser($user)
            ->with(['city', 'referenceSource'])
            ->wherePublicId($id)
            ->firstOrFail();

        if ($order->sync_status === 'synced'
            && (int) ($order->levelion_order_id ?? 0) === (int) $order->id
            && (int) $order->id > 0
            && ! \App\Support\OrderCrmIdSync::isParkedId((int) $order->id)) {
            return back()->with('status', 'Заявка уже есть в CRM.');
        }

        if ($order->levelion_order_id && (int) $order->levelion_order_id !== (int) $order->id) {
            $order = OrderCrmIdSync::align($order, (int) $order->levelion_order_id);

            return back()->with('status', 'ID заявки приведён к CRM (#'.$order->id.').');
        }

        if ($order->city?->levelion_city_id === null) {
            return back()->withErrors(['sync' => 'Город не привязан к CRM.']);
        }

        if ($order->reference_source_id) {
            $order->load('referenceSource');

            if ($order->referenceSource?->local_source_id) {
                LocalSourceReferenceMirror::sync(
                    Source::query()->findOrFail($order->referenceSource->local_source_id)
                );
                $order->load('referenceSource');
            }

            if (! $order->referenceSource || ! OrderSourceOptions::referenceSourceVisibleToUser($order->referenceSource, $user)) {
                return back()->withErrors([
                    'sync' => 'Источник недоступен для заявки. Выберите другой источник или обновите карточку источника.',
                ]);
            }
        }

        $push = $api->pushPartnerOrder($order, LevelionApiService::partnerOrderIdempotencyKey($order));

        if ($push['crm_id'] !== null) {
            $order = OrderCrmIdSync::align($order, (int) $push['crm_id']);
            $order->sync_status = 'synced';
            $order->sync_last_error = null;
            $order->save();

            return back()->with('status', 'Заявка отправлена в CRM (ID '.$order->id.').');
        }

        $order->sync_status = 'error';
        $order->sync_last_error = $push['error'];
        $order->save();

        if (is_string($push['error'])
            && str_contains($push['error'], 'available_for_superpart')
            && $order->referenceSource) {
            $order->referenceSource->update(['available_for_superpart' => false]);
        }

        return back()->withErrors(['sync' => $push['error'] ?? 'Не удалось отправить заявку в CRM.']);
    }

    public function export(Request $request)
    {
        $user = $request->user();
        if ($user?->isManager()) {
            abort(403);
        }

        $format = $request->input('format', 'xlsx');

        if (! in_array($format, ['xlsx', 'csv'], true)) {
            abort(422, 'Неподдерживаемый формат экспорта.');
        }

        // Без периода — только текущий месяц (иначе выгрузка «за всё время»).
        if (! $request->filled('date_from') && ! $request->filled('date_to')) {
            $request->merge([
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->endOfMonth()->toDateString(),
            ]);
        }

        $activeStatus = $request->input('status', 'all');

        $ordersQuery = Order::query()
            ->forPortalUser($user)
            ->with(['city', 'source', 'referenceSource', 'employee', 'workType', 'user']);

        if ($activeStatus === 'warranty') {
            $ordersQuery->where('type', 'warranty');
        } elseif ($activeStatus !== 'all') {
            $ordersQuery->where('status', $activeStatus);
        }

        if ($request->filled('date_from')) {
            $ordersQuery->whereDate('order_time', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $ordersQuery->whereDate('order_time', '<=', $request->input('date_to'));
        }

        PartnerOrdersList::applyFilters($ordersQuery, $request);
        PartnerOrdersList::applySorting($ordersQuery, $request);

        $count = (clone $ordersQuery)->count();
        if ($count > PartnerOrdersList::EXPORT_MAX_ROWS) {
            abort(422, 'Слишком много заявок для экспорта ('.$count.'). Сузьте период или фильтры (лимит '.PartnerOrdersList::EXPORT_MAX_ROWS.').');
        }

        $orders = $ordersQuery->get();

        return (new OrdersExport($orders))->download($format);
    }
}