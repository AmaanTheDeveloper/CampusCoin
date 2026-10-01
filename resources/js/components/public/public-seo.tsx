import { Head } from '@inertiajs/react';

type PublicSeoProps = {
    title: string;
    description: string;
};

export default function PublicSeo({ title, description }: PublicSeoProps) {
    return (
        <Head title={title}>
            <meta head-key="description" name="description" content={description} />
        </Head>
    );
}
