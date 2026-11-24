<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $s) {}

    public function index(Request $r)
    {
        return $this->s->datos($r->user()->load('rol'));
    }
}
