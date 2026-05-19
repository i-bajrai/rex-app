import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState, type JSX } from 'react';
import { Link, useNavigate, useParams } from 'react-router';
import {
    ApiError,
    deleteContact,
    getContact,
    placeCall,
    type CallOutcome,
} from '../../api/contacts';

type CallState =
    | { kind: 'idle' }
    | { kind: 'pending' }
    | { kind: 'outcome'; outcome: CallOutcome }
    | { kind: 'no_phone'; message: string }
    | { kind: 'rate_limited'; retryAfter: number }
    | { kind: 'error'; message: string };

const STATUS_LABEL: Record<CallOutcome['status'], string> = {
    connected: 'Connected',
    no_answer: 'No answer',
    busy: 'Busy',
    failed: 'Failed',
    invalid_number: 'Invalid number',
};

function CallOutcomePanel({ state }: { state: CallState }): JSX.Element | null {
    if (state.kind === 'idle') {
        return null;
    }

    if (state.kind === 'pending') {
        return (
            <p
                className="mt-3 rounded border border-gray-200 p-3 text-sm text-gray-600"
                role="status"
            >
                Placing call…
            </p>
        );
    }

    if (state.kind === 'no_phone') {
        return (
            <p
                className="mt-3 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
                data-testid="call-no-phone"
            >
                No phone on file — add a phone to this contact before calling.
                <span className="block text-xs text-amber-700">
                    {state.message}
                </span>
            </p>
        );
    }

    if (state.kind === 'rate_limited') {
        return (
            <p
                className="mt-3 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800"
                data-testid="call-rate-limited"
            >
                Try again in {state.retryAfter}s.
            </p>
        );
    }

    if (state.kind === 'error') {
        return (
            <p
                className="mt-3 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                data-testid="call-error"
            >
                {state.message}
            </p>
        );
    }

    const outcome = state.outcome;

    if (outcome.status === 'connected') {
        return (
            <div
                className="mt-3 rounded border border-green-200 bg-green-50 p-3 text-sm text-green-800"
                data-testid="call-connected"
            >
                <p className="font-medium">
                    Connected — {outcome.duration_seconds ?? 0}s
                </p>
                {outcome.provider_message !== null ? (
                    <p className="mt-1 text-xs text-green-700">
                        {outcome.provider_message}
                    </p>
                ) : null}
            </div>
        );
    }

    return (
        <div
            className="mt-3 rounded border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700"
            data-testid={`call-${outcome.status}`}
        >
            <p className="font-medium">{STATUS_LABEL[outcome.status]}</p>
            {outcome.provider_message !== null ? (
                <p className="mt-1 text-xs text-gray-600">
                    {outcome.provider_message}
                </p>
            ) : null}
        </div>
    );
}

export function ContactsShow(): JSX.Element {
    const params = useParams<{ id: string }>();
    const id = Number(params.id);
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const [callState, setCallState] = useState<CallState>({ kind: 'idle' });

    const contactQuery = useQuery({
        queryKey: ['contacts', 'show', id],
        queryFn: () => getContact(id),
        enabled: !Number.isNaN(id),
    });

    const callMutation = useMutation({
        mutationFn: () => placeCall(id),
        onMutate: () => setCallState({ kind: 'pending' }),
        onSuccess: (outcome) => setCallState({ kind: 'outcome', outcome }),
        onError: (error: Error) => {
            if (error instanceof ApiError) {
                if (error.code === 'contact.call.no_phone') {
                    setCallState({ kind: 'no_phone', message: error.message });
                    return;
                }
                if (error.code === 'contact.call.rate_limited') {
                    const details = error.details as {
                        retry_after_seconds?: number;
                    } | null;
                    const retryAfter = details?.retry_after_seconds ?? 60;
                    setCallState({ kind: 'rate_limited', retryAfter });
                    return;
                }
                setCallState({ kind: 'error', message: error.message });
                return;
            }
            setCallState({ kind: 'error', message: error.message });
        },
    });

    const deleteMutation = useMutation({
        mutationFn: () => deleteContact(id),
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: ['contacts'] });
            navigate('/contacts');
        },
    });

    useEffect(() => {
        if (callState.kind !== 'rate_limited') {
            return;
        }
        if (callState.retryAfter <= 0) {
            setCallState({ kind: 'idle' });
            return;
        }
        const handle = window.setTimeout(() => {
            setCallState({
                kind: 'rate_limited',
                retryAfter: callState.retryAfter - 1,
            });
        }, 1000);
        return () => window.clearTimeout(handle);
    }, [callState]);

    if (Number.isNaN(id)) {
        return <p className="text-sm text-red-600">Invalid contact id.</p>;
    }

    if (contactQuery.isPending) {
        return <p className="text-sm text-gray-500">Loading…</p>;
    }

    if (contactQuery.isError) {
        return (
            <div>
                <p className="text-sm text-red-600">
                    {contactQuery.error.message}
                </p>
                <Link
                    to="/contacts"
                    className="mt-3 inline-block text-sm text-gray-700 underline"
                >
                    Back to list
                </Link>
            </div>
        );
    }

    const contact = contactQuery.data.data;
    const hasPhone = contact.phones.length > 0;
    const callPending =
        callMutation.isPending ||
        callState.kind === 'pending' ||
        callState.kind === 'rate_limited' ||
        (callState.kind === 'outcome' &&
            callState.outcome.status === 'connected');

    return (
        <div>
            <div className="mb-4 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">{contact.name}</h1>
                <div className="flex gap-2">
                    <Link
                        to={`/contacts/${contact.id}/edit`}
                        className="rounded border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-100"
                    >
                        Edit
                    </Link>
                    <button
                        type="button"
                        onClick={() => deleteMutation.mutate()}
                        disabled={deleteMutation.isPending}
                        className="rounded border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50"
                    >
                        Delete
                    </button>
                </div>
            </div>

            <section className="mb-6 rounded border border-gray-200 bg-white p-4">
                <h2 className="mb-2 text-sm font-semibold tracking-wide text-gray-500 uppercase">
                    Phones
                </h2>
                {hasPhone ? (
                    <ul className="text-sm text-gray-900">
                        {contact.phones.map((phone) => (
                            <li key={phone} className="py-1">
                                {phone}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-sm text-gray-500">No phones.</p>
                )}
            </section>

            <section className="mb-6 rounded border border-gray-200 bg-white p-4">
                <h2 className="mb-2 text-sm font-semibold tracking-wide text-gray-500 uppercase">
                    Emails
                </h2>
                {contact.emails.length > 0 ? (
                    <ul className="text-sm text-gray-900">
                        {contact.emails.map((email) => (
                            <li key={email} className="py-1">
                                {email}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-sm text-gray-500">No emails.</p>
                )}
            </section>

            <section className="rounded border border-gray-200 bg-white p-4">
                <h2 className="mb-2 text-sm font-semibold tracking-wide text-gray-500 uppercase">
                    Place Call
                </h2>
                <button
                    type="button"
                    onClick={() => callMutation.mutate()}
                    disabled={!hasPhone || callPending}
                    className="rounded bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-50"
                    data-testid="place-call"
                >
                    {callPending ? 'Working…' : 'Place Call'}
                </button>
                {!hasPhone ? (
                    <p className="mt-2 text-xs text-gray-500">
                        No phone on file — add a phone to this contact first.
                    </p>
                ) : null}
                <CallOutcomePanel state={callState} />
                {callState.kind !== 'idle' && callState.kind !== 'pending' ? (
                    <button
                        type="button"
                        onClick={() => setCallState({ kind: 'idle' })}
                        className="mt-2 text-xs text-gray-500 underline"
                    >
                        Dismiss
                    </button>
                ) : null}
            </section>

            <Link
                to="/contacts"
                className="mt-6 inline-block text-sm text-gray-700 underline"
            >
                Back to list
            </Link>
        </div>
    );
}
