import { z } from 'zod';

const phoneFieldSchema = z.object({
    value: z
        .string()
        .refine((value) => value === '' || /^\+(61|64)\d{8,10}$/.test(value), {
            message:
                'Must be E164 starting with +61 (AU) or +64 (NZ), digits only',
        }),
});

const emailFieldSchema = z.object({
    value: z
        .string()
        .max(254, 'Must be 254 characters or fewer')
        .refine(
            (value) =>
                value === '' || z.string().email().safeParse(value).success,
            {
                message: 'Must be a valid email address',
            },
        ),
});

export const contactFormSchema = z
    .object({
        name: z.string().min(1, 'Name is required').max(255),
        phones: z.array(phoneFieldSchema),
        emails: z.array(emailFieldSchema),
    })
    .refine(
        (value) => {
            const nonEmptyPhones = value.phones.filter(
                (entry) => entry.value !== '',
            );
            const nonEmptyEmails = value.emails.filter(
                (entry) => entry.value !== '',
            );
            return nonEmptyPhones.length > 0 || nonEmptyEmails.length > 0;
        },
        {
            message: 'Add at least one phone or email',
            path: ['name'],
        },
    );

export type ContactFormValues = z.infer<typeof contactFormSchema>;
