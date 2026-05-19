import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, type JSX } from 'react';
import { useFieldArray, useForm } from 'react-hook-form';
import { Link, useNavigate, useParams } from 'react-router';
import {
    ApiError,
    createContact,
    getContact,
    mapServerErrorsToFields,
    updateContact,
} from '../../api/contacts';
import { contactFormSchema, type ContactFormValues } from './schema';

type Props = { mode: 'create' | 'edit' };

const DEFAULT_VALUES: ContactFormValues = {
    name: '',
    phones: [{ value: '' }],
    emails: [{ value: '' }],
};

type ServerMappableValues = {
    phones: string[];
    emails: string[];
};

export function ContactsForm({ mode }: Props): JSX.Element {
    const navigate = useNavigate();
    const params = useParams<{ id: string }>();
    const id = mode === 'edit' ? Number(params.id) : null;
    const queryClient = useQueryClient();

    const existing = useQuery({
        queryKey: ['contacts', 'show', id],
        queryFn: () => getContact(id as number),
        enabled: mode === 'edit' && id !== null && !Number.isNaN(id),
    });

    const form = useForm<ContactFormValues>({
        resolver: zodResolver(contactFormSchema),
        defaultValues: DEFAULT_VALUES,
        mode: 'onSubmit',
    });

    const phones = useFieldArray({
        control: form.control,
        name: 'phones',
    });
    const emails = useFieldArray({
        control: form.control,
        name: 'emails',
    });

    useEffect(() => {
        if (mode === 'edit' && existing.data) {
            const c = existing.data.data;
            form.reset({
                name: c.name,
                phones:
                    c.phones.length > 0
                        ? c.phones.map((value) => ({ value }))
                        : [{ value: '' }],
                emails:
                    c.emails.length > 0
                        ? c.emails.map((value) => ({ value }))
                        : [{ value: '' }],
            });
        }
    }, [mode, existing.data, form]);

    const mutation = useMutation({
        mutationFn: (values: ContactFormValues) => {
            const payload = {
                name: values.name,
                phones: values.phones
                    .map((row) => row.value)
                    .filter((value) => value !== ''),
                emails: values.emails
                    .map((row) => row.value)
                    .filter((value) => value !== ''),
            };
            if (mode === 'edit' && id !== null) {
                return updateContact(id, payload);
            }
            return createContact(payload);
        },
        onSuccess: async (response) => {
            await queryClient.invalidateQueries({ queryKey: ['contacts'] });
            navigate(`/contacts/${response.data.id}`);
        },
        onError: (error: Error) => {
            if (error instanceof ApiError) {
                const current = form.getValues();
                const mapTarget: ServerMappableValues = {
                    phones: current.phones.map((row) => row.value),
                    emails: current.emails.map((row) => row.value),
                };
                const mapped = mapServerErrorsToFields(
                    error.details,
                    (field, opts) => {
                        // react-hook-form's setError accepts dot-paths; remap
                        // server's `phones.N` / `emails.N` to `phones.N.value`
                        const remapped = field.replace(
                            /^(phones|emails)\.(\d+)$/,
                            '$1.$2.value',
                        );
                        form.setError(
                            remapped as Parameters<typeof form.setError>[0],
                            opts,
                        );
                    },
                    error.message,
                    mapTarget,
                );
                if (!mapped) {
                    form.setError('root', {
                        type: 'server',
                        message: error.message,
                    });
                }
                return;
            }
            form.setError('root', { type: 'server', message: error.message });
        },
    });

    const onSubmit = form.handleSubmit((values) => mutation.mutate(values));

    const rootError = form.formState.errors.root?.message;

    return (
        <div>
            <h1 className="mb-4 text-2xl font-semibold">
                {mode === 'edit' ? 'Edit Contact' : 'New Contact'}
            </h1>

            <form onSubmit={onSubmit} className="space-y-5">
                <div>
                    <label
                        htmlFor="name"
                        className="mb-1 block text-sm font-medium"
                    >
                        Name
                    </label>
                    <input
                        id="name"
                        type="text"
                        {...form.register('name')}
                        className="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                    />
                    {form.formState.errors.name ? (
                        <p
                            className="mt-1 text-xs text-red-600"
                            data-testid="error-name"
                        >
                            {form.formState.errors.name.message}
                        </p>
                    ) : null}
                </div>

                <fieldset>
                    <legend className="mb-1 text-sm font-medium">
                        Phones (E164, AU +61 / NZ +64)
                    </legend>
                    {phones.fields.map((field, index) => {
                        const error =
                            form.formState.errors.phones?.[index]?.value
                                ?.message;
                        return (
                            <div
                                key={field.id}
                                className="mb-2 flex items-start gap-2"
                            >
                                <div className="flex-1">
                                    <input
                                        type="text"
                                        {...form.register(
                                            `phones.${index}.value` as const,
                                        )}
                                        placeholder="+61412345678"
                                        className="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                                    />
                                    {error ? (
                                        <p
                                            className="mt-1 text-xs text-red-600"
                                            data-testid={`error-phones-${index}`}
                                        >
                                            {error}
                                        </p>
                                    ) : null}
                                </div>
                                <button
                                    type="button"
                                    onClick={() => phones.remove(index)}
                                    className="rounded border border-gray-300 px-2 py-2 text-xs"
                                >
                                    Remove
                                </button>
                            </div>
                        );
                    })}
                    <button
                        type="button"
                        onClick={() => phones.append({ value: '' })}
                        className="text-sm text-gray-700 underline"
                    >
                        Add phone
                    </button>
                </fieldset>

                <fieldset>
                    <legend className="mb-1 text-sm font-medium">Emails</legend>
                    {emails.fields.map((field, index) => {
                        const error =
                            form.formState.errors.emails?.[index]?.value
                                ?.message;
                        return (
                            <div
                                key={field.id}
                                className="mb-2 flex items-start gap-2"
                            >
                                <div className="flex-1">
                                    <input
                                        type="text"
                                        {...form.register(
                                            `emails.${index}.value` as const,
                                        )}
                                        placeholder="jane@example.com"
                                        className="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                                    />
                                    {error ? (
                                        <p
                                            className="mt-1 text-xs text-red-600"
                                            data-testid={`error-emails-${index}`}
                                        >
                                            {error}
                                        </p>
                                    ) : null}
                                </div>
                                <button
                                    type="button"
                                    onClick={() => emails.remove(index)}
                                    className="rounded border border-gray-300 px-2 py-2 text-xs"
                                >
                                    Remove
                                </button>
                            </div>
                        );
                    })}
                    <button
                        type="button"
                        onClick={() => emails.append({ value: '' })}
                        className="text-sm text-gray-700 underline"
                    >
                        Add email
                    </button>
                </fieldset>

                {rootError !== undefined ? (
                    <p
                        className="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700"
                        data-testid="error-root"
                    >
                        {rootError}
                    </p>
                ) : null}

                <div className="flex gap-2">
                    <button
                        type="submit"
                        disabled={mutation.isPending}
                        className="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50"
                    >
                        {mutation.isPending
                            ? 'Saving…'
                            : mode === 'edit'
                              ? 'Save changes'
                              : 'Create contact'}
                    </button>
                    <Link
                        to="/contacts"
                        className="rounded border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-100"
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    );
}
