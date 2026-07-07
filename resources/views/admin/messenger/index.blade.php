@extends('admin.layout.app')

@section('content')
    <style>
        body.messenger-page-active {
            overflow: hidden;
        }

        body.messenger-page-active .main-wrapper {
            height: calc(100vh - 70px);
            min-height: 0;
            overflow: hidden;
        }

        body.messenger-page-active .admin-page-content {
            height: 100%;
            min-height: 0;
            overflow: hidden;
        }

        body.messenger-page-active .page-footer {
            display: none;
        }

        .messenger-page {
            height: 100%;
            min-height: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .messenger-shell {
            display: grid;
            grid-template-columns: minmax(240px, 320px) minmax(0, 1fr);
            grid-template-rows: minmax(0, 1fr);
            gap: 1rem;
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
        }

        .messenger-users,
        .messenger-chat {
            border: 1px solid var(--admin-border-color);
            background: var(--admin-surface-color);
        }

        .messenger-users {
            min-height: 0;
            overflow-y: auto;
        }

        .messenger-user-link {
            display: flex;
            gap: 0.75rem;
            padding: 0.9rem 1rem;
            border-bottom: 1px solid var(--admin-border-color);
            color: inherit;
            text-decoration: none;
        }

        .messenger-user-link:hover,
        .messenger-user-link.is-active {
            background: rgba(var(--admin-primary-color-rgb), 0.1);
        }

        .messenger-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-weight: 700;
            background: rgba(var(--admin-primary-color-rgb), 0.12);
            color: var(--admin-primary-color);
        }

        .messenger-chat {
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }

        .messenger-chat-header,
        .messenger-compose {
            padding: 1rem;
            border-bottom: 1px solid var(--admin-border-color);
        }

        .messenger-compose {
            border-top: 1px solid var(--admin-border-color);
            border-bottom: 0;
        }

        .messenger-thread {
            flex: 1 1 auto;
            overflow-y: auto;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            min-height: 0;
        }

        .messenger-bubble {
            max-width: min(680px, 82%);
            padding: 0.8rem 0.95rem;
            border-radius: 0.8rem;
            border: 1px solid var(--admin-border-color);
            background: rgba(var(--admin-primary-color-rgb), 0.07);
        }

        .messenger-bubble.is-own {
            align-self: flex-end;
            background: var(--admin-primary-color);
            color: var(--admin-primary-contrast-color);
            border-color: transparent;
        }

        .messenger-bubble.is-own small,
        .messenger-bubble.is-own div {
            color: var(--admin-primary-contrast-color) !important;
        }

        .messenger-message-text {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        @media (max-width: 991.98px) {
            .messenger-shell {
                grid-template-columns: 1fr;
                grid-template-rows: minmax(150px, 32%) minmax(0, 1fr);
            }
        }
    </style>

    <div class="main-content messenger-page">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">Messenger</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin-dashboard') }}"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Messages</li>
                    </ol>
                </nav>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success border-0">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger border-0">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="messenger-shell">
            <div class="messenger-users rounded-4">
                <div class="p-3 border-bottom">
                    <h5 class="mb-1">Users</h5>
                    <div class="text-muted small">Direct messages between admin users</div>
                </div>

                @forelse ($admins as $admin)
                    @php
                        $name = trim((string) ($admin->name ?: $admin->email));
                        $initials = collect(explode(' ', $name))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('');
                    @endphp
                    <a class="messenger-user-link {{ optional($selectedAdmin)->id === $admin->id ? 'is-active' : '' }}"
                        href="{{ route('admin-messenger', ['user' => $admin->id]) }}">
                        <span class="messenger-avatar">{{ $initials ?: 'A' }}</span>
                        <span class="flex-grow-1 min-w-0">
                            <span class="d-flex justify-content-between gap-2">
                                <strong>{{ $name }}</strong>
                                @if ($admin->unread_messages_count > 0)
                                    <span class="badge bg-danger">{{ $admin->unread_messages_count }}</span>
                                @endif
                            </span>
                            <span class="d-block text-muted small">{{ $admin->display_role_label }}</span>
                            <span class="d-block text-muted small">{{ $admin->email }}</span>
                        </span>
                    </a>
                @empty
                    <div class="p-4 text-muted">No other admin users are available.</div>
                @endforelse
            </div>

            <div class="messenger-chat rounded-4">
                @if ($selectedAdmin)
                    <div class="messenger-chat-header">
                        <h5 class="mb-1">{{ $selectedAdmin->name ?: $selectedAdmin->email }}</h5>
                        <div class="text-muted small">{{ $selectedAdmin->email }}</div>
                    </div>

                    <div class="messenger-thread" id="messengerThread">
                        @forelse ($messages as $message)
                            @php
                                $isOwn = (int) $message->sender_admin_id === (int) $currentAdmin->id;
                            @endphp
                            <div class="messenger-bubble {{ $isOwn ? 'is-own' : '' }}">
                                <div class="messenger-message-text">{{ $message->body }}</div>
                                <small class="d-block mt-2 text-muted">
                                    {{ optional($message->created_at)->format('M d, Y h:i A') }}
                                    @if ($isOwn && $message->read_at)
                                        &middot; Read
                                    @endif
                                </small>
                            </div>
                        @empty
                            <div class="text-center text-muted my-auto">No messages yet.</div>
                        @endforelse
                    </div>

                    <form class="messenger-compose" action="{{ route('admin-messenger-store') }}" method="post">
                        @csrf
                        <input type="hidden" name="receiver_admin_id" value="{{ $selectedAdmin->id }}">
                        <div class="d-flex gap-2">
                            <textarea name="body" class="form-control" rows="2" placeholder="Type a message..." required>{{ old('body') }}</textarea>
                            <button type="submit" class="btn btn-primary px-4">Send</button>
                        </div>
                    </form>
                @else
                    <div class="d-flex align-items-center justify-content-center h-100 p-5 text-muted">
                        Add another admin user to start messaging.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        (function() {
            document.body.classList.add('messenger-page-active');

            var thread = document.getElementById('messengerThread');

            if (thread) {
                thread.scrollTop = thread.scrollHeight;
            }
        })();
    </script>
@endsection
