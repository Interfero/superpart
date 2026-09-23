@extends('layouts.app')

@section('title', 'Двухфакторная аутентификация')

@section('content')
<div class="mx-auto max-w-md space-y-4 py-10">
    <h1 class="text-xl font-semibold">Код подтверждения</h1>
    <p class="text-sm text-muted-foreground">Введите 6‑значный код из приложения‑аутентификатора.</p>
    <form method="post" action="{{ route('two-factor.challenge.verify') }}" class="space-y-3">
        @csrf
        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="8"
               class="w-full rounded-md border border-border bg-background px-3 py-2" required>
        @error('code')
            <p class="text-sm text-destructive">{{ $message }}</p>
        @enderror
        <button type="submit" class="rounded-md bg-primary px-4 py-2 text-primary-foreground">Продолжить</button>
    </form>
</div>
@endsection
