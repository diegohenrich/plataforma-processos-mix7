<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TeamMemberController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);
        $professionals = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('role', UserRole::Professional->value)
            ->orderBy('name')
            ->paginate(20);

        $clients = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('role', UserRole::Client->value)
            ->orderBy('name')
            ->paginate(20, ['*'], 'clients_page');

        return view('team.index', compact('professionals', 'clients'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'max:200'],
        ]);

        User::create([
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'organization_id' => $request->user()->organization_id,
            'role' => UserRole::Professional,
            'is_active' => true,
        ]);

        return redirect()->route('team.index')->with('success', 'Profissional adicionado à equipe.');
    }

    public function storeClient(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);
        $data = $request->validate([
            'client_name' => ['required', 'string', 'max:160'],
            'client_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'client_password' => ['required', 'string', 'min:12', 'max:200'],
        ]);

        User::create([
            'name' => trim($data['client_name']),
            'email' => mb_strtolower(trim($data['client_email'])),
            'password' => $data['client_password'],
            'organization_id' => $request->user()->organization_id,
            'role' => UserRole::Client,
            'is_active' => true,
        ]);

        return redirect()->route('team.index')->with('success', 'Conta de cliente criada. Compartilhe o acesso com a pessoa por canal seguro.');
    }
}
