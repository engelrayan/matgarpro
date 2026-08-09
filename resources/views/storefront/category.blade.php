@extends('storefront.layout')

@section('title', $category->name . ' — ' . $store->name)
@section('description', $category->description ?: $category->name . ' من ' . $store->name)

@section('content')
    @include('storefront.partials.sections')
@endsection
