@extends('adminlte::page')

@section('title', 'Dashboard')

@section('content_header')
    <h1>Dashboard</h1>
@stop

@section('content')

    @include($viewName, $data)
@stop

@push('css')
<style>
    .list-group-item-action {
        color: #495057;
    }
    .list-group-item-action:hover, .list-group-item-action:focus {
        background-color: #f8f9fa;
        color: #1f2d3d;
    }
</style>
@endpush
