import { useQuery } from '@tanstack/react-query';
import { useState, type JSX } from 'react';
import { Link } from 'react-router';
import {
    listContacts,
    searchContacts,
    type ContactListRow,
    type ContactSearchQuery,
} from '../../api/contacts';

type SearchForm = {
    name: string;
    phone: string;
    email_domain: string;
};

const EMPTY_SEARCH: SearchForm = { name: '', phone: '', email_domain: '' };

function hasAnyCriterion(form: SearchForm): boolean {
    return form.name !== '' || form.phone !== '' || form.email_domain !== '';
}

export function ContactsIndex(): JSX.Element {
    const [form, setForm] = useState<SearchForm>(EMPTY_SEARCH);
    const [active, setActive] = useState<SearchForm>(EMPTY_SEARCH);

    const searchActive = hasAnyCriterion(active);

    const listQuery = useQuery({
        queryKey: ['contacts', 'list'],
        queryFn: () => listContacts(),
        enabled: !searchActive,
    });

    const searchQuery = useQuery({
        queryKey: ['contacts', 'search', active],
        queryFn: () => {
            const query: ContactSearchQuery = {};
            if (active.name !== '') {
                query.name = active.name;
            }
            if (active.phone !== '') {
                query.phone = active.phone;
            }
            if (active.email_domain !== '') {
                query.email_domain = active.email_domain;
            }
            return searchContacts(query);
        },
        enabled: searchActive,
    });

    const activeQuery = searchActive ? searchQuery : listQuery;
    const rows: ContactListRow[] = activeQuery.data?.data ?? [];

    return (
        <div>
            <h1 className="mb-4 text-2xl font-semibold">Contacts</h1>

            <form
                className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    setActive(form);
                }}
            >
                <input
                    type="text"
                    name="name"
                    placeholder="Name"
                    value={form.name}
                    onChange={(event) =>
                        setForm({ ...form, name: event.target.value })
                    }
                    className="rounded border border-gray-300 px-3 py-2 text-sm"
                />
                <input
                    type="text"
                    name="phone"
                    placeholder="Phone (E164)"
                    value={form.phone}
                    onChange={(event) =>
                        setForm({ ...form, phone: event.target.value })
                    }
                    className="rounded border border-gray-300 px-3 py-2 text-sm"
                />
                <input
                    type="text"
                    name="email_domain"
                    placeholder="Email domain"
                    value={form.email_domain}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            email_domain: event.target.value,
                        })
                    }
                    className="rounded border border-gray-300 px-3 py-2 text-sm"
                />
                <div className="flex gap-2">
                    <button
                        type="submit"
                        className="rounded bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700"
                    >
                        Search
                    </button>
                    {searchActive ? (
                        <button
                            type="button"
                            onClick={() => {
                                setForm(EMPTY_SEARCH);
                                setActive(EMPTY_SEARCH);
                            }}
                            className="rounded border border-gray-300 px-3 py-2 text-sm font-medium hover:bg-gray-100"
                        >
                            Clear
                        </button>
                    ) : null}
                </div>
            </form>

            {activeQuery.isPending ? (
                <p className="text-sm text-gray-500">Loading…</p>
            ) : null}

            {activeQuery.isError ? (
                <p className="text-sm text-red-600">
                    Failed to load contacts. {activeQuery.error.message}
                </p>
            ) : null}

            {!activeQuery.isPending &&
            !activeQuery.isError &&
            rows.length === 0 ? (
                <p
                    className="rounded border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500"
                    data-testid="empty-state"
                >
                    {searchActive
                        ? 'No contacts matched your search.'
                        : 'No contacts yet. Add one to get started.'}
                </p>
            ) : null}

            {rows.length > 0 ? (
                <ul className="divide-y divide-gray-200 rounded border border-gray-200 bg-white">
                    {rows.map((row) => (
                        <li key={row.id}>
                            <Link
                                to={`/contacts/${row.id}`}
                                className="flex items-center justify-between px-4 py-3 hover:bg-gray-50"
                            >
                                <span className="font-medium text-gray-900">
                                    {row.name}
                                </span>
                                <span className="text-xs text-gray-500">
                                    {row.phones_count ?? 0} phone(s) ·{' '}
                                    {row.emails_count ?? 0} email(s)
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
