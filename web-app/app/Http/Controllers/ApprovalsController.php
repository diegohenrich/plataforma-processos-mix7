<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApprovalsController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->role === UserRole::Client, 403);

        return redirect()->route('demands.index', ['view' => 'board'])->with('info', 'Aprovações agora ficam registradas dentro da demanda correspondente, junto da etapa, das versões e das respostas do cliente.');
    }
}
