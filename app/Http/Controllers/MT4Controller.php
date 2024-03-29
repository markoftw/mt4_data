<?php

namespace App\Http\Controllers;

use App\Events\SendForexData;
use App\Events\SendHistoryData;
use App\Events\SendHistorySignalsData;
use App\User;
use Carbon\Carbon;
use App\Arrow;
use App\CStrength;
use App\Data;
use App\History;
use App\Pair;
use App\Price;
use App\Signal;
use App\Helpers\HQSMS;

class MT4Controller extends Controller
{
    private $cstrength_all = [];
    private $prices_all = [];
    private $arrows_all = [];
    private $pairs = ["AUDCAD", "AUDCHF", "AUDJPY", "AUDNZD", "AUDUSD", "CADCHF", "CADJPY", "CHFJPY", "EURAUD", "EURCAD",
        "EURCHF", "EURGBP", "EURJPY", "EURNZD", "EURUSD", "GBPAUD", "GBPCAD", "GBPCHF", "GBPJPY", "GBPNZD",
        "GBPUSD", "NZDJPY", "NZDUSD", "USDCAD", "USDCHF", "USDJPY"];
    private $razlika = 0;
    private $old_signals = [];

    public function index()
    {
        return view('forex_table');
    }

    public function getData()
    {
        $date = Signal::first()->updated_at;
        $users = User::all()->toArray();
        $data = Data::where('data_type', 'razlika')->first()->data;
        $signals = Signal::with('pair')->get()->toArray();
        $signals[0]['updated_at'] = $date;
        $signals[0]['razlika'] = $data;
        $signals[0]['users'] = $users;
        return $signals;
    }

    public function getHistoryData()
    {
        $history = History::with('pair')->orderBy('id', 'DESC')->get()->take(10)->toArray();
        return $history;
    }

    public function calculate()
    {
        $start_time = microtime(true);

        $this->old_signals = Signal::with('pair')->get()->toArray();
        $this->prices_all = Price::all();
        $this->arrows_all = Arrow::all();
        $this->cstrength_all = CStrength::all();
        $this->razlika = Data::where('data_type', 'razlika')->first()->data;

        $update_counter = 1;
        for ($i = 0; $i < 26; $i++) {
            $bid_price = $this->prices_all[$i]->bid_price;
            if ($bid_price > $this->prices_all[$i]->period_m5) {
                Signal::where('id', $update_counter)->update(['p_m5' => 'BUY']);
            } else {
                Signal::where('id', $update_counter)->update(['p_m5' => 'SELL']);
            }
            if ($bid_price > $this->prices_all[$i]->period_m15) {
                Signal::where('id', $update_counter)->update(['p_m15' => 'BUY']);
            } else {
                Signal::where('id', $update_counter)->update(['p_m15' => 'SELL']);
            }
            if ($bid_price > $this->prices_all[$i]->period_m30) {
                Signal::where('id', $update_counter)->update(['p_m30' => 'BUY']);
            } else {
                Signal::where('id', $update_counter)->update(['p_m30' => 'SELL']);
            }
            if ($bid_price > $this->prices_all[$i]->period_h1) {
                Signal::where('id', $update_counter)->update(['p_h1' => 'BUY']);
            } else {
                Signal::where('id', $update_counter)->update(['p_h1' => 'SELL']);
            }
            // Arrows - 255 = RED, 32768 = GREEN, 65535 = ORANGE
            if ($this->arrows_all[$i]->m5_color == '32768') {
                Signal::where('id', $update_counter)->update(['TD_m5' => 'BUY']);
            } else if ($this->arrows_all[$i]->m5_color == '255') {
                Signal::where('id', $update_counter)->update(['TD_m5' => 'SELL']);
            } else {
                Signal::where('id', $update_counter)->update(['TD_m5' => 'NO']);
            }
            if ($this->arrows_all[$i]->m15_color == '32768') {
                Signal::where('id', $update_counter)->update(['TD_m15' => 'BUY']);
            } else if ($this->arrows_all[$i]->m15_color == '255') {
                Signal::where('id', $update_counter)->update(['TD_m15' => 'SELL']);
            } else {
                Signal::where('id', $update_counter)->update(['TD_m15' => 'NO']);
            }
            if ($this->arrows_all[$i]->h1_color == '32768') {
                Signal::where('id', $update_counter)->update(['TD_h1' => 'BUY']);
            } else if ($this->arrows_all[$i]->h1_color == '255') {
                Signal::where('id', $update_counter)->update(['TD_h1' => 'SELL']);
            } else {
                Signal::where('id', $update_counter)->update(['TD_h1' => 'NO']);
            }
            if ($this->arrows_all[$i]->lts_color == '32768') {
                Signal::where('id', $update_counter)->update(['TD_LTS' => 'BUY']);
            } else if ($this->arrows_all[$i]->lts_color == '255') {
                Signal::where('id', $update_counter)->update(['TD_LTS' => 'SELL']);
            } else {
                Signal::where('id', $update_counter)->update(['TD_LTS' => 'NO']);
            }
            if ($this->arrows_all[$i]->hts_color == '32768') {
                Signal::where('id', $update_counter)->update(['TD_HTS' => 'BUY']);
            } else if ($this->arrows_all[$i]->hts_color == '255') {
                Signal::where('id', $update_counter)->update(['TD_HTS' => 'SELL']);
            } else {
                Signal::where('id', $update_counter)->update(['TD_HTS' => 'NO']);
            }
            if ($this->arrows_all[$i]->ts_color == '32768') {
                Signal::where('id', $update_counter)->update(['TD_TS' => 'BUY']);
            } else if ($this->arrows_all[$i]->ts_color == '255') {
                Signal::where('id', $update_counter)->update(['TD_TS' => 'SELL']);
            } else {
                Signal::where('id', $update_counter)->update(['TD_TS' => 'NO']);
            }
            // EVO
            if ($this->prices_all[$i]->m5_up != '2147483647') {
                Signal::where('id', $update_counter)->update(['EVO_5' => 'BUY']);
            } else {
                Signal::where('id', $update_counter)->update(['EVO_5' => 'SELL']);
            }
            if ($this->prices_all[$i]->m15_up != '2147483647') {
                Signal::where('id', $update_counter)->update(['EVO_15' => 'BUY']);
            } else {
                Signal::where('id', $update_counter)->update(['EVO_15' => 'SELL']);
            }
            $update_counter++;
        }

        for ($j = 0; $j < 26; $j++) {
            $this->cstrength($this->pairs[$j], $j);
        }

        $new = Signal::with('pair')->get()->toArray();
        $different = false;
        for ($i = 0; $i < 26; $i++) {
            if ($new[$i] === $this->old_signals[$i]) {

            } else {
                $different = true;
            }
        }

        if ($different) {
            $count = count($new);
            for ($j = 0; $j < $count; $j++) {
                $this->getSignalStatus($new[$j]);
            }
            $new[0]['updated_at'] = date("Y-m-d H:i:s");
            broadcast(new SendForexData($new));
        }

        $end_time = microtime(true);
        $time_diff = $end_time - $start_time;

        return response(['timer' => round($time_diff, 2)], 200);

    }

    private function getSignalStatus($signal_pair)
    {
        /*$values = array_values($signal_pair);
        $arr_results = [];
        for($i = 2; $i < 15; $i++){
            array_push($arr_results, $values[$i]);
        }*/
        if (isset($signal_pair['pair'])) {
            unset($signal_pair['pair']);
        }
        $count = array_count_values($signal_pair);
        if (isset($count['SELL']) && $count['SELL'] == 13) {
            $history = $this->checkHistory($signal_pair['id']);
            $db = Carbon::parse($history['created_at']);
            $now = Carbon::now();
            $length = $db->diffInHours($now);
            if ($length > 0 || count($history) == 0) {
                $this->storeHistory($signal_pair['id'], 'SELL');
                $hs = History::with('pair')->orderBy('id', 'DESC')->get()->toArray();
                broadcast(new SendHistorySignalsData($hs));
                //Send SMS
            }
        } else if (isset($count['BUY']) && $count['BUY'] == 13) {
            $history = $this->checkHistory($signal_pair['id']);
            $db = Carbon::parse($history['created_at']);
            $now = Carbon::now();
            $length = $db->diffInHours($now);
            if ($length > 0 || count($history) == 0) {
                $this->storeHistory($signal_pair['id'], 'BUY');
                $hs = History::with('pair')->orderBy('id', 'DESC')->get()->toArray();
                broadcast(new SendHistorySignalsData($hs));
                //Send SMS
            }
        } else {

        }
    }

    private function checkHistory($pair)
    {
        $history = Pair::find($pair);
        $results = $history->history()->with('pair')->orderBy('id', 'DESC')->first();
        if (count($results)) {
            return $results->toArray();
        }
    }

    private function storeHistory($pair_id, $order)
    {
        $timestamp = date("H");
        if ($timestamp >= 8 && $timestamp <= 19) {
            $pair = Pair::with('prices')->where('id', $pair_id)->get();
            $pair_price = $pair[0]->prices[0]->bid_price;
            $history_data = [
                'order_type' => $order,
                'bid_price' => $pair_price,
                'difference' => Data::where('data_type', 'razlika')->first()->data
            ];
            $p = Pair::find($pair_id);
            $history = $p->history()->create($history_data);
            if ($history) {
                $hs_data = $this->getHistoryData();
                broadcast(new SendHistoryData($hs_data));
            }
        }
    }

    private function cstrength($item, $id)
    {
        $par_1 = substr($item, 0, 3);
        $par_2 = substr($item, 3, 6);

        $difference = $this->razlika;

        $val_1 = $this->getValues($par_1);
        $val_2 = $this->getValues($par_2);

        // val_1[0] == GBP_NOW, val_1[1] == GBP_THEN
        // val_2[0] == JPY_NOW, val_2[1] == JPY_THEN

        $difference_now = abs($val_1[0] - $val_2[0]);
        $difference_then = abs($val_1[1] - $val_2[1]);

        if ($difference_now / $difference > $difference_then && $val_1[0] >= $val_1[1] && $val_2[0] <= $val_2[1] && $val_1[0] > $val_2[0]
            || $difference_now * $difference < $difference_then && $val_1[0] >= $val_1[1] && $val_2[0] <= $val_2[1] && $val_2[0] > $val_1[0]
        ) {
            Signal::where('id', ++$id)->update(['cs_signal' => 'BUY']);
        } else if ($difference_now / $difference > $difference_then && $val_2[0] >= $val_2[1] && $val_1[0] <= $val_1[1] && $val_2[0] > $val_1[0]
            || $difference_now * $difference < $difference_then && $val_2[0] >= $val_2[1] && $val_1[0] <= $val_1[1] && $val_1[0] > $val_2[0]
        ) {
            Signal::where('id', ++$id)->update(['cs_signal' => 'SELL']);
        } else {
            Signal::where('id', ++$id)->update(['cs_signal' => 'NO']);
        }
    }

    private function getValues($value)
    {
        switch ($value) {
            case "GBP":
                $arr = [$this->cstrength_all[0]->gbp_now, $this->cstrength_all[0]->gbp_then];
                return $arr;
                break;
            case "JPY":
                $arr = [$this->cstrength_all[0]->jpy_now, $this->cstrength_all[0]->jpy_then];
                return $arr;
                break;
            case "EUR":
                $arr = [$this->cstrength_all[0]->eur_now, $this->cstrength_all[0]->eur_then];
                return $arr;
                break;
            case "USD":
                $arr = [$this->cstrength_all[0]->usd_now, $this->cstrength_all[0]->usd_then];
                return $arr;
                break;
            case "NZD":
                $arr = [$this->cstrength_all[0]->nzd_now, $this->cstrength_all[0]->nzd_then];
                return $arr;
                break;
            case "CHF":
                $arr = [$this->cstrength_all[0]->chf_now, $this->cstrength_all[0]->chf_then];
                return $arr;
                break;
            case "AUD":
                $arr = [$this->cstrength_all[0]->aud_now, $this->cstrength_all[0]->aud_then];
                return $arr;
                break;
            case "CAD":
                $arr = [$this->cstrength_all[0]->cad_now, $this->cstrength_all[0]->cad_then];
                return $arr;
                break;
            default:
                return false;
                break;
        }
    }
}
