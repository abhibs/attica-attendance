<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminMessengerController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Admin $currentAdmin */
        $currentAdmin = $request->user('admin');
        $selectedAdminId = (int) $request->query('user');
        $admins = Admin::query()
            ->where('id', '!=', $currentAdmin->id)
            ->orderBy('name')
            ->orderBy('email')
            ->get(['id', 'name', 'email', 'role', 'position']);
        $unreadCounts = AdminMessage::query()
            ->selectRaw('sender_admin_id, COUNT(*) as unread_count')
            ->where('receiver_admin_id', $currentAdmin->id)
            ->whereNull('read_at')
            ->groupBy('sender_admin_id')
            ->pluck('unread_count', 'sender_admin_id');

        $admins->each(function (Admin $admin) use ($unreadCounts): void {
            $admin->unread_messages_count = (int) ($unreadCounts[$admin->id] ?? 0);
            $admin->display_role_label = $this->roleLabel($admin->role);
        });

        $selectedAdmin = $admins->firstWhere('id', $selectedAdminId) ?: $admins->first();
        $messages = collect();

        if ($selectedAdmin instanceof Admin) {
            AdminMessage::query()
                ->where('sender_admin_id', $selectedAdmin->id)
                ->where('receiver_admin_id', $currentAdmin->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            $messages = AdminMessage::query()
                ->with(['sender:id,name,email', 'receiver:id,name,email'])
                ->where(function ($query) use ($currentAdmin, $selectedAdmin): void {
                    $query->where('sender_admin_id', $currentAdmin->id)
                        ->where('receiver_admin_id', $selectedAdmin->id);
                })
                ->orWhere(function ($query) use ($currentAdmin, $selectedAdmin): void {
                    $query->where('sender_admin_id', $selectedAdmin->id)
                        ->where('receiver_admin_id', $currentAdmin->id);
                })
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();
        }

        return view('admin.messenger.index', [
            'admins' => $admins,
            'currentAdmin' => $currentAdmin,
            'messages' => $messages,
            'selectedAdmin' => $selectedAdmin,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Admin $currentAdmin */
        $currentAdmin = $request->user('admin');
        $data = $request->validate([
            'receiver_admin_id' => ['required', 'integer', 'exists:admins,id'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $receiverAdminId = (int) $data['receiver_admin_id'];

        if ($receiverAdminId === (int) $currentAdmin->id) {
            throw ValidationException::withMessages([
                'receiver_admin_id' => 'You cannot send a message to yourself.',
            ]);
        }

        AdminMessage::query()->create([
            'sender_admin_id' => $currentAdmin->id,
            'receiver_admin_id' => $receiverAdminId,
            'body' => trim((string) $data['body']),
        ]);

        return redirect()
            ->route('admin-messenger', ['user' => $receiverAdminId])
            ->with('status', 'Message sent.');
    }

    private function roleLabel(?string $role): string
    {
        return match (strtolower(trim((string) $role))) {
            Admin::ROLE_HR_ADMIN, 'hradmin', '' => 'HRManager',
            'md' => 'MD',
            Admin::ROLE_HIRING => 'Hiring',
            Admin::ROLE_JOINING => 'Joining',
            Admin::ROLE_OPENING => 'Opening',
            Admin::ROLE_ACCOUNTS => 'Accounts',
            default => ucwords(str_replace('_', ' ', trim((string) $role))),
        };
    }
}
