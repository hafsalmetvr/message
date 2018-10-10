@extends('layouts.app.index')


@section('content')
	<router-view>
		<div class="main-loader">
			<div class="loader"></div>
		</div>
	</router-view>
@endsection
