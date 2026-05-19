import type { JSX } from 'react';
import { Link, Outlet } from 'react-router';

export function AppLayout(): JSX.Element {
    return (
        <div className="min-h-screen bg-gray-50 text-gray-900">
            <header className="border-b border-gray-200 bg-white">
                <div className="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
                    <Link
                        to="/contacts"
                        className="text-lg font-semibold text-gray-900"
                    >
                        Rex Contacts
                    </Link>
                    <Link
                        to="/contacts/new"
                        className="rounded bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700"
                    >
                        New Contact
                    </Link>
                </div>
            </header>
            <main className="mx-auto max-w-4xl px-6 py-8">
                <Outlet />
            </main>
        </div>
    );
}
