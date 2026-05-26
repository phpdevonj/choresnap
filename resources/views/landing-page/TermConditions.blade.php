@extends('landing-page.layouts.default')

@section('content')
    <div class="my-5 content-area">
        <h4 class="text-center text-capitalize font-weight-bold my-5">{{__('messages.terms_condition')}}</h4>
        <div class="container">
            <div class="content-area">
                {!! $term_condition->value ?? null !!}
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
    <style>
h1 { font-family: Verdana; font-size: 26px; font-weight: bold; color: #038d8d; }
h2 { font-family: Verdana; font-size: 18px; font-weight: bold; color: #e67e22; }
p  { font-family: Verdana; font-size: 18px; color: #000000; }
</style>
@endsection
