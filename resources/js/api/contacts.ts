export type ServerErrorSetter = (
    field: string,
    options: { type: string; message: string },
) => void;

export type ServerMappableValues = {
    phones?: string[];
    emails?: string[];
};

export type ContactListRow = {
    id: number;
    name: string;
    created_at: string;
    updated_at: string;
    phones_count?: number;
    emails_count?: number;
};

export type Contact = {
    id: number;
    name: string;
    created_at: string;
    updated_at: string;
    phones: string[];
    emails: string[];
};

export type CallStatus =
    | 'connected'
    | 'no_answer'
    | 'busy'
    | 'failed'
    | 'invalid_number';

export type CallOutcome = {
    status: CallStatus;
    duration_seconds: number | null;
    provider_message: string | null;
};

export type ContactPayload = {
    name: string;
    phones: string[];
    emails: string[];
};

export type ContactSearchQuery = {
    name?: string;
    phone?: string;
    email_domain?: string;
};

export type FieldDetail = { field: string; code: string; message: string };

export type ApiErrorDetails = FieldDetail[] | Record<string, unknown> | null;

export type ApiErrorBody = {
    error: {
        code: string;
        message: string;
        details: ApiErrorDetails;
    };
};

export class ApiError extends Error {
    constructor(
        public readonly status: number,
        public readonly code: string,
        message: string,
        public readonly details: ApiErrorDetails,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

function csrfToken(): string {
    const meta = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    );

    return meta?.content ?? '';
}

async function request<T>(
    path: string,
    init: RequestInit & { method?: string } = {},
): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (init.body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const token = csrfToken();
    if (token !== '') {
        headers['X-CSRF-TOKEN'] = token;
    }

    const response = await fetch(path, {
        ...init,
        credentials: 'same-origin',
        headers: { ...headers, ...(init.headers ?? {}) },
    });

    if (response.status === 204) {
        return undefined as T;
    }

    const text = await response.text();
    const parsed = text === '' ? null : (JSON.parse(text) as unknown);

    if (!response.ok) {
        const body = parsed as ApiErrorBody | null;
        const code = body?.error?.code ?? 'unknown_error';
        const message =
            body?.error?.message ?? `Request failed (${response.status})`;
        const details = body?.error?.details ?? null;
        throw new ApiError(response.status, code, message, details);
    }

    return parsed as T;
}

export function listContacts(): Promise<{ data: ContactListRow[] }> {
    return request<{ data: ContactListRow[] }>('/api/v1/contacts');
}

export function getContact(id: number): Promise<{ data: Contact }> {
    return request<{ data: Contact }>(`/api/v1/contacts/${id}`);
}

export function searchContacts(
    query: ContactSearchQuery,
): Promise<{ data: ContactListRow[] }> {
    const params = new URLSearchParams();
    if (query.name !== undefined && query.name !== '') {
        params.set('name', query.name);
    }
    if (query.phone !== undefined && query.phone !== '') {
        params.set('phone', query.phone);
    }
    if (query.email_domain !== undefined && query.email_domain !== '') {
        params.set('email_domain', query.email_domain);
    }

    return request<{ data: ContactListRow[] }>(
        `/api/v1/contacts/search?${params.toString()}`,
    );
}

export function createContact(
    payload: ContactPayload,
): Promise<{ data: Contact }> {
    return request<{ data: Contact }>('/api/v1/contacts', {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

export function updateContact(
    id: number,
    payload: ContactPayload,
): Promise<{ data: Contact }> {
    return request<{ data: Contact }>(`/api/v1/contacts/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    });
}

export function deleteContact(id: number): Promise<void> {
    return request<void>(`/api/v1/contacts/${id}`, { method: 'DELETE' });
}

export function placeCall(id: number): Promise<CallOutcome> {
    return request<CallOutcome>(`/api/v1/contacts/${id}/call`, {
        method: 'POST',
    });
}

/**
 * Map a server `details` payload onto react-hook-form field errors.
 *
 * Supports:
 *   - Field-level envelope `[{field, code, message}, ...]` — used for
 *     `validation_failed` (422 from Laravel form-request validation).
 *   - Domain rejection envelope `{phone: <string>}` / `{email: <string>}` —
 *     surfaces the offending value as an error on the matching field by
 *     scanning the form values.
 */
export function mapServerErrorsToFields(
    details: ApiErrorDetails,
    setError: ServerErrorSetter,
    message: string,
    currentValues: ServerMappableValues,
): boolean {
    if (Array.isArray(details)) {
        details.forEach((detail) => {
            setError(detail.field, {
                type: 'server',
                message: detail.message,
            });
        });
        return details.length > 0;
    }

    if (details === null || typeof details !== 'object') {
        return false;
    }

    const offendingPhone =
        typeof (details as { phone?: unknown }).phone === 'string'
            ? (details as { phone: string }).phone
            : null;
    const offendingEmail =
        typeof (details as { email?: unknown }).email === 'string'
            ? (details as { email: string }).email
            : null;

    let mapped = false;

    if (offendingPhone !== null) {
        const phones = currentValues.phones ?? [];
        const idx = phones.findIndex(
            (value) => value.toLowerCase() === offendingPhone.toLowerCase(),
        );
        if (idx >= 0) {
            setError(`phones.${idx}`, { type: 'server', message });
            mapped = true;
        }
    }

    if (offendingEmail !== null) {
        const emails = currentValues.emails ?? [];
        const idx = emails.findIndex(
            (value) => value.toLowerCase() === offendingEmail.toLowerCase(),
        );
        if (idx >= 0) {
            setError(`emails.${idx}`, { type: 'server', message });
            mapped = true;
        }
    }

    return mapped;
}
