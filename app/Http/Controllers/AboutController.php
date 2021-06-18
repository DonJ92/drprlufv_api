<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App;

class AboutController extends Controller
{
    /**
     *
     * @return \Illuminate\Http\Response
     */
    public function privacy(Request $request)
    {
        return view('about.privacy');
    }

   	/**
     *
     * @return \Illuminate\Http\Response
     */
    public function terms(Request $request)
    {
        return view('about.terms');
    }
}