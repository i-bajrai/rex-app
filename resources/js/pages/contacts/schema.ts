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
    .superRefine((value, ctx) => {
        const nonEmptyPhones = value.phones.filter(
            (entry) => entry.value !== '',
        );
        const nonEmptyEmails = value.emails.filter(
            (entry) => entry.value !== '',
        );
        if (nonEmptyPhones.length === 0 && nonEmptyEmails.length === 0) {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: 'Add at least one phone or email',
                path: ['name'],
            });
        }

        flagDuplicateIndices(value.phones.map((entry) => entry.value)).forEach(
            (index) => {
                ctx.addIssue({
                    code: z.ZodIssueCode.custom,
                    message:
                        'This phone number is duplicated in your submission.',
                    path: ['phones', index, 'value'],
                    params: { code: 'duplicate' },
                });
            },
        );

        flagDuplicateIndices(
            value.emails.map((entry) => entry.value.toLowerCase()),
        ).forEach((index) => {
            ctx.addIssue({
                code: z.ZodIssueCode.custom,
                message: 'This email address is duplicated in your submission.',
                path: ['emails', index, 'value'],
                params: { code: 'duplicate' },
            });
        });
    });

function flagDuplicateIndices(values: string[]): number[] {
    const seen = new Map<string, number>();
    const duplicates: number[] = [];
    values.forEach((value, index) => {
        if (value === '') {
            return;
        }
        const firstIndex = seen.get(value);
        if (firstIndex === undefined) {
            seen.set(value, index);
            return;
        }
        if (!duplicates.includes(firstIndex)) {
            duplicates.push(firstIndex);
        }
        duplicates.push(index);
    });
    return duplicates;
}

export type ContactFormValues = z.infer<typeof contactFormSchema>;
