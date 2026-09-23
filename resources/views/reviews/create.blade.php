@extends('layouts.app')

@section('title', 'Добавить отзыв — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Отчёт по отзывам', 'url' => route('reports.reviews')],
        ['label' => 'Добавить', 'url' => null],
    ]" />
@endsection

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <div class="overflow-hidden rounded-2xl bg-gradient-to-r from-primary/15 via-primary/5 to-transparent">
            <div class="flex flex-col gap-4 px-6 py-7 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-foreground">Добавить отзыв</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Добавьте ссылку, изображение и подробный комментарий к претензии.</p>
                </div>
                <x-ui.button tag="a" :href="route('reports.reviews')" variant="secondary">К отчёту</x-ui.button>
            </div>
        </div>

        @if ($errors->any())
            <x-ui.alert type="error">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </x-ui.alert>
        @endif

        <form method="POST" action="{{ route('reviews.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <x-ui.card>
                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.form-group label="Тип отзыва" name="review_type">
                        <x-ui.select id="review_type" name="review_type" :error="$errors->has('review_type')">
                            <option value="">Без типа</option>
                            <option value="kc" @selected(old('review_type') === 'kc')>КЦ отзыв</option>
                            <option value="branch" @selected(old('review_type') === 'branch')>Филиал</option>
                        </x-ui.select>
                    </x-ui.form-group>

                    <x-ui.form-group label="ID заявки (необязательно)" name="order_id" tip="Можно выбрать заявку или оставить поле пустым.">
                        <x-ui.select id="order_id" name="order_id" :error="$errors->has('order_id')">
                            <option value="">Без заявки</option>
                            @foreach ($orders as $order)
                                <option value="{{ $order->id }}" @selected((string) old('order_id', $preselectedOrderId ?? '') === (string) $order->id)>№{{ $order->id }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.form-group>

                    <x-ui.form-group label="Город (необязательно)" name="city_id">
                        <x-ui.select id="city_id" name="city_id" :error="$errors->has('city_id')">
                            <option value="">Без города</option>
                            @foreach ($cities as $city)
                                <option value="{{ $city->id }}" @selected(old('city_id') == $city->id)>{{ $city->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.form-group>

                    <x-ui.form-group label="Ссылка на отзыв" name="review_url" required tip="Вставьте полную ссылку, начинающуюся с https://">
                        <x-ui.input type="url" id="review_url" name="review_url" value="{{ old('review_url') }}" required placeholder="https://..." :error="$errors->has('review_url')" />
                    </x-ui.form-group>
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-ui.form-group label="Комментарий к отзыву" name="comment" required hint="Введите, пожалуйста, комментарий к претензии — не менее 10 букв.">
                    <textarea id="comment" name="comment" rows="6" required
                        class="w-full rounded-xl border border-border bg-background px-4 py-3 text-sm text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                        placeholder="Введите, пожалуйста, комментарий к претензии (от 10 букв)">{{ old('comment') }}</textarea>
                </x-ui.form-group>
            </x-ui.card>

            <x-ui.card>
                <h2 class="mb-4 text-lg font-semibold text-foreground">Изображение</h2>
                <label class="flex min-h-[150px] cursor-pointer flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-muted/20 px-6 py-8 text-center transition hover:border-primary/50 hover:bg-primary/5">
                    <span class="text-sm font-medium text-foreground">Выберите файл</span>
                    <span class="mt-2 text-xs text-muted-foreground">JPG, JPEG или PNG, до 10 МБ</span>
                    <input type="file" name="photo" id="review_photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="hidden">
                </label>
                <div id="review-photo-preview-wrap" class="mt-4 hidden">
                    <img id="review-photo-preview" src="" alt="Предпросмотр" class="max-h-64 rounded-xl border border-border object-contain">
                    <p id="review-photo-name" class="mt-2 text-xs text-muted-foreground"></p>
                </div>
            </x-ui.card>

            <div class="flex flex-wrap gap-3">
                <x-ui.button type="submit" variant="primary" size="lg">Отправить</x-ui.button>
                <x-ui.button tag="a" :href="route('reports.reviews')" variant="secondary" size="lg">Отмена</x-ui.button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('review_photo');
    const wrap = document.getElementById('review-photo-preview-wrap');
    const image = document.getElementById('review-photo-preview');
    const name = document.getElementById('review-photo-name');

    input?.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) {
            wrap?.classList.add('hidden');
            return;
        }
        if (!['image/jpeg', 'image/png'].includes(file.type) || file.size > 10 * 1024 * 1024) {
            input.value = '';
            wrap?.classList.add('hidden');
            alert('Выберите JPG, JPEG или PNG размером до 10 МБ.');
            return;
        }
        image.src = URL.createObjectURL(file);
        name.textContent = file.name;
        wrap?.classList.remove('hidden');
    });
});
</script>
@endpush
