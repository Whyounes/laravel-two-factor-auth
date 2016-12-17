@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-heading">2 Step Verification</div>
                    <div class="panel-body">
                        {{-- List session errors --}}
                        @if (count($errors) > 0)
                            <div class="alert alert-danger ">
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span></button>
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form class="form-horizontal" role="form" method="POST" action="{{ url(\Whyounes\TFAuth\Controllers\TFAController::getVerificationCodeRoute()) }}">
                            {{ csrf_field() }}

                            <div class="form-group">
                                <label for="code" class="col-md-4 control-label">Four digits code</label>

                                <div class="col-md-6">
                                    <input id="code" type="text" class="form-control" name="code"
                                           value="{{ old('code') }}" required autofocus>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-md-8 col-md-offset-4">
                                    <button type="submit" class="btn btn-primary">
                                        Verify
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection