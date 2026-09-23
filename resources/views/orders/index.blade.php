@extends('layouts.app')

@section('title', 'Список заявок — SuperPart')

@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Список заявок', 'url' => null],
    ]" />
@endsection

@section('content')
    <x-page-header title="Список заявок" />

    @include('orders._list-panel', [
        'formAction' => '/orders',
        'tabsUrl' => '/orders',
        'showExport' => true,
        'showCreateButton' => true,
        'filtersAlwaysVisible' => true,
    ])
@endsection
