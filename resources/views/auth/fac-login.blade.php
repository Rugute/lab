@extends('layouts.auth')

@section('css_scripts')

<link href="{{ asset('css/select2/select2.min.css') }}" rel="stylesheet" type="text/css">

@endsection

@section('content')

@isset($login_error)
    {{ $login_error }}
@endisset

<div class="row">
        <div class="col-md-12">
            <div class="hpanel" style="width: 430px;">
                <div class="panel-body" style="padding: 20px;">
                    <form class="form-horizontal" method="POST" action="{{ url('login/facility') }}">
                        {{ csrf_field() }}
                        <div class="form-group" style="padding-bottom: -;padding-right: 20px;padding-left: 20px;margin-bottom: 0px;">
                            <label class="control-label" for="email" style="color: black;">Facility:</label>
                                <select class="form-control" required name="facility_id" id="facility_id">

                                  <option value=""> Select One </option>
                                  @foreach ($facilities as $facility)
                                      <option value="{{ $facility->id }}"

                                      @if (isset($sample) && $sample->patient->facility_id == $facility->id)
                                          selected
                                      @endif

                                      > {{ $facility->name }}
                                      </option>
                                  @endforeach

                                </select>
                            @if ($errors->has('email'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('email') }}</strong>
                                </span>
                            @endif
                        </div>
                        <div class="form-group" style="padding-bottom: -;padding-right: 20px;padding-left: 20px;margin-bottom: 0px;">
                            <label class="control-label" for="password" style="color: black;">Batch No:</label>
                            <div class="input-group m-b" style="margin-bottom: 0px;">
                                <span class="input-group-addon"><span class="pe-7s-unlock"></span></span>
                                <input type="text" title="Please enter your password" placeholder="Batch" required="" value="" name="batch_no" id="batch_no" class="form-control">
                            </div>
                            @if ($errors->has('password'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('password') }}</strong>
                                </span>
                            @endif
                        </div>
                        
                        <button type="submit" class="btn btn-danger btn-block" style="background-color: #16A085;border-color: #16A085;margin-top: 2em;">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')

<script src="{{ asset('js/select2/select2.full.min.js') }}"></script>
<script type="text/javascript">
    $(document).ready(function(){

        $("#facility_id").select2();

    });

</script>

@endsection