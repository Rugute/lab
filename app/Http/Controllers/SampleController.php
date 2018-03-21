<?php

namespace App\Http\Controllers;

use App\Sample;
use App\Patient;
use App\Mother;
use App\Batch;
use App\Facility;
use App\Lookup;
use DB;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SampleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $data = Lookup::samples_form();
        return view('forms.samples', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $submit_type = $request->input('submit_type');

        if($submit_type == "cancel"){
            $batch_no = session()->pull('batch_no');
            $this->clear_session();
            DB::table('batches')->where('id', $batch_no)->update(['input_complete' => 1]);
            return redirect()->route('sample.create');
        }

        $batch_no = session('batch_no', 0);
        $batch_dispatch = session('batch_dispatch', 0);

        $ddispatched = $request->input('datedispatchedfromfacility');

        if($batch_no == 0){
            $facility_id = $request->input('facility_id');
            $facility = Facility::find($facility_id);
            session(['facility_name' => $facility->name, 'facility_id' => $facility_id, 'batch_total' => 0, 'batch_received' => $request->input('datereceived')]);

            $batch = new Batch;
            $batch->user_id = auth()->user()->id;
            $batch->lab_id = auth()->user()->lab_id;
            $batch->facility_id = $facility_id;
            $batch->datereceived = $request->input('datereceived');

            if($ddispatched == null){
                session(['batch_dispatch' => 0]);
            }
            else{
                session(['batch_dispatch' => 1, 'batch_dispatched' => $ddispatched]);
                $batch->datedispatchedfromfacility = $ddispatched;
            }

            if(auth()->user()->user_type_id == 1 || auth()->user()->user_type_id == 4){
                $batch->received_by = auth()->user()->id;
                $batch->site_entry = 1;
            }

            if(auth()->user()->user_type_id == 5){
                $batch->site_entry = 2;
            }

            $batch->save();
            $batch_no = $batch->id;
            session(['batch_no' => $batch_no]);
        }

        if($ddispatched && $batch_dispatch == 0){
            DB::table('batches')->where('id', $batch_no)->update(['datedispatchedfromfacility' => $ddispatched]);
            session(['batch_dispatch' => 1]);
        }


        $new_patient = $request->input('new_patient');

        if($new_patient == 0){

            $repeat_test = Sample::where(['patient_id' => $request->input('patient_id'),
            'batch_id' => $batch_no])->first();

            if($repeat_test){
                return redirect()->route('sample.create');
            }

            $data = $request->except(['_token', 'patient_name', 'submit_type', 'facility_id', 'sex', 'sample_months', 'sample_weeks', 'entry_point', 'caregiver_phone', 'hiv_status', 'patient', 'new_patient', 'datereceived', 'datedispatchedfromfacility', 'dob', 'ccc_no']);
            $sample = new Sample;
            $sample->fill($data);
            $sample->batch_id = $batch_no;
            // $sample->age = $request->input('sample_months') + ( $request->input('sample_weeks') / 4 );

            $dc = Carbon::createFromFormat('Y-m-d', $request->input('datecollected'));
            $dob = Carbon::parse( $request->input('dob') );
            $months = $dc->diffInMonths($dob);
            $weeks = $dc->diffInWeeks($dob->copy()->addMonths($months));
            $sample->age = $months + ($weeks / 4);
            $sample->save();
        }

        else{

            $data = $request->only(['hiv_status', 'entry_point', 'facility_id', 'ccc_no']);
            $mother = new Mother;
            $mother->fill($data);
            $mother->save();

            $data = $request->only(['sex', 'patient_name', 'facility_id', 'caregiver_phone', 'patient', 'dob']);
            $patient = new Patient;
            $patient->fill($data);
            $patient->mother_id = $mother->id;
            $patient->save();

            // $patient_age = $request->input('sample_months') + ( $request->input('sample_weeks') / 4 );
            // $dt = Carbon::today();
            // $dt->subMonths($request->input('sample_months'));
            // $dt->subWeeks($request->input('sample_weeks'));
            // $patient->dob = $dt->toDateString();

            $dc = Carbon::createFromFormat('Y-m-d', $request->input('datecollected'));
            $dob = Carbon::createFromFormat('Y-m-d', $request->input('dob'));
            $months = $dc->diffInMonths($dob);
            $weeks = $dc->diffInWeeks($dob->copy()->addMonths($months));

            $patient_age = $months + ($weeks / 4);

            $data = $request->except(['_token', 'patient_name', 'submit_type', 'facility_id', 'sex', 'sample_months', 'sample_weeks', 'entry_point', 'caregiver_phone', 'hiv_status', 'patient', 'new_patient', 'datereceived', 'datedispatchedfromfacility', 'dob', 'ccc_no']);
            $sample = new Sample;
            $sample->fill($data);
            $sample->patient_id = $patient->id;
            $sample->age = $patient_age;
            $sample->batch_id = $batch_no;
            $sample->save();

        }

        $submit_type = $request->input('submit_type');

        if($submit_type == "release"){
            $batch_no = session()->pull('batch_no');
            $this->clear_session();
            DB::table('batches')->where('id', $batch_no)->update(['input_complete' => 1]);
        }
        else if($submit_type == "add"){

        }

        $batch_total = session('batch_total', 0) + 1;

        session(['batch_total' => $batch_total]);

        if($batch_total == 10){
            $batch_no = session()->pull('batch_no', $batch_no);
            $this->clear_session();
            DB::table('batches')->where('id', $batch_no)->update(['input_complete' => 1, 'batch_full' => 1]);
        }


        return redirect()->route('sample.create');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Sample  $sample
     * @return \Illuminate\Http\Response
     */
    public function show(Sample $sample)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Sample  $sample
     * @return \Illuminate\Http\Response
     */
    public function edit(Sample $sample)
    {
        $sample->load(['patient.mother', 'batch']);
        $data = Lookup::samples_form();
        $data['sample'] = $sample;
        return view('forms.samples', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Sample  $sample
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Sample $sample)
    {
        $data = $request->except(['_token', 'patient_name', 'submit_type', 'facility_id', 'sex', 'sample_months', 'sample_weeks', 'entry_point', 'caregiver_phone', 'hiv_status', 'patient', 'new_patient', 'datereceived', 'datedispatchedfromfacility', 'dob', 'ccc_no']);
        $sample->fill($data);
        // $sample->save();

        $batch = Batch::find($sample->batch_id);
        $batch->fill($request->only(['datereceived', 'datedispatchedfromfacility', 'facility_id']));
        $batch->save();

        $new_patient = $request->input('new_patient');

        if($new_patient == 0)
        {
            $data = $request->only(['sex', 'patient_name', 'facility_id', 'caregiver_phone', 'patient', 'dob']);
            $patient = Patient::find($sample->patient_id);
            $patient->fill($data);
            $patient->save();

            $data = $request->only(['hiv_status', 'entry_point', 'facility_id', 'ccc_no']);
            $mother = Mother::find($patient->mother_id);
            $mother->fill($data);
            $mother->save();
        }
        else
        {
            $data = $request->only(['hiv_status', 'entry_point', 'facility_id', 'ccc_no']);
            $mother = new Mother;
            $mother->fill($data);
            $mother->save();

            $data = $request->only(['sex', 'patient_name', 'facility_id', 'caregiver_phone', 'patient', 'dob']);
            $patient = new Patient;
            $patient->fill($data);
            $patient->mother_id = $mother->id;
            $patient->save();
        }

        $dc = Carbon::createFromFormat('Y-m-d', $request->input('datecollected'));
        $dob = Carbon::createFromFormat('Y-m-d', $request->input('dob'));
        $months = $dc->diffInMonths($dob);
        $weeks = $dc->diffInWeeks($dob->copy()->addMonths($months));
        $patient_age = $months + ($weeks / 4);
        $sample->age = $patient_age;
        $sample->patient_id = $patient->id;
        $sample->save();

        $site_entry_approval = session()->pull('site_entry_approval');

        if($site_entry_approval){
            return redirect('batch/site_approval/' . $batch->id);
        }

        return redirect('batch/' . $batch->id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Sample  $sample
     * @return \Illuminate\Http\Response
     */
    public function destroy(Sample $sample)
    {
        $sample->delete();
        return back();
    }

    public function new_patient($patient, $facility_id)
    {
        $patient = Patient::where(['facility_id' => $facility_id, 'patient' => $patient])->first();
        $data;
        if($patient){
            $mother = $patient->mother;
            $data[0] = 0;
            $data[1] = $patient->toArray();
            $data[2] = $mother->toArray();

            $sample = Sample::select('id')->where(['patient_id' => $patient->id, 'result' => 2])->first();
            if($sample){
                $data[3] = ['previous_positive' => 1];
            }
            else{
                $data[3] = ['previous_positive' => 0];
            }
        }
        else{
            $data[0] = 1;
        }
        return $data;
    }

    public function release_redraw(Sample $sample)
    {
        $sample->repeatt = 0;
        $sample->result = 5;
        $sample->approvedby = auth()->user()->id;
        $sample->dateapproved = date('Y-m-d');
        $sample->save();
        $my = new \App\Misc;
        $my->check_batch($sample->batch_id);
        return back();
    }

    public function release_redraws(Request $request)
    {
        $samples_input = $request->input('samples');
        // DB::table('samples')->whereIn('id', $samples_input)->update(['repeatt' => 0, 'result' => 5, 'approvedby' => auth()->user()->id, 'dateapproved' => date('Y-m-d')]);

        $samples = Sample::whereIn('id', $samples_input)->get();

        foreach ($samples as $key => $sample) {
            $this->release_redraw($sample);
        }
        return back();
    }

    private function clear_session(){
        session()->forget('batch_no');
        session()->forget('batch_total');
        session()->forget('batch_dispatch');
        session()->forget('batch_dispatched');
        session()->forget('batch_received');
        session()->forget('facility_id');
        session()->forget('facility_name');
    }
}
