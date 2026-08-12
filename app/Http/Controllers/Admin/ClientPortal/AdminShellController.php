<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AdminShellController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.client-portal.app');
    }
}