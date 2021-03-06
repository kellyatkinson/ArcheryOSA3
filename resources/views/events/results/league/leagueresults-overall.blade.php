@extends('template.default')

@section ('title')Event Results @endsection

@section('content')


    <link href="{{URL::asset('/plugins/datatables/dataTables.bootstrap4.min.css')}}" rel="stylesheet">
    <link href="{{URL::asset('/plugins/datatables/buttons.bootstrap4.min.css')}}"    rel="stylesheet">
    <link href="{{URL::asset('/plugins/datatables/responsive.bootstrap4.min.css')}}" rel="stylesheet">
    <link href="{{URL::asset('/plugins/datatables/select.bootstrap4.min.css')}}"     rel="stylesheet">

    <div class="row">
		<div class="col-sm-12">
	    	<div class="page-title-box">
                <h4 class="page-title">
                    <a href="/event/results/{{$event->eventurl}}">{{$event->label}}</a>
                    /
                    <a href="javascript:;">Overall</a>
                </h4>
	    	</div>
		</div>
	</div>
    @if (!empty($event->imagedt))
        @include('events.results.templates.event-banners')
    @endif

    <style>
        body {
            line-height: 1;
            font-size: 12px;
        }

        table.dataTable {
            margin-top: 1px !important;
            margin-bottom: 20px !important;
        }
    </style>

	<div class="row">
        <div class="col-lg-12">
            <ul class="nav nav-tabs tabs"></ul>
            <div class="tab-content" style="padding: 0%">
                @foreach ($evententrys as $bowtype => $ee)
                    <div class="tab-pane active" id="{{$bowtype}}">
                        @foreach($ee as $division => $archers)
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered datatable-buttons" cellspacing="0" width="100%">
                                    <thead>
                                        <tr id="tabltr">
                                            <th>{{$division}}</th>
                                            <th>Average</th>
                                            <th>Top 10 Total</th>
                                            <th>Top 10 Points</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($archers as $archer)
                                        <tr class="results">
                                            <th scope="row" width="15%">
                                                <a href="/profile/public/{{$archer->username}}">
                                                    {{ucwords($archer->firstname . ' ' . $archer->lastname)}}
                                                </a>
                                            </th>
                                            <td width="10%">
                                                {{number_format($archer->average->average ?? 0, 2)}}
                                            </td>
                                            <td width="10%">
                                                {{intval($archer->top10->total ?? 0)}}
                                            </td>
                                            <td width="10%">
                                                {{intval($archer->top10points->points ?? 0)}}
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <script src="{{URL::asset('/plugins/datatables/jquery.dataTables.min.js')}}"></script>
    <script src="{{URL::asset('/plugins/datatables/dataTables.bootstrap4.min.js')}}"></script>
    <script src="{{URL::asset('/plugins/datatables/dataTables.keyTable.min.js')}}"></script>
    <script src="{{URL::asset('/plugins/datatables/dataTables.responsive.min.js')}}"></script>
    <script src="{{URL::asset('/plugins/datatables/responsive.bootstrap4.min.js')}}"></script>
    <script src="{{URL::asset('/plugins/datatables/dataTables.select.min.js')}}"></script>

    <script src="{{URL::asset('/plugins/datatables/dataTables.buttons.min.js')}}"></script>
    <script src="{{URL::asset('/plugins/datatables/buttons.bootstrap4.min.js')}}"></script>
    <script src="{{URL::asset('/plugins/datatables/jszip.min.js')}}"></script>
    <script src="{{URL::asset('/plugins/datatables/pdfmake.min.js')}}"></script>
    <script src="{{URL::asset('/plugins/datatables/vfs_fonts.js')}}"></script>
    <script src="{{URL::asset('/plugins/datatables/buttons.html5.min.js')}}"></script>
    <script src="{{URL::asset('/plugins/datatables/buttons.print.min.js')}}"></script>

    <script type="text/javascript">
        $(document).ready(function () {
            //Buttons examples
            var index = $('#tabltr').find('th:last').index();

            var table = $('.datatable-buttons').DataTable({
                lengthChange: false,
                bPaginate: false,
                bInfo : false,
                searching : false,
                "order": [[ index, "desc" ], [index -1, "desc"]]
            });
        });
    </script>



@endsection