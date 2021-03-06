@extends('template.default')

@section ('title') Exports @endsection

@section('content')

    <div class="page-title-box">
        <h4 class="page-title">
            <a href="/events/manage/{{$event->eventurl}}">{{ucwords($event->label)}}</a>
            /
            <a href="javascript:;">Event Entries</a>
        </h4>
    </div>

    @include('template.alerts')

    <div class="row">
        <div class="offset-2 col-8">
            <div class="card-box table-responsive">
                <h4 class="m-t-0 header-title">Event Exports</h4>
                <p class="text-muted font-14 m-b-30">
                  <br>
                </p>
                <br>

                <meta name="csrf-token" content="{{ csrf_token() }}">
                <meta name="eventurl" content="{{ $event->eventurl}}">

                <table class="table table-striped table-bordered" cellspacing="0" width="100%">
                    <thead>
                    <tr>
                        <th>Export</th>
                        <th>File</th>
                    </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td><strong>Ianseo Entries</strong></td>
                            <td>
                                <a href="/event/export/entries/ianseo/{{$event->eventurl}}/csv"><i class="fa fa-file-excel-o fa-3x"></i></a>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Entries</strong></td>
                            <td>
                                <a href="/event/export/entries/{{$event->eventurl}}/csv"><i class="fa fa-file-excel-o fa-3x"></i></a>
                                &nbsp;
                                <a href="/event/export/entries/{{$event->eventurl}}/pdf"><i class="fa fa-file-pdf-o fa-3x"></i></a>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Results</strong></td>
                            <td>
                                @foreach ($eventcompetitions as $ec)

                                    <a href="/event/export/results/{{$event->eventurl}}/{{$ec->eventcompetitionid}}"><i class="fa fa-file-excel-o fa-3x"></i>
                                        <span>{!! $ec->label . ' - ' . date('d F Y', strtotime($ec->date))!!}</span>
                                    </a><br><br>
                                @endforeach
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Target Allocations</strong></td>
                            <td>
                                <a href="/event/export/targetallocations/{{$event->eventurl}}"><i class="fa fa-file-pdf-o fa-3x"></i></a>
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>
        </div>
    </div>


@endsection