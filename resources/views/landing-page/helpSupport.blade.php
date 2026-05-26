@extends('landing-page.layouts.default')

@section('content')
    <div class="my-5">
        <h4 class="text-center text-capitalize font-weight-bold my-5">{{__('landingpage.help_support')}}</h4>
        <div class="container">
            <div class="content-area">
                {!! $help_support->value ?? null !!}
            </div>
        </div>
    </div>
@endsection

@section('after_head')
    <style>
        .content-area p,
        .content-area span,
        .content-area div {
            line-height: 1.5 !important;
            margin-bottom: 12px !important;
        }
    </style>
@endsection
