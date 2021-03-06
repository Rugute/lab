<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ViewModel extends Model
{

    public function facility()
    {
        return $this->belongsTo('App\Facility');
    }

    public function view_facility()
    {
        return $this->belongsTo('App\ViewFacility', 'facility_id');
    }

    public function creator()
    {
        return $this->belongsTo('App\User', 'user_id');
    }

    public function my_date_format($value)
    {
        if($this->$value) return date('d-M-Y', strtotime($this->$value));

        return '';
    }

    public function my_time_format($value)
    {
        if($this->$value) return date('d-M-Y H:i:s', strtotime($this->$value));

        return '';
    }


    public function scopeSample($query, $facility, $patient, $datecollected)
    {
        $min_date = date('Y-m-d', strtotime($datecollected . ' -3 days'));
        $max_date = date('Y-m-d', strtotime($datecollected . ' +3 days'));
        return $query->where(['facility_id' => $facility, 'patient' => $patient])
                        ->whereBetween('datecollected', [$min_date, $max_date]);
    }

    public function scopeExisting($query, $data_array)
    {
        $min_date = date('Y-m-d', strtotime($data_array['datecollected'] . ' -3 days'));
        $max_date = date('Y-m-d', strtotime($data_array['datecollected'] . ' +3 days'));
        return $query->where(['facility_id' => $data_array['facility_id'], 'patient' => $data_array['patient']])
                    ->whereBetween('datecollected', [$min_date, $max_date]);
    }

    public function scopePatient($query, $facility, $patient)
    {
        return $query->where(['facility_id' => $facility, 'patient' => $patient]);
    }

    public function getIsReadyAttribute()
    {
        if($this->repeatt == 0){
            if(in_array(env('APP_LAB'), \App\Lookup::$double_approval)){
                if(($this->dateapproved && $this->dateapproved2) || ($this->approvedby && $this->approvedby2)){
                    return true;
                }
            }
            else{
                if($this->dateapproved || $this->approvedby) return true;
            }
        }
        return false;
    }

    public function get_link($attr)
    {
        $user = auth()->user();
        $c = get_class($this);
        $c = strtolower($c);
        $c = str_replace_first('app\\', '', $c);

        $pre = '';
        if(str_contains($c, 'viral')) $pre = 'viral';
        $user = auth()->user();

        if(str_contains($attr, 'worksheet')) $url = url($pre . 'worksheet/approve/' . $this->$attr);
        else if(str_contains($attr, 'sample') || (str_contains($c, 'sample') && $attr == 'id')) $url = url($pre . 'sample/runs/' . $this->$attr);
        else{
            $a = explode('_', $attr);
            $url = url($pre . $a[0] . '/' . $this->$attr);
        }

        if($attr == 'id' && (!$user || ($user && $user->user_type_id == 5))) return null;

        if(str_contains($attr, ['worksheet', 'sample']) && (!$user || ($user && $user->user_type_id == 5))) return $this->$attr;

        $text = $this->$attr;

        if(str_contains($attr, 'patient')) $text = $this->patient;

        $full_link = "<a href='{$url}' target='_blank'> {$text} </a>";

        return $full_link;
    }


    /**
     * Get the patient's gender
     *
     * @return string
     */
    public function getGenderAttribute()
    {
        if($this->sex == 1){ return "Male"; }
        else if($this->sex == 2){ return "Female"; }
        else{ return "No Gender"; }
    }


    /**
     * Get the sample's received status name
     *
     * @return string
     */
    public function getReceivedAttribute()
    {
        if($this->receivedstatus == 1){ return "Accepted"; }
        else if($this->receivedstatus == 2){ return "Rejected"; }
        else{ return ""; }
    }
}
