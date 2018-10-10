<div class="section-heading-page">
    <div class="container">
        <div class="row">
            <div class="col-sm-12">
                <ol class="breadcrumb text-center-xs">
                    <li>
                        <a href="{{ url('manager') }}">Manager</a>
                    </li>
                    @if (Request::segment(2))
                        <li>
                            <a href="{{ url('manager/'.Request::segment(2)) }}">{{ slugToTitle(Request::segment(2)) }}</a>
                        </li>
                    @endif
                    @if (Request::segment(3))
                        <li>{{ ucfirst(Request::segment(3)) }}</li>
                    @endif
                </ol>
            </div>
        </div>
    </div>
</div>
