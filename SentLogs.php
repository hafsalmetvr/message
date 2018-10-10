<?php

namespace App\Services\SMS\Logger;

use Carbon\Carbon;

use App\Models\SmsBroadcast;

class SentLogs {
    public function __construct()
    {
        $this->initialize();

        $this->yesterday = SmsBroadcast::where('user_id', auth()->user()->id)
                                 ->whereDate('created_at', $this->dayBefore)->count();
    }

    public function initialize()
    {
        $this->dayBefore = date("Y-m-d", strtotime( '-1 days' ) );
        $this->sevenDayStartDate = Carbon::now()->subDays(7)->toDateString();
        $this->monthStartDate = Carbon::now()->startOfMonth();
        $this->currentDay = Carbon::now()->toDateTimeString();
        $this->dayBeforeYesterday = Carbon::now()->subDays(2)->toDateString();
        $this->lastMonthStartDate = new Carbon('first day of last month');
        $this->lastMonthEndDate = new Carbon('last day of last month');
        $this->sevenDayBeforeLast7Start = Carbon::now()->subDays(14)->toDateString();
        $this->sevenDayBeforeLastSevenEnd = Carbon::now()->subDays(8)->toDateString();
    }


    public function getToday()
    {
        $today = SmsBroadcast::where('user_id', auth()->user()->id)
                                    ->whereRaw('Date(created_at) = CURDATE()')->count();

        $todayChange = $this->yesterday == 0 ? round($today * 100, 2) : round(($today - $this->yesterday)/$this->yesterday * 100, 2);

        return $todayChange < 0 ? array('value' => $today, 'change' => array('value' => abs($todayChange), 'negative' => 'true')) : array('value' => $today, 'change' => array('value' => $todayChange ));
    }

    public function getYesterday() 
    {
        $dayBeforeSterday = SmsBroadcast::where('user_id', auth()->user()->id)
                                        ->whereDate('created_at', $this->dayBeforeYesterday)->count();

        $yesterdayChange = $dayBeforeSterday == 0 ? round($this->yesterday * 100, 2) : round(($this->yesterday - $dayBeforeSterday)/$dayBeforeSterday * 100, 2);

        return $yesterdayChange < 0 ? array('value' => $this->yesterday, 'change' => array('value' => abs($yesterdayChange), 'negative' => 'true')) : array('value' => $this->yesterday, 'change' => array('value' => $yesterdayChange));
    }

    public function getLast7Day()
    {
        $last7Day = SmsBroadcast::where('user_id', auth()->user()->id)
                                ->whereBetween('created_at', [$this->sevenDayStartDate, $this->currentDay])->count();

        $beforeLast7Day = SmsBroadcast::where('user_id', auth()->user()->id)
                                      ->whereBetween('created_at', [$this->sevenDayBeforeLast7Start, $this->sevenDayBeforeLastSevenEnd])->count();   

        $sevenDayChange = $beforeLast7Day == 0 ? round($last7Day * 100, 2) : round(($last7Day - $beforeLast7Day)/$beforeLast7Day * 100, 2);

        return $sevenDayChange < 0 ? array('value' => $last7Day, 'change' => array('value' => abs($sevenDayChange), 'negative' => 'true')) : array('value' => $last7Day, 'change' => array('value' => $sevenDayChange));  
    }

    public function getMonth()
    {
        $lastMonth = SmsBroadcast::where('user_id', auth()->user()->id)
                                 ->whereBetween('created_at', [$this->lastMonthStartDate, $this->lastMonthEndDate])->count();

        $month = SmsBroadcast::where('user_id', auth()->user()->id)
                             ->whereBetween('created_at', [$this->monthStartDate, $this->currentDay])->count();

        $monthChange = $lastMonth == 0 ? round($month * 100, 2) : round(($month - $lastMonth)/$lastMonth * 100, 2);
        
        return $monthChange < 0 ? array('value' => $month, 'change' => array('value' => abs($monthChange), 'negative' => 'true')) : array('value' => $month, 'change' => array('value' => $monthChange));
    }
}