@extends('layouts.base')

@section('head_style')
	<link href="{{ mix('css/landing.css') }}" rel="stylesheet" />
@endsection


@section('title')
<title>Send Bulk SMS </title>
@endsection
@section('description') 
	<meta name="description" content="Send Bulk SMS or use our SMS API to implement SMS notifications, OTP, reminders into your workflow. Build apps that send SMS with our redundant SSL SMS API." />
@endsection
@section('keywords') 
	<meta name="keywords" content="send bulk sms, sms api, sms gateway, sms notifications, otp sms">
@endsection

@section('structure')
	@include('components.header')
	@yield('content')
	@include('components.footer')
@endsection

@section('bottom_script')
	<script src="{{ mix('js/landing.js') }}"></script>
@endsection
