<?php

namespace App\Http\Controllers;

use App\DrWorksheet;
use App\DrPatient;
use App\DrSample;
use App\DrSampleView;
use App\User;

use App\Lookup;
use App\MiscDr;

use Illuminate\Http\Request;

class DrWorksheetController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($state=0, $date_start=NULL, $date_end=NULL, $worksheet_id=NULL)
    {
        $worksheets = DrWorksheet::with(['creator', 'reviewer'])->withCount(['sample'])
            ->when($state, function ($query) use ($state){
                return $query->where('status_id', $state);
            })
            ->when($date_start, function($query) use ($date_start, $date_end){
                if($date_end)
                {
                    return $query->whereDate('dr_worksheets.created_at', '>=', $date_start)
                    ->whereDate('dr_worksheets.created_at', '<=', $date_end);
                }
                return $query->whereDate('dr_worksheets.created_at', $date_start);
            })
            ->orderBy('dr_worksheets.created_at', 'desc')
            ->get();

        $data = Lookup::worksheet_lookups();
        $data['worksheets'] = $worksheets;
        $data['myurl'] = url('dr_worksheet/index/' . $state . '/');
        return view('tables.dr_worksheets', $data)->with('pageTitle', 'Worksheets');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($extraction_worksheet_id)
    {
        // $samples = DrSample::selectRaw("dr_samples.*")
        //                 ->join('drug_resistance_reasons', 'drug_resistance_reasons.id', '=', 'dr_samples.dr_reason_id')
        //                 ->orderBy('drug_resistance_reasons.rank', 'asc')
        //                 ->whereNull('worksheet_id')
        //                 ->where('receivedstatus', 1)
        //                 ->limit(16)
        //                 ->get();

        // $samples->load(['patient.facility']);
        // $data['dr_samples'] = $samples;

        $data = Lookup::get_dr();
        $data = array_merge($data, MiscDr::get_worksheet_samples($extraction_worksheet_id));
        return view('forms.dr_worksheets', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $dr_worksheet = new DrWorksheet;
        $dr_worksheet->fill($request->except(['_token']));
        $dr_worksheet->save();

        $data = MiscDr::get_worksheet_samples($dr_worksheet->extraction_worksheet_id);
        $samples = $data['samples'];

        foreach ($samples as $s) {
            $sample = DrSample::find($s->id);
            $sample->worksheet_id = $dr_worksheet->id;
            $sample->save();
        }
        return redirect('dr_worksheet/print/' . $dr_worksheet->id);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\DrWorksheet  $drWorksheet
     * @return \Illuminate\Http\Response
     */
    public function show(DrWorksheet $drWorksheet, $print=false)
    {
        $data = Lookup::get_dr();
        // $data['samples'] = $drWorksheet->sample;
        $data['samples'] = DrSample::where(['worksheet_id' => $drWorksheet->id])->orderBy('id', 'asc')->get();
        $data['date_created'] = $drWorksheet->my_date_format('created_at', "Y-m-d");
        if($print) $data['print'] = true;
        return view('worksheets.dr_worksheet', $data);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\DrWorksheet  $drWorksheet
     * @return \Illuminate\Http\Response
     */
    public function print(DrWorksheet $worksheet)
    {
        return $this->show($worksheet, true);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\DrWorksheet  $drWorksheet
     * @return \Illuminate\Http\Response
     */
    public function edit(DrWorksheet $drWorksheet)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\DrWorksheet  $drWorksheet
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, DrWorksheet $drWorksheet)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\DrWorksheet  $drWorksheet
     * @return \Illuminate\Http\Response
     */
    public function destroy(DrWorksheet $drWorksheet)
    {
        //
    }

    public function upload(DrWorksheet $worksheet)
    {
        $worksheet->load(['creator']);
        $users = User::where('user_type_id', 1)->get();
        return view('forms.upload_dr_results', ['worksheet' => $worksheet, 'users' => $users, 'type' => 'dr'])->with('pageTitle', 'Worksheet Upload');        
    }

    public function save_results(Request $request, DrWorksheet $worksheet)
    {
        $worksheet->fill($request->except(['_token', 'upload']));
        $file = $request->upload->path();
        
        $zip = new \ZipArchive;
        $path = storage_path('app/public/results/dr/' . $worksheet->id . '/');
        if(is_dir($path)) MiscDr::delete_folder($path);
        mkdir($path, 0777, true);


        $p = $request->upload->store('public/results/dr/' . $worksheet->id );

        if($zip->open($file) === TRUE){
            $zip->extractTo($path);
            $zip->close();
            $worksheet->save();
            session(['toast_message' => 'The worksheet results has been uploaded.']);
        }
        else{
            session([
                'toast_message' => 'The worksheet results could not be uploaded. Please try again.', 
                'toast_error' => 1
            ]);
            return back();
        }
        
        return redirect('dr_worksheet');
    }

    public function cancel(DrWorksheet $worksheet)
    {
        if($worksheet->status_id != 1){
            session(['toast_message' => 'The worksheet is not eligible to be cancelled.']);
            session(['toast_error' => 1]);
            return back();
        }
        DrSample::where('worksheet_id', $worksheet->id)->update(['worksheet_id' => null, 'datetested' => null, ]);
        $worksheet->dateuploaded = $worksheet->uploadedby = null;
        $worksheet->datecancelled = date('Y-m-d');
        $worksheet->cancelledby = auth()->user()->id;
        $worksheet->save();

        session(['toast_message' => 'The worksheet has been cancelled.']);
        return redirect('dr_worksheet');
    }


    public function cancel_upload(DrWorksheet $worksheet)
    {
        if($worksheet->status_id != 2){
            session(['toast_message' => 'The worksheet upload cannot be reversed.']);
            session(['toast_error' => 1]);
            return back();
        }

        if($worksheet->uploadedby != auth()->user()->id && auth()->user()->user_type_id != 0){
            session(['toast_message' => 'Only the user who uploaded the results can reverse the upload.']);
            session(['toast_error' => 1]);
            return back();
        }

        $path = storage_path('app/public/results/dr/' . $worksheet->id . '/');
        MiscDr::delete_folder($path);
        session(['toast_message' => 'The worksheet upload has been reversed.']);
        return redirect('dr_worksheet/upload/' . $worksheet->id);
    }
}
