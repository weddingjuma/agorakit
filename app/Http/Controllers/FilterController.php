<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use \App\Group;

class FilterController extends Controller
{
    public function all(Request $request)
    {
        $request->session()->put('filter', 'all');
        return redirect()->route('index');
    }

    public function my(Request $request)
    {
        $request->session()->put('filter', 'my');
    }

    public function group(Request $request, Group $group)
    {
        $request->session()->put('filter', $group->id);
    }
}
