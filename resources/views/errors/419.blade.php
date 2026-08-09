@extends('errors.layout')

@section('code', '٤١٩')
@section('title', 'الصفحة قعدت مفتوحة كتير')
{{-- The one error a merchant hits mid-task. Say what to do, not what expired:
     "page expired" tells them nothing about their half-filled form. --}}
@section('body', 'لأسباب أمنية الجلسة انتهت. حدّث الصفحة وجرّب تاني — بياناتك زي ما هي.')

@section('actions')
    <button type="button" class="btn-primary" onclick="location.reload()">حدّث الصفحة</button>
@endsection
