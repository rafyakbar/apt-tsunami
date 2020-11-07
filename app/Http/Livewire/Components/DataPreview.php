<?php

namespace App\Http\Livewire\Components;

use App\Http\Controllers\Api\ChartController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;

class DataPreview extends Component
{
    public $date = '-';

    protected $listeners = [
        'changeDate' => 'setDate'
    ];

    public function setDate($url)
    {
        parse_str( parse_url( $url, PHP_URL_QUERY), $array );
        $this->date = $array['date'];
    }

    private function data()
    {
        return ChartController::data();
    }

    public function mount()
    {
        $this->date = $this->data()
            ->keys()[0];
    }

    public function render()
    {
        $data = $this->data()
            ->get($this->date);

        $keys = array_keys($data[0]);

        return view('livewire.components.data-preview', [
            'data' => $data,
            'keys' => $keys
        ]);
    }
}
