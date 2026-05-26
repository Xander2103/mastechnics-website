@extends('layouts.app')

@section('title', $translation->meta_title ?? $translation->title)
@section('meta_description', $translation->meta_description ?? '')

@section('content')
    @if ($page->type === 'home')
        @include('pages.partials.home-page')
    @elseif ($page->type === 'service')
        @include('pages.partials.service-page')
    @else
        @include('pages.partials.default-page')
    @endif
@endsection