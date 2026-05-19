import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { createBrowserRouter, Navigate, RouterProvider } from 'react-router';
import { AppLayout } from './components/AppLayout';
import { ContactsForm } from './pages/contacts/Form';
import { ContactsIndex } from './pages/contacts/Index';
import { ContactsShow } from './pages/contacts/Show';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            retry: false,
            refetchOnWindowFocus: false,
        },
    },
});

const router = createBrowserRouter([
    {
        path: '/',
        element: <Navigate to="/contacts" replace />,
    },
    {
        path: '/contacts',
        element: <AppLayout />,
        children: [
            { index: true, element: <ContactsIndex /> },
            { path: 'new', element: <ContactsForm mode="create" /> },
            { path: ':id', element: <ContactsShow /> },
            { path: ':id/edit', element: <ContactsForm mode="edit" /> },
        ],
    },
]);

const container = document.getElementById('app');
if (container === null) {
    throw new Error('Missing #app mount point');
}

createRoot(container).render(
    <StrictMode>
        <QueryClientProvider client={queryClient}>
            <RouterProvider router={router} />
        </QueryClientProvider>
    </StrictMode>,
);
