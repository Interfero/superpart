@extends('layouts.app')

@section('title', 'Настройка 2FA')

@section('content')
<div class="mx-auto max-w-lg space-y-4 py-10">
    <h1 class="text-xl font-semibold">Включение 2FA</h1>
    <p class="text-sm text-muted-foreground">Отсканируйте QR в приложении (Google Authenticator / Authy) или введите секрет вручную.</p>
    <div class="flex justify-center">
        <img src="{{ $qrUrl }}" alt="QR 2FA" width="200" height="200" class="rounded-md border border-border bg-white p-2">
    </div>
    <p class="break-all font-mono text-sm">{{ $secret }}</p>
    <form method="post" action="{{ route('two-factor.setup.confirm') }}" class="space-y-3">
        @csrf
        <label class="block text-sm">Код из приложения</label>
        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="8"
               class="w-full rounded-md border border-border bg-background px-3 py-2" required>
        @error('code')
            <p class="text-sm text-destructive">{{ $message }}</p>
        @enderror
        <button type="submit" class="rounded-md bg-primary px-4 py-2 text-primary-foreground">Подтвердить</button>
    </form>
</div>
@endsection
