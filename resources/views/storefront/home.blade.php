@extends('storefront.layout')

@section('title', $store->name)
@section('description', $store->description ?: 'اطلب دلوقتي من ' . $store->name . ' — الدفع عند الاستلام.')

@section('content')
    {{-- The page is whatever the merchant arranged in the builder. A store that
         never opened it gets the platform's default order, which is the layout
         this file used to hardcode — see PageBuilder::defaults(). --}}
    @include('storefront.partials.sections')
@endsection
