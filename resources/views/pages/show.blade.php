@extends('layouts.app')

@section('title', $translation->meta_title ?? $translation->title)
@section('meta_description', $translation->meta_description ?? '')

@section('content')
    @if ($page->type === 'home')
        @include('pages.partials.home-page')
    @elseif ($page->type === 'service')
        @include('pages.partials.service-page')
    @elseif ($page->type === 'request')
        @include('pages.partials.request-page')
    @elseif ($page->type === 'contact')
        @include('pages.partials.contact-page')
    @elseif ($page->type === 'privacy')
        @include('pages.partials.privacy-page')
    @else
        @include('pages.partials.default-page')
    @endif
@endsection
