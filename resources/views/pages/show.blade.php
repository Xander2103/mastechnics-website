@extends('layouts.app')

@section('title', $seo['title'])
@section('meta_description', $seo['description'])

@section('content')
    @include('partials.breadcrumbs', ['crumbs' => $seo['breadcrumbs']])

    @if ($page->type === 'home')
        @include('pages.partials.home-page')
    @elseif ($page->type === 'service')
        @include('pages.partials.service-page')
    @elseif ($page->type === 'services')
        @include('pages.partials.services-page')
    @elseif ($page->type === 'location')
        @include('pages.partials.location-page')
    @elseif ($page->type === 'service_area')
        @include('pages.partials.service-area-page')
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
