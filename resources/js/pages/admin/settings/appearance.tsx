import AppearanceTabs from '@/components/appearance-tabs';
import HeadingSmall from '@/components/heading-small';
import AdminLayout from '@/layouts/admin-layout';
import AdminSettingsLayout from '@/layouts/admin/settings-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Settings', href: '/admin/settings' },
    { title: 'Appearance', href: '/admin/settings/appearance' },
];

export default function AdminAppearanceSettings() {
    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Appearance" />
            <AdminSettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Appearance" description="Choose light, dark, or system theme for the Admin Control Center." />
                    <AppearanceTabs />
                </div>
            </AdminSettingsLayout>
        </AdminLayout>
    );
}
