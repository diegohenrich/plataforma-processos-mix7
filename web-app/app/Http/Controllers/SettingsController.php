<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Demand;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('create', Demand::class);

        return view('settings.index', [
            'canManageAi' => $request->user()->role === UserRole::AgencyOwner,
        ]);
    }
}
