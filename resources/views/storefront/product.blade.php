@extends('storefront.layout')

@section('title', $product->seo_title ?: $product->name . ' — ' . $store->name)
@section('description', $product->seo_description ?: Str::limit(strip_tags((string) $product->description), 155))
@if ($product->primaryImage())
    @section('image', $product->primaryImage()->url())
@endif

@section('content')
    @include('storefront.partials.sections')
@endsection
