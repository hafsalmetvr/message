@extends('layouts.auth')

@section('title')
    {{--<title>Log in To Your  Account</title>--}}
    <title>Unsubscribe</title>
@endsection
@section('description')
    {{--<meta name="description" content="Log in to the cheap, reliable bulk sms service. Schedule SMS or send sms messages to your contact list with merge, tracking, and powerful features." />--}}
@endsection
@section('keywords')
    {{--<meta name="keywords" content="schedule sms, sms messages, cheap bulk sms, cheap bulk sms service, bulk sms login">--}}
@endsection

@section('content')
    <section class="section section-auth">
        <div class="container">
            <div class="columns is-vcentered">
                <div class="column is-4 is-offset-4">
                    <a class="logo-wrapper" href="{{ url('/') }}">
                        @include('components.logo')
                    </a>
                    <div class="box style-form">
                        <div class="box-content">
                            <h1 class="title">Unsubscribe</h1>
                            <p class="subtitle">Please enter your number below to stop receiving text messages from us:</p>

                            {{--@if(Session::has('warning'))--}}
                                {{--<p class="notification is-danger">{{Session::get('warning')}}</p>--}}
                            {{--@elseif(Session::has('status'))--}}
                                {{--<p class="notification">{{Session::get('status')}}</p>--}}
                            {{--@elseif(Session::has('message'))--}}
                                {{--<p class="notification is-danger">{{Session::get('message')}}</p>--}}
                            {{--@endif--}}

                            <form method="POST" action="{{ route('optout.post') }}">
                                {{ csrf_field() }}

                                <input type="hidden" name="list_id" value="{{ $list_id }}">
                                <input type="hidden" name="user_id" value="{{ $user_id }}">

                                @include('components.form.field', [
                                    'icon' => 'icon-globe',
                                    'name' => 'phonenumber',
                                    'type' => 'text',
                                    'placeholder' => 'International Code + Mobile Number',
                                    'value' => old('phonenumber'),
                                    'required' => true
                                ])

                                <br />

                                <div class="field">
                                    <div class="control">
                                        <button type="submit" class="button is-info is-fullwidth">Unsubscribe</button>
                                    </div>
                                </div>
                            </form>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>
@endsection
